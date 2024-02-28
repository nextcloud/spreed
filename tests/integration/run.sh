#!/usr/bin/env bash

PROCESS_ID=$$

APP_NAME=spreed
NOTIFICATIONS_BRANCH="master"
GUESTS_BRANCH="master"
CSB_BRANCH="main"

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

# The federated server is started and stopped by the tests themselves
PORT_FED=8180
export PORT_FED

echo "" > phpserver_fed.log
PHP_CLI_SERVER_WORKERS=3 php -S localhost:${PORT_FED} -t ${ROOT_DIR} &> phpserver_fed.log &
PHPPID2=$!
echo -e "Running on process ID: \033[1;35m$PHPPID2\033[0m"

# Output filtered federated php server logs
tail -f phpserver_fed.log | grep --line-buffered -v -E ":[0-9]+ Accepted$" | grep --line-buffered -v -E ":[0-9]+ Closing$" &

# Kill all sub-processes in case of ctrl+c
trap 'pkill -P $PHPPID1; pkill -P $PHPPID2; pkill -P $PROCESS_ID; wait $PHPPID1; wait $PHPPID2;' INT TERM

NEXTCLOUD_ROOT_DIR=${ROOT_DIR}
export NEXTCLOUD_ROOT_DIR
export TEST_SERVER_URL="http://localhost:8080/"
export TEST_REMOTE_URL="http://localhost:8180/"

OVERWRITE_CLI_URL=$(${ROOT_DIR}/occ config:system:get overwrite.cli.url)
${ROOT_DIR}/occ config:system:set overwrite.cli.url --value "http://localhost:8080/"

SKELETON_DIR=$(${ROOT_DIR}/occ config:system:get skeletondirectory)
if [[ "$SKELETON_DIR" ]]; then
	echo "Resetting custom skeletondirectory so that tests pass"
	${ROOT_DIR}/occ config:system:delete skeletondirectory
fi

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Setting up apps\033[0m"
echo -e "\033[0;36m#\033[0m"
cp -R ./spreedcheats ../../../spreedcheats
${ROOT_DIR}/occ app:getpath spreedcheats

# Add apps to the parent directory of "spreed" (unless they are
# already there or in "apps").
${ROOT_DIR}/occ app:getpath notifications || (cd ../../../ && git clone --depth 1 --branch ${NOTIFICATIONS_BRANCH} https://github.com/nextcloud/notifications)
${ROOT_DIR}/occ app:getpath guests || (cd ../../../ && git clone --depth 1 --branch ${GUESTS_BRANCH} https://github.com/nextcloud/guests)
${ROOT_DIR}/occ app:getpath call_summary_bot || (cd ../../../ && git clone --depth 1 --branch ${CSB_BRANCH} https://github.com/nextcloud/call_summary_bot)

${ROOT_DIR}/occ app:enable spreed || exit 1
${ROOT_DIR}/occ app:enable --force spreedcheats || exit 1
${ROOT_DIR}/occ app:enable --force notifications || exit 1
${ROOT_DIR}/occ app:enable --force guests || exit 1
${ROOT_DIR}/occ app:enable --force call_summary_bot || exit 1

${ROOT_DIR}/occ app:list | grep spreed
${ROOT_DIR}/occ app:list | grep notifications
${ROOT_DIR}/occ app:list | grep guests
${ROOT_DIR}/occ app:list | grep call_summary_bot

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Optimizing configuration\033[0m"
echo -e "\033[0;36m#\033[0m"
# Disable bruteforce protection because the integration tests do trigger them
${ROOT_DIR}/occ config:system:set auth.bruteforce.protection.enabled --value false --type bool
# Disable rate limit protection because the integration tests do trigger them
${ROOT_DIR}/occ config:system:set ratelimit.protection.enabled --value false --type bool
# Allow local remote urls otherwise we can not share
${ROOT_DIR}/occ config:system:set allow_local_remote_servers --value true --type bool

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

# Kill parent PHP processes
kill -TERM $PHPPID1;
kill -TERM $PHPPID2;

# Kill child processes of this script (e.g. tail)
pkill -P $PROCESS_ID;

echo ''
echo -e "\033[0;36m#\033[0m"
echo -e "\033[0;36m# Reverting configuration changes and disabling spreedcheats\033[0m"
echo -e "\033[0;36m#\033[0m"
${ROOT_DIR}/occ app:disable spreedcheats
${ROOT_DIR}/occ config:system:set overwrite.cli.url --value $OVERWRITE_CLI_URL
if [[ "$SKELETON_DIR" ]]; then
	${ROOT_DIR}/occ config:system:set skeletondirectory --value "$SKELETON_DIR"
fi
rm -rf ../../../spreedcheats

wait $PHPPID1
wait $PHPPID2

exit $RESULT
