#!/bin/bash

echo "========================="
echo "= List of changed files ="
echo "========================="
git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA
echo "========================="

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c ".php$") -gt 0 ]] && echo "PHP files are modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "^tests/acceptance/") -gt 0 ]] && echo "Acceptance test files are modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "^js/") -gt 0 ]] && echo "JS files are modified" && exit 0

exit 1
