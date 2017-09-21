#!/usr/bin/env bash

# @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
#
# @license GNU AGPL version 3 or any later version
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# Helper script to run the integration tests on a fresh Nextcloud server through
# Docker.
#
# The integration tests are run in its own Docker container; the grand-grand-
# grandparent directory of the integration tests directory (that is, the root
# directory of the Nextcloud server, as it is assumed that the parent directory
# of this application is the "apps" directory of a Nextcloud server) is copied
# to the container and the integration tests are run inside it; in the container
# the configuration/data from the original Nextcloud server is ignored, and a
# new server installation is performed inside the container instead. Once the
# tests end the container is stopped.
#
# To perform its job, the script requires the "docker" command to be available.
#
# The Docker Command Line Interface (the "docker" command) requires special
# permissions to talk to the Docker daemon, and those permissions are typically
# available only to the root user. Please see the Docker documentation to find
# out how to give access to a regular user to the Docker daemon:
# https://docs.docker.com/engine/installation/linux/linux-postinstall/
#
# Note, however, that being able to communicate with the Docker daemon is the
# same as being able to get root privileges for the system. Therefore, you must
# give access to the Docker daemon (and thus run this script as) ONLY to trusted
# and secure users:
# https://docs.docker.com/engine/security/security/#docker-daemon-attack-surface
#
# Finally, take into account that this script will automatically remove the
# Docker container named "nextcloud-local-test-integration-spreed", even if the
# script did not create it (probably you will not have containers nor images
# with that name, but just in case).

# Sets the variables that abstract the differences in command names and options
# between operating systems.
#
# Switches between mktemp on GNU/Linux and gmktemp on macOS.
function setOperatingSystemAbstractionVariables() {
	case "$OSTYPE" in
		darwin*)
			if [ "$(which gtimeout)" == "" ]; then
				echo "Please install coreutils (brew install coreutils)"
				exit 1
			fi

			MKTEMP=gmktemp
			;;
		linux*)
			MKTEMP=mktemp
			;;
		*)
			echo "Operating system ($OSTYPE) not supported"
			exit 1
			;;
	esac
}

# Creates a Docker container to run the integration tests.
#
# This function starts a Docker container with a copy of the Nextcloud code from
# the grand-grand-grandparent directory, although ignoring any configuration or
# data that it may provide (for example, if that directory was used directly to
# deploy a Nextcloud instance in a web server). As the Nextcloud code is copied
# to the container instead of referenced the original code can be modified while
# the integration tests are running without interfering in them.
function prepareDocker() {
	NEXTCLOUD_LOCAL_CONTAINER=nextcloud-local-test-integration-spreed

	echo "Starting the Nextcloud container"
	# When using "nextcloudci/phpX.Y" images the container exits immediately if
	# no command is given, so a Bash session is created to prevent that.
	docker run --detach --name=$NEXTCLOUD_LOCAL_CONTAINER --interactive --tty $NEXTCLOUD_LOCAL_IMAGE bash

	# Use the $TMPDIR or, if not set, fall back to /tmp.
	NEXTCLOUD_LOCAL_TAR="$($MKTEMP --tmpdir="${TMPDIR:-/tmp}" --suffix=.tar nextcloud-local-XXXXXXXXXX)"

	# Setting the user and group of files in the tar would be superfluous, as
	# "docker cp" does not take them into account (the extracted files are set
	# to root).
	echo "Copying local Git working directory of Nextcloud to the container"
	tar --create --file="$NEXTCLOUD_LOCAL_TAR" --exclude=".git" --exclude="./build" --exclude="./config/config.php" --exclude="./data" --exclude="./data-autotest" --exclude="./tests" --directory=../../../../ .

	docker exec $NEXTCLOUD_LOCAL_CONTAINER mkdir /nextcloud
	docker cp - $NEXTCLOUD_LOCAL_CONTAINER:/nextcloud/ < "$NEXTCLOUD_LOCAL_TAR"

	echo "Installing Nextcloud in the container"
	docker exec $NEXTCLOUD_LOCAL_CONTAINER bash -c "cd nextcloud && php occ maintenance:install --admin-pass=admin"
}

# Removes/stops temporal elements created/started by this script.
function cleanUp() {
	# Disable (yes, "+" disables) exiting immediately on errors to ensure that
	# all the cleanup commands are executed (well, no errors should occur during
	# the cleanup anyway, but just in case).
	set +o errexit

	echo "Cleaning up"

	if [ -f "$NEXTCLOUD_LOCAL_TAR" ]; then
		echo "Removing $NEXTCLOUD_LOCAL_TAR"
	    rm $NEXTCLOUD_LOCAL_TAR
	fi

	# The name filter must be specified as "^/XXX$" to get an exact match; using
	# just "XXX" would match every name that contained "XXX".
	if [ -n "$(docker ps --all --quiet --filter name="^/$NEXTCLOUD_LOCAL_CONTAINER$")" ]; then
		echo "Removing Docker container $NEXTCLOUD_LOCAL_CONTAINER"
		docker rm --volumes --force $NEXTCLOUD_LOCAL_CONTAINER
	fi
}

# Exit immediately on errors.
set -o errexit

# Execute cleanUp when the script exits, either normally or due to an error.
trap cleanUp EXIT

# Ensure working directory is script directory, as some actions (like copying
# the Git working directory to the container) expect that.
cd "$(dirname $0)"

# "--image XXX" option can be provided to set the Docker image to use to run
# the integration tests (one of the "nextcloudci/phpX.Y:phpX.Y-Z" images).
NEXTCLOUD_LOCAL_IMAGE="nextcloudci/php7.1:php7.1-15"
if [ "$1" = "--image" ]; then
	NEXTCLOUD_LOCAL_IMAGE=$2

	shift 2
fi

# If no parameter is provided to this script all the integration tests are run.
SCENARIO_TO_RUN=$1

setOperatingSystemAbstractionVariables

prepareDocker

echo "Running tests"
# --tty is needed to get colourful output.
docker exec --tty $NEXTCLOUD_LOCAL_CONTAINER bash -c "cd nextcloud/apps/spreed/tests/integration && ./run.sh $SCENARIO_TO_RUN"
