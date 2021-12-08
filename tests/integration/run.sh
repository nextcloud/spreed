#!/usr/bin/env bash

APP_NAME=spreed

APP_INTEGRATION_DIR=$PWD
ROOT_DIR=${APP_INTEGRATION_DIR}/../../../..
composer install

php -S localhost:8080 -t ${ROOT_DIR} &
PHPPID=$!
echo $PHPPID

# The federated server is started and stopped by the tests themselves
PORT_FED=8180
export PORT_FED
NEXTCLOUD_ROOT_DIR=${ROOT_DIR}
export NEXTCLOUD_ROOT_DIR

# also kill php process in case of ctrl+c
trap 'kill -TERM $PHPPID; wait $PHPPID' TERM

cp -R ./spreedcheats ../../../spreedcheats
${ROOT_DIR}/occ app:enable spreed || exit 1
${ROOT_DIR}/occ app:enable spreedcheats || exit 1
${ROOT_DIR}/occ app:list | grep spreed

export TEST_SERVER_URL="http://localhost:8080/"
export TEST_REMOTE_URL="http://localhost:8180/"
${APP_INTEGRATION_DIR}/vendor/bin/behat -f junit -f pretty $1 $2
RESULT=$?

kill $PHPPID

${ROOT_DIR}/occ app:disable spreedcheats
rm -rf ../../../spreedcheats

wait $PHPPID

exit $RESULT
