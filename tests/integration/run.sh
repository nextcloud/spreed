#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

PROCESS_ID=$$

APP_NAME=spreed
NOTIFICATIONS_BRANCH="master"
GUESTS_BRANCH="main"
CIRCLES_BRANCH="master"

APP_INTEGRATION_DIR=$PWD
ROOT_DIR=${APP_INTEGRATION_DIR}/../../../..
echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Installing composer dependencies from tests/integration/\033[0m"
echo -e "\033[0;36m#\033[0m"
composer install

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Starting PHP webserver\033[0m"
echo -e "\033[0;36m#\033[0m"

echo "" > phpserver.log
PHP_CLI_SERVER_WORKERS=3 php -S localhost:8080 -t ${ROOT_DIR} &> phpserver.log &
PHPPID1=$!
echo -e "Running on process ID: \033[1;35m$PHPPID1\033[0m"

# Output filtered php server logs
tail -f phpserver.log | grep --line-buffered -v -E ":[0-9]+ Accepted$" | grep --line-buffered -v -E ":[0-9]+ Closing$" &

occ_host() {
	NEXTCLOUD_CONFIG_DIR=${MAIN_SERVER_CONFIG_DIR} ${ROOT_DIR}/occ "$@"
}

occ_remote() {
	NEXTCLOUD_CONFIG_DIR=${REAL_FEDERATED_SERVER_CONFIG_DIR} ${REMOTE_ROOT_DIR}/occ "$@"
}

MAIN_SERVER_CONFIG_DIR=${ROOT_DIR}/config
MAIN_SERVER_DATA_DIR=$(occ_host config:system:get datadirectory)
MAIN_SERVER_APPS_PATHS=$(occ_host config:system:get apps_paths --output json)

if [ $REMOTE_ROOT_DIR ]; then
	REAL_FEDERATED_SERVER_CONFIG_DIR="$REMOTE_ROOT_DIR/config"
	REAL_FEDERATED_SERVER_DATA_DIR="$REMOTE_ROOT_DIR/data"
	DESTROY_REAL_FEDERATED_SERVER=false
else
	echo ''
	echo -e "\033[0;36m#\033[0m"
	echo -e "\033[0;36m# Setting up real federated server\033[0m"
	echo -e "\033[0;36m#\033[0m"
	REMOTE_ROOT_DIR=$ROOT_DIR
	REAL_FEDERATED_SERVER_CONFIG_DIR="$MAIN_SERVER_DATA_DIR/tests-talk-real-federated-server/config"
	REAL_FEDERATED_SERVER_DATA_DIR="$MAIN_SERVER_DATA_DIR/tests-talk-real-federated-server/data"
	DESTROY_REAL_FEDERATED_SERVER=false

	if [ ! -d "$REAL_FEDERATED_SERVER_CONFIG_DIR" ] || occ_remote status | grep "installed: false"; then
		DESTROY_REAL_FEDERATED_SERVER=true
		if [ $CI ]; then
			DESTROY_REAL_FEDERATED_SERVER=false
		fi
		echo ''
		echo -e "\033[0;31mReal federated server not installed in $REAL_FEDERATED_SERVER_CONFIG_DIR\033[0m"
		echo -e "\033[0;33mPerforming basic SQLite installation with data directory in $REAL_FEDERATED_SERVER_DATA_DIR\033[0m"
		mkdir --parents "$REAL_FEDERATED_SERVER_CONFIG_DIR" "$REAL_FEDERATED_SERVER_DATA_DIR"
		occ_remote maintenance:install --admin-pass=admin --data-dir="$REAL_FEDERATED_SERVER_DATA_DIR"
		echo ''
		if [ $MAIN_SERVER_APPS_PATHS ]; then
			echo -e "\033[0;33mCopying custom apps_paths\033[0m"
			echo "{\"system\":{\"apps_paths\":$MAIN_SERVER_APPS_PATHS}}" > "$REAL_FEDERATED_SERVER_CONFIG_DIR/apps_paths.json"
			occ_remote config:import < "$REAL_FEDERATED_SERVER_CONFIG_DIR/apps_paths.json"
			echo ''
		fi
	fi
fi

PORT_FED_REAL=8280
export PORT_FED_REAL

echo "" > phpserver_fed_real.log
PHP_CLI_SERVER_WORKERS=3 NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR" php -S localhost:${PORT_FED_REAL} -t ${REMOTE_ROOT_DIR} &> phpserver_fed_real.log &
PHPPID3=$!
echo -e "Running on process ID: \033[1;35m$PHPPID3\033[0m"

# Output filtered real federated php server logs
tail -f phpserver_fed_real.log | grep --line-buffered -v -E ":[0-9]+ Accepted$" | grep --line-buffered -v -E ":[0-9]+ Closing$" &

# Kill all sub-processes in case of ctrl+c
trap 'pkill -P $PHPPID1; pkill -P $PHPPID3; pkill -P $PROCESS_ID; wait $PHPPID1; wait $PHPPID3;' INT TERM

export NEXTCLOUD_HOST_ROOT_DIR=${ROOT_DIR}
export NEXTCLOUD_HOST_CONFIG_DIR=${MAIN_SERVER_CONFIG_DIR}
export NEXTCLOUD_REMOTE_ROOT_DIR=${REMOTE_ROOT_DIR}
export NEXTCLOUD_REMOTE_CONFIG_DIR=${REAL_FEDERATED_SERVER_CONFIG_DIR}
export TEST_SERVER_URL="http://localhost:8080/"
export TEST_REMOTE_URL="http://localhost:8280/"

export NEXTCLOUD_CONFIG_DIR="$MAIN_SERVER_CONFIG_DIR"
MAIN_OVERWRITE_CLI_URL=$(occ_host config:system:get overwrite.cli.url)
MAIN_SKELETON_DIR=$(occ_host config:system:get skeletondirectory)
occ_host config:system:set overwrite.cli.url --value "http://localhost:8080/"
occ_host config:app:set dav enableDefaultContact --value false --type boolean
if [[ "$MAIN_SKELETON_DIR" != "" ]]; then
	echo "Resetting custom skeletondirectory so that tests pass"
	occ_host config:system:delete skeletondirectory
fi

REAL_FEDERATED_OVERWRITE_CLI_URL=$(occ_remote config:system:get overwrite.cli.url)
REAL_FEDERATED_SKELETON_DIR=$(occ_remote config:system:get skeletondirectory)
occ_remote config:system:set overwrite.cli.url --value "$TEST_REMOTE_URL"
occ_remote config:app:set dav enableDefaultContact --value false --type boolean
if [[ "$REAL_FEDERATED_SKELETON_DIR" != "" ]]; then
	echo "Resetting custom skeletondirectory so that tests pass"
	occ_remote config:system:delete skeletondirectory
fi

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Setting up apps\033[0m"
echo -e "\033[0;36m#\033[0m"
cp -R ./spreedcheats ../../../spreedcheats
occ_host app:getpath spreedcheats
cp -R ./talk_webhook_demo ../../../talk_webhook_demo
occ_host app:getpath talk_webhook_demo

REMOTE_SPREED_DIR=$(occ_remote app:getpath spreed)
if [ ! -d ${REMOTE_ROOT_DIR}/apps/spreedcheats ]; then
	cp -R ${REMOTE_SPREED_DIR}/tests/integration/spreedcheats ${REMOTE_ROOT_DIR}/apps/spreedcheats
fi
if [ ! -d ${REMOTE_ROOT_DIR}/apps/talk_webhook_demo ]; then
	cp -R ${REMOTE_SPREED_DIR}/tests/integration/talk_webhook_demo ${REMOTE_ROOT_DIR}/apps/talk_webhook_demo
fi

# Add apps to the parent directory of "spreed" (unless they are
# already there or in "apps").
occ_host app:getpath notifications || (cd ../../../ && git clone --depth 1 --branch ${NOTIFICATIONS_BRANCH} https://github.com/nextcloud/notifications)
occ_host app:getpath guests || (cd ../../../ && git clone --depth 1 --branch ${GUESTS_BRANCH} https://github.com/nextcloud/guests)
occ_host app:getpath circles || (cd ../../../ && git clone --depth 1 --branch ${CIRCLES_BRANCH} https://github.com/nextcloud/circles)

for OCC in occ_host occ_remote; do
	${OCC} app:enable spreed || exit 1
	${OCC} app:enable --force spreedcheats || exit 1
	${OCC} app:enable --force talk_webhook_demo || exit 1
	${OCC} app:enable --force notifications || exit 1
	${OCC} app:enable --force guests || exit 1
	${OCC} app:enable --force circles || exit 1

	${OCC} app:list | grep spreed
	${OCC} app:list | grep talk_webhook_demo
	${OCC} app:list | grep notifications
	${OCC} app:list | grep guests
	${OCC} app:list | grep circles
done

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Optimizing configuration\033[0m"
echo -e "\033[0;36m#\033[0m"

EXCLUDE_TAGS=''
for OCC in occ_host occ_remote; do
	# Disable bruteforce protection because the integration tests do trigger them
	${OCC} config:system:set auth.bruteforce.protection.enabled --value false --type bool
	# Disable rate limit protection because the integration tests do trigger them
	${OCC} config:system:set ratelimit.protection.enabled --value false --type bool
	# Allow local remote urls otherwise we can not share
	${OCC} config:system:set allow_local_remote_servers --value true --type bool
	# Enable debug mode as it is required to enable developer commands
	${OCC} config:system:set debug --value true --type bool
	# Use faster password hashing
	${OCC} config:system:set hashing_default_password --value=true --type=bool

	# Build skip list
	MAJOR_VERSION=$(${OCC} status | grep -Eo 'version: ([0-9]+).' | grep -Eo '[0-9]+')
	EXCLUDE_TAGS="${EXCLUDE_TAGS} --tags=~skip${MAJOR_VERSION}"
done

# Restore default config dir to local server in case it is used from the tests
export NEXTCLOUD_CONFIG_DIR="$MAIN_SERVER_CONFIG_DIR"

echo ''
echo -e "\033[1;33m#\033[0m"
echo -e "\033[1;33m# ██████╗ ██╗   ██╗███╗   ██╗    ████████╗███████╗███████╗████████╗███████╗\033[0m"
echo -e "\033[1;33m# ██╔══██╗██║   ██║████╗  ██║    ╚══██╔══╝██╔════╝██╔════╝╚══██╔══╝██╔════╝\033[0m"
echo -e "\033[1;33m# ██████╔╝██║   ██║██╔██╗ ██║       ██║   █████╗  ███████╗   ██║   ███████╗\033[0m"
echo -e "\033[1;33m# ██╔══██╗██║   ██║██║╚██╗██║       ██║   ██╔══╝  ╚════██║   ██║   ╚════██║\033[0m"
echo -e "\033[1;33m# ██║  ██║╚██████╔╝██║ ╚████║       ██║   ███████╗███████║   ██║   ███████║\033[0m"
echo -e "\033[1;33m# ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═══╝       ╚═╝   ╚══════╝╚══════╝   ╚═╝   ╚══════╝\033[0m"
echo -e "\033[1;33m#\033[0m"
echo ${APP_INTEGRATION_DIR}/vendor/bin/behat --colors -f junit -f pretty ${EXCLUDE_TAGS} $1 $2
${APP_INTEGRATION_DIR}/vendor/bin/behat --colors -f junit -f pretty ${EXCLUDE_TAGS} $1 $2
RESULT=$?

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Stopping PHP webserver\033[0m"
echo -e "\033[0;36m#\033[0m"

# Kill child PHP processes
pkill -P $PHPPID1;
pkill -P $PHPPID3;

# Kill parent PHP processes
kill -TERM $PHPPID1;
kill -TERM $PHPPID3;

# Kill child processes of this script (e.g. tail)
pkill -P $PROCESS_ID;

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Reverting configuration changes and disabling spreedcheats\033[0m"
echo -e "\033[0;36m#\033[0m"

# Main server
export NEXTCLOUD_CONFIG_DIR="$MAIN_SERVER_CONFIG_DIR"
occ_host app:disable spreedcheats
occ_host app:disable talk_webhook_demo
occ_host config:system:set overwrite.cli.url --value "$MAIN_OVERWRITE_CLI_URL"
if [[ "$MAIN_SKELETON_DIR" != "" ]]; then
	occ_host config:system:set skeletondirectory --value "$MAIN_SKELETON_DIR"
fi

# Real federated server
if $DESTROY_REAL_FEDERATED_SERVER; then
	rm -rf "$REAL_FEDERATED_SERVER_CONFIG_DIR" "$REAL_FEDERATED_SERVER_DATA_DIR"
else
	export NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR"
	occ_remote app:disable spreedcheats
	occ_remote app:disable talk_webhook_demo
	occ_remote config:system:set overwrite.cli.url --value "$REAL_FEDERATED_OVERWRITE_CLI_URL"
	if [[ "$REAL_FEDERATED_SKELETON_DIR" != "" ]]; then
		occ_remote config:system:set skeletondirectory --value "$REAL_FEDERATED_SKELETON_DIR"
	fi
fi

rm -rf ../../../spreedcheats
rm -rf ../../../talk_webhook_demo
rm -rf ${REMOTE_ROOT_DIR}/apps/spreedcheats
rm -rf ${REMOTE_ROOT_DIR}/apps/talk_webhook_demo

wait $PHPPID1
wait $PHPPID3

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Gracefully completed\033[0m"
echo -e "\033[0;36m#\033[0m"

exit $RESULT
