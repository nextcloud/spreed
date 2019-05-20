#!/usr/bin/env bash

APP_NAME=spreed

APP_INTEGRATION_DIR=$PWD
ROOT_DIR=${APP_INTEGRATION_DIR}/../../../..
composer install

php -S localhost:8080 -t ${ROOT_DIR} &
PHPPID=$!
echo $PHPPID

cp -R ./spreedcheats ../../../spreedcheats
${ROOT_DIR}/occ app:enable spreed
${ROOT_DIR}/occ app:enable spreedcheats
${ROOT_DIR}/occ app:list | grep spreed

# "-- tags XXX" option can be provided to limit the tests run to those with the
# given Behat tags.
TAGS=""
if [ "$1" = "--tags" ]; then
	TAGS="--tags $2"

	shift 2
fi

export TEST_SERVER_URL="http://localhost:8080/"
${APP_INTEGRATION_DIR}/vendor/bin/behat -f junit -f pretty $TAGS $1 $2
RESULT=$?

kill $PHPPID

${ROOT_DIR}/occ app:disable spreedcheats
rm -rf ../../../spreedcheats

exit $RESULT
