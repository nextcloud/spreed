#!/usr/bin/env bash

APP_NAME=spreed

APP_INTEGRATION_DIR=$PWD
ROOT_DIR=${APP_INTEGRATION_DIR}/../../../..
composer install

php -S localhost:8080 -t ${ROOT_DIR} &
PHPPID=$!
echo $PHPPID

cp -R ./spreedcheats ../../../spreedcheats
${ROOT_DIR}/occ app:enable spreed || exit 1
${ROOT_DIR}/occ app:enable spreedcheats || exit 1
${ROOT_DIR}/occ app:list | grep spreed

export TEST_SERVER_URL="http://localhost:8080/"
${APP_INTEGRATION_DIR}/vendor/bin/behat -f junit -f pretty $1 $2
RESULT=$?

kill $PHPPID

${ROOT_DIR}/occ app:disable spreedcheats
rm -rf ../../../spreedcheats

exit $RESULT
