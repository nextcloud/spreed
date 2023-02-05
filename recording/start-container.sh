#!/usr/bin/env bash

# @copyright Copyright (c) 2023, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

# Helper script to run the recording backend for Nextcloud Talk.
#
# The recording backend is implemented in several Python files. This Bash script
# is provided to set up a Docker container with Selenium, a web browser and all
# the needed Python dependencies for the recording backend.
#
# This script creates an Ubuntu container, installs all the needed dependencies
# in it and executes the recording backend inside the container. If the
# container exists already the previous container will be reused and this script
# will simply execute the recording backend in it.
#
# Due to that the Docker container will not be stopped nor removed when the
# script exits (except when the container was created but it could not be
# started); that must be explicitly done once the container is no longer needed.
#
#
#
# DOCKER AND PERMISSIONS
#
# To perform its job, this script requires the "docker" command to be available.
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

# Sets the variables that abstract the differences in command names and options
# between operating systems.
#
# Switches between timeout on GNU/Linux and gtimeout on macOS (same for mktemp
# and gmktemp).
function setOperatingSystemAbstractionVariables() {
	case "$OSTYPE" in
		darwin*)
			if [ "$(which gtimeout)" == "" ]; then
				echo "Please install coreutils (brew install coreutils)"
				exit 1
			fi

			MKTEMP=gmktemp
			TIMEOUT=gtimeout
			DOCKER_OPTIONS="-e no_proxy=localhost "
			;;
		linux*)
			MKTEMP=mktemp
			TIMEOUT=timeout
			DOCKER_OPTIONS=" "
			;;
		*)
			echo "Operating system ($OSTYPE) not supported"
			exit 1
			;;
	esac
}

# Removes Docker container if it was created but failed to start.
function cleanUp() {
	# Disable (yes, "+" disables) exiting immediately on errors to ensure that
	# all the cleanup commands are executed (well, no errors should occur during
	# the cleanup anyway, but just in case).
	set +o errexit

	# The name filter must be specified as "^/XXX$" to get an exact match; using
	# just "XXX" would match every name that contained "XXX".
	if [ -n "$(docker ps --all --quiet --filter status=created --filter name="^/$CONTAINER$")" ]; then
		echo "Removing Docker container $CONTAINER"
		docker rm --volumes --force $CONTAINER
	fi
}

# Exit immediately on errors.
set -o errexit

# Execute cleanUp when the script exits, either normally or due to an error.
trap cleanUp EXIT

# Ensure working directory is script directory, as some actions (like copying
# the files to the container) expect that.
cd "$(dirname $0)"

HELP="Usage: $(basename $0) [OPTION]...

Options (all options can be omitted, but when present they must appear in the
following order):
--help prints this help and exits.
--container CONTAINER_NAME the name to assign to the container. Defaults to
  talk-recording.
--time-zone TIME_ZONE the time zone to use inside the container. Defaults to
  UTC. The recording backend can be started again later with a different time
  zone (although other commands executed in the container with 'docker exec'
  will still use the time zone specified during creation).
--dev-shm-size SIZE the size to assign to /dev/shm in the Docker container.
  Defaults to 2g"
if [ "$1" = "--help" ]; then
	echo "$HELP"

	exit 0
fi

CONTAINER="talk-recording"
if [ "$1" = "--container" ]; then
	CONTAINER="$2"

	shift 2
fi

if [ "$1" = "--time-zone" ]; then
	TIME_ZONE="$2"

	shift 2
fi

CUSTOM_CONTAINER_OPTIONS=false

# 2g is the default value recommended in the documentation of the Docker images
# for Selenium:
# https://github.com/SeleniumHQ/docker-selenium#--shm-size2g
DEV_SHM_SIZE="2g"
if [ "$1" = "--dev-shm-size" ]; then
	DEV_SHM_SIZE="$2"
	CUSTOM_CONTAINER_OPTIONS=true

	shift 2
fi

if [ -n "$1" ]; then
	echo "Invalid option (or at invalid position): $1

$HELP"

	exit 1
fi

ENVIRONMENT_VARIABLES=""
if [ -n "$TIME_ZONE" ]; then
	ENVIRONMENT_VARIABLES="--env TZ=$TIME_ZONE"
fi

setOperatingSystemAbstractionVariables

# If the container is not found a new one is prepared. Otherwise the existing
# container is used.
#
# The name filter must be specified as "^/XXX$" to get an exact match; using
# just "XXX" would match every name that contained "XXX".
if [ -z "$(docker ps --all --quiet --filter name="^/$CONTAINER$")" ]; then
	echo "Creating Talk recording container"
	# In Ubuntu 22.04 and later Firefox is installed as a snap package, which
	# does not work out of the box in a container. Therefore, for now Ubuntu
	# 20.04 is used instead.
	docker run --detach --tty --name=$CONTAINER --shm-size=$DEV_SHM_SIZE $ENVIRONMENT_VARIABLES $DOCKER_OPTIONS ubuntu:20.04 bash

	echo "Installing required Python modules"
	# "noninteractive" is used to provide default settings instead of asking for
	# them (for example, for tzdata).
	# Additional Python dependencies may be installed by pip if needed.
	docker exec $CONTAINER bash -c "apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install --assume-yes ffmpeg firefox pulseaudio python3-pip xvfb"

	echo "Adding user to run the recording backend"
	docker exec $CONTAINER useradd --create-home recording

	echo "Copying recording backend to the container"
	docker exec $CONTAINER mkdir --parent /tmp/recording/
	docker cp . $CONTAINER:/tmp/recording/

	echo "Installing recording backend inside container"
	docker exec $CONTAINER python3 -m pip install /tmp/recording/

	echo "Copying configuration from server.conf.in to /etc/nextcloud-talk-recording/server.conf"
	docker exec $CONTAINER mkdir --parent /etc/nextcloud-talk-recording/
	docker cp server.conf.in $CONTAINER:/etc/nextcloud-talk-recording/server.conf
elif $CUSTOM_CONTAINER_OPTIONS; then
	# Environment variables are excluded from this warning.
	echo "WARNING: Using existing container, custom container options ignored"
fi

# Start existing container if it is stopped.
if [ -n "$(docker ps --all --quiet --filter status=exited --filter name="^/$CONTAINER$")" ]; then
	echo "Starting Talk recording container"
	docker start $CONTAINER
fi

echo "Starting recording backend"
docker exec --tty --interactive --user recording $ENVIRONMENT_VARIABLES --workdir /home/recording $CONTAINER python3 -m nextcloud.talk.recording --config /etc/nextcloud-talk-recording/server.conf
