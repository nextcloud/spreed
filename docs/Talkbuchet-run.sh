#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Helper script to run Talkbuchet, the helper tool for load/stress testing of
# Nextcloud Talk.
#
# Talkbuchet is a JavaScript script (Talkbuchet.js), and it is run using a web
# browser. A Python script (Talkbuchet-cli.py) is provided to launch a web
# browser, load Talkbuchet and control it from a command line interface (which
# requires Selenium and certain Python packages to be available in the system).
# A Bash script (Talkbuchet-run.sh) is provided to set up a Docker container
# with Selenium, a web browser and all the needed Python dependencies for
# Talkbuchet-cli.py.
#
# Please refer to the documentation in Talkbuchet.js and Talkbuchet-cli.py for
# information on Talkbuchet and how to control it.
#
# Talkbuchet-run.sh creates a Selenium container, installs all the needed
# dependencies in it and executes Talkbuchet-cli.py inside the container. The
# command line interface will automatically use the Selenium server from the
# container, so as soon as the command line interface is shown Talkbuchet is
# ready to be used. If the container exists already the previous container will
# be reused and Talkbuchet-run.sh will simply execute Talkbuchet-cli.py in it.
#
# Due to that the Docker container will not be stopped nor removed when the
# script exits (except when the container was created but it could not be
# started); that must be explicitly done once the container is no longer needed.
# If the Selenium container can not be started then the script will be exited
# immediately with an error state; the most common cause for the Selenium
# container to fail to start is that another process is already using the mapped
# ports in the host.
#
# As the web browsers are run inside the Docker container they are not visible
# by default. However, they can be viewed using VNC (for example,
# "vncviewer 127.0.0.1:5900"). The Selenium Docker images also support web VNC,
# so the web browsers can also be viewed navigating to "http://127.0.0.1:7900"
# in a web browser outside the Docker container. In both cases, when asked for
# the password use "secret".
#
# Besides that, by default Talkbuchet-cli.py starts the web browsers in headless
# mode, so "setHeadless(False)" should be called in the command line interface
# before starting a web browser to be able to view it.
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
# Talkbuchet to the container) expect that.
cd "$(dirname $0)"

HELP="Usage: $(basename $0) [OPTION]...

Options (all options can be omitted, but when present they must appear in the
following order):
--help prints this help and exits.
--container CONTAINER_NAME the name to assign to the container. Defaults to
  talkbuchet-selenium, talkbuchet-selenium-chrome or talkbuchet-selenium-firefox
  depending on the image options.
--selenium-image SELENIUM_IMAGE or --chrome or --firefox:
  --selenium-image SELENIUM_IMAGE the name of the Selenium image to use. No
    matter the image, the default container name will be talkbuchet-selenium. If
    no specific image is given a Firefox image will be used by default (and
    talkbuchet-selenium-firefox will be used as the default container name).
  --chrome use a Selenium image with Chrome.
  --firefox use a Selenium image with Firefox.
--vnc-port PORT_NUMBER the port used to map the VNC server in the host. Defaults
  to 5900.
--web-vnc-port PORT_NUMBER the port used to map the web VNC server in the host.
  Defaults to 7900.
--dev-shm-size SIZE the size to assign to /dev/shm in the Docker container.
  Defaults to 2g"
if [ "$1" = "--help" ]; then
	echo "$HELP"

	exit 0
fi

CUSTOM_CONTAINER_NAME=false
CONTAINER="talkbuchet-selenium-firefox"
if [ "$1" = "--container" ]; then
	CONTAINER="$2"
	CUSTOM_CONTAINER_NAME=true

	shift 2
fi

CUSTOM_CONTAINER_OPTIONS=false

SELENIUM_IMAGE="selenium/standalone-firefox:99.0-20220427"
if [ "$1" = "--selenium-image" ]; then
	SELENIUM_IMAGE="$2"
	CUSTOM_CONTAINER_OPTIONS=true

	if ! $CUSTOM_CONTAINER_NAME; then
		CONTAINER="talkbuchet-selenium"
	fi

	shift 2
elif [ "$1" = "--chrome" ]; then
	SELENIUM_IMAGE="selenium/standalone-chrome:101.0-20220427"

	if $CUSTOM_CONTAINER_NAME; then
		CUSTOM_CONTAINER_OPTIONS=true
	else
		CONTAINER="talkbuchet-selenium-chrome"
	fi

	shift 1
elif [ "$1" = "--firefox" ]; then
	if $CUSTOM_CONTAINER_NAME; then
		CUSTOM_CONTAINER_OPTIONS=true
	fi

	shift 1
fi

VNC_PORT="5900"
if [ "$1" = "--vnc-port" ]; then
	VNC_PORT="$2"
	CUSTOM_CONTAINER_OPTIONS=true

	shift 2
fi

WEB_VNC_PORT="7900"
if [ "$1" = "--web-vnc-port" ]; then
	WEB_VNC_PORT="$2"
	CUSTOM_CONTAINER_OPTIONS=true

	shift 2
fi

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

setOperatingSystemAbstractionVariables

# If the container is not found a new one is prepared. Otherwise the existing
# container is used.
#
# The name filter must be specified as "^/XXX$" to get an exact match; using
# just "XXX" would match every name that contained "XXX".
if [ -z "$(docker ps --all --quiet --filter name="^/$CONTAINER$")" ]; then
	echo "Creating Selenium container"
	docker run --detach --name=$CONTAINER --publish $VNC_PORT:5900 --publish $WEB_VNC_PORT:7900 --shm-size=$DEV_SHM_SIZE $DOCKER_OPTIONS $SELENIUM_IMAGE

	echo "Installing required Python modules"
	docker exec --user root $CONTAINER bash -c "apt-get update && apt-get install --assume-yes python3-pip && pip install selenium websocket-client"

	echo "Copying Talkbuchet to the container"
	docker cp Talkbuchet.js $CONTAINER:/tmp/
	docker cp Talkbuchet-cli.py $CONTAINER:/tmp/
elif $CUSTOM_CONTAINER_OPTIONS; then
	echo "WARNING: Using existing container, custom container options ignored"
fi

# Start existing container if it is stopped.
if [ -n "$(docker ps --all --quiet --filter status=exited --filter name="^/$CONTAINER$")" ]; then
	echo "Starting Selenium container"
	docker start $CONTAINER
fi

echo "Starting Talkbuchet CLI"
docker exec --tty --interactive --workdir /tmp $CONTAINER python3 -i /tmp/Talkbuchet-cli.py
