#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

PROCESS_ID=$$

APP_NAME=spreed
NOTIFICATIONS_BRANCH="stable30"
GUESTS_BRANCH="main"
CIRCLES_BRANCH="stable30"

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

PORT_FED=8180
export PORT_FED

echo "" > phpserver_fed.log
PHP_CLI_SERVER_WORKERS=3 php -S localhost:${PORT_FED} -t ${ROOT_DIR} &> phpserver_fed.log &
PHPPID2=$!
echo -e "Running on process ID: \033[1;35m$PHPPID2\033[0m"

# Output filtered federated php server logs
tail -f phpserver_fed.log | grep --line-buffered -v -E ":[0-9]+ Accepted$" | grep --line-buffered -v -E ":[0-9]+ Closing$" &

MAIN_SERVER_CONFIG_DIR=${ROOT_DIR}/config
MAIN_SERVER_DATA_DIR=$(${ROOT_DIR}/occ config:system:get datadirectory)
MAIN_SERVER_APPS_PATHS=$(${ROOT_DIR}/occ config:system:get apps_paths --output json)
REAL_FEDERATED_SERVER_CONFIG_DIR="$MAIN_SERVER_DATA_DIR/tests-talk-real-federated-server/config"
REAL_FEDERATED_SERVER_DATA_DIR="$MAIN_SERVER_DATA_DIR/tests-talk-real-federated-server/data"
DESTROY_REAL_FEDERATED_SERVER=false

if [ ! -d "$REAL_FEDERATED_SERVER_CONFIG_DIR" ] || NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR" ${ROOT_DIR}/occ status | grep "installed: false"; then
	DESTROY_REAL_FEDERATED_SERVER=true
	if [ $CI ]; then
		DESTROY_REAL_FEDERATED_SERVER=false
	fi
	echo ''
	echo -e "\033[0;31mReal federated server not installed in $REAL_FEDERATED_SERVER_CONFIG_DIR\033[0m"
	echo -e "\033[0;33mPerforming basic SQLite installation with data directory in $REAL_FEDERATED_SERVER_DATA_DIR\033[0m"
	mkdir --parents "$REAL_FEDERATED_SERVER_CONFIG_DIR" "$REAL_FEDERATED_SERVER_DATA_DIR"
	NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR" ${ROOT_DIR}/occ maintenance:install --admin-pass=admin --data-dir="$REAL_FEDERATED_SERVER_DATA_DIR"
	echo ''
	if [ $MAIN_SERVER_APPS_PATHS ]; then
		echo -e "\033[0;33mCopying custom apps_paths\033[0m"
		echo "{\"system\":{\"apps_paths\":$MAIN_SERVER_APPS_PATHS}}" > "$REAL_FEDERATED_SERVER_CONFIG_DIR/apps_paths.json"
		NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR" ${ROOT_DIR}/occ config:import < "$REAL_FEDERATED_SERVER_CONFIG_DIR/apps_paths.json"
		echo ''
	fi
fi

PORT_FED_REAL=8280
export PORT_FED_REAL

echo "" > phpserver_fed_real.log
PHP_CLI_SERVER_WORKERS=3 NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR" php -S localhost:${PORT_FED_REAL} -t ${ROOT_DIR} &> phpserver_fed_real.log &
PHPPID3=$!
echo -e "Running on process ID: \033[1;35m$PHPPID3\033[0m"

# Output filtered real federated php server logs
tail -f phpserver_fed_real.log | grep --line-buffered -v -E ":[0-9]+ Accepted$" | grep --line-buffered -v -E ":[0-9]+ Closing$" &

# Kill all sub-processes in case of ctrl+c
trap 'pkill -P $PHPPID1; pkill -P $PHPPID2; pkill -P $PHPPID3; pkill -P $PROCESS_ID; wait $PHPPID1; wait $PHPPID2; wait $PHPPID3;' INT TERM

NEXTCLOUD_ROOT_DIR=${ROOT_DIR}
export NEXTCLOUD_ROOT_DIR
export TEST_SERVER_URL="http://localhost:8080/"
export TEST_LOCAL_REMOTE_URL="http://localhost:8180/"
export TEST_REMOTE_URL="http://localhost:8280/"
export MAIN_SERVER_CONFIG_DIR
export REAL_FEDERATED_SERVER_CONFIG_DIR

export NEXTCLOUD_CONFIG_DIR="$MAIN_SERVER_CONFIG_DIR"
MAIN_OVERWRITE_CLI_URL=$(${ROOT_DIR}/occ config:system:get overwrite.cli.url)
MAIN_SKELETON_DIR=$(${ROOT_DIR}/occ config:system:get skeletondirectory)
${ROOT_DIR}/occ config:system:set overwrite.cli.url --value "http://localhost:8080/"
if [[ "$MAIN_SKELETON_DIR" != "" ]]; then
	echo "Resetting custom skeletondirectory so that tests pass"
	${ROOT_DIR}/occ config:system:delete skeletondirectory
fi

export NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR"
REAL_FEDERATED_OVERWRITE_CLI_URL=$(${ROOT_DIR}/occ config:system:get overwrite.cli.url)
REAL_FEDERATED_SKELETON_DIR=$(${ROOT_DIR}/occ config:system:get skeletondirectory)
${ROOT_DIR}/occ config:system:set overwrite.cli.url --value "$TEST_REMOTE_URL"
if [[ "$REAL_FEDERATED_SKELETON_DIR" != "" ]]; then
	echo "Resetting custom skeletondirectory so that tests pass"
	${ROOT_DIR}/occ config:system:delete skeletondirectory
fi

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Setting up apps\033[0m"
echo -e "\033[0;36m#\033[0m"
cp -R ./spreedcheats ../../../spreedcheats
${ROOT_DIR}/occ app:getpath spreedcheats
cp -R ./talk_webhook_demo ../../../talk_webhook_demo
${ROOT_DIR}/occ app:getpath talk_webhook_demo

# Add apps to the parent directory of "spreed" (unless they are
# already there or in "apps").
${ROOT_DIR}/occ app:getpath notifications || (cd ../../../ && git clone --depth 1 --branch ${NOTIFICATIONS_BRANCH} https://github.com/nextcloud/notifications)
${ROOT_DIR}/occ app:getpath guests || (cd ../../../ && git clone --depth 1 --branch ${GUESTS_BRANCH} https://github.com/nextcloud/guests)
${ROOT_DIR}/occ app:getpath circles || (cd ../../../ && git clone --depth 1 --branch ${CIRCLES_BRANCH} https://github.com/nextcloud/circles)
${ROOT_DIR}/occ app:getpath call_summary_bot || (cd ../../../ && git clone --depth 1 --branch ${CSB_BRANCH} https://github.com/nextcloud/call_summary_bot)

for CONFIG_DIR in $MAIN_SERVER_CONFIG_DIR $REAL_FEDERATED_SERVER_CONFIG_DIR; do
	export NEXTCLOUD_CONFIG_DIR="$CONFIG_DIR"

	${ROOT_DIR}/occ app:enable spreed || exit 1
	${ROOT_DIR}/occ app:enable --force spreedcheats || exit 1
	${ROOT_DIR}/occ app:enable --force talk_webhook_demo || exit 1
	${ROOT_DIR}/occ app:enable --force notifications || exit 1
	${ROOT_DIR}/occ app:enable --force guests || exit 1
	${ROOT_DIR}/occ app:enable --force circles || exit 1

	${ROOT_DIR}/occ app:list | grep spreed
	${ROOT_DIR}/occ app:list | grep talk_webhook_demo
	${ROOT_DIR}/occ app:list | grep notifications
	${ROOT_DIR}/occ app:list | grep guests
	${ROOT_DIR}/occ app:list | grep circles
done

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Optimizing configuration\033[0m"
echo -e "\033[0;36m#\033[0m"
for CONFIG_DIR in $MAIN_SERVER_CONFIG_DIR $REAL_FEDERATED_SERVER_CONFIG_DIR; do
	export NEXTCLOUD_CONFIG_DIR="$CONFIG_DIR"

	# Disable bruteforce protection because the integration tests do trigger them
	${ROOT_DIR}/occ config:system:set auth.bruteforce.protection.enabled --value false --type bool
	# Disable rate limit protection because the integration tests do trigger them
	${ROOT_DIR}/occ config:system:set ratelimit.protection.enabled --value false --type bool
	# Allow local remote urls otherwise we can not share
	${ROOT_DIR}/occ config:system:set allow_local_remote_servers --value true --type bool
	# Enable debug mode as it is required to enable developer commands
	${ROOT_DIR}/occ config:system:set debug --value true --type bool
	# Use faster password hashing
	${ROOT_DIR}/occ config:system:set hashing_default_password --value=true --type=bool
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
${APP_INTEGRATION_DIR}/vendor/bin/behat --colors -f junit -f pretty $1 $2
RESULT=$?

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Stopping PHP webserver\033[0m"
echo -e "\033[0;36m#\033[0m"

# Kill child PHP processes
pkill -P $PHPPID1;
pkill -P $PHPPID2;
pkill -P $PHPPID3;

# Kill parent PHP processes
kill -TERM $PHPPID1;
kill -TERM $PHPPID2;
kill -TERM $PHPPID3;

# Kill child processes of this script (e.g. tail)
pkill -P $PROCESS_ID;

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Reverting configuration changes and disabling spreedcheats\033[0m"
echo -e "\033[0;36m#\033[0m"

# Main server
export NEXTCLOUD_CONFIG_DIR="$MAIN_SERVER_CONFIG_DIR"
${ROOT_DIR}/occ app:disable spreedcheats
${ROOT_DIR}/occ app:disable talk_webhook_demo
${ROOT_DIR}/occ config:system:set overwrite.cli.url --value "$MAIN_OVERWRITE_CLI_URL"
if [[ "$MAIN_SKELETON_DIR" != "" ]]; then
	${ROOT_DIR}/occ config:system:set skeletondirectory --value "$MAIN_SKELETON_DIR"
fi

# Real federated server
if $DESTROY_REAL_FEDERATED_SERVER; then
	rm -rf "$REAL_FEDERATED_SERVER_CONFIG_DIR" "$REAL_FEDERATED_SERVER_DATA_DIR"
else
	export NEXTCLOUD_CONFIG_DIR="$REAL_FEDERATED_SERVER_CONFIG_DIR"
	${ROOT_DIR}/occ app:disable spreedcheats
	${ROOT_DIR}/occ app:disable talk_webhook_demo
	${ROOT_DIR}/occ config:system:set overwrite.cli.url --value "$REAL_FEDERATED_OVERWRITE_CLI_URL"
	if [[ "$REAL_FEDERATED_SKELETON_DIR" != "" ]]; then
		${ROOT_DIR}/occ config:system:set skeletondirectory --value "$REAL_FEDERATED_SKELETON_DIR"
	fi
fi

rm -rf ../../../spreedcheats
rm -rf ../../../talk_webhook_demo

wait $PHPPID1
wait $PHPPID2
wait $PHPPID3

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Gracefully completed\033[0m"
echo -e "\033[0;36m#\033[0m"

exit $RESULT
