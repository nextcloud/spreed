#!/bin/bash

echo "========================="
echo "= List of changed files ="
echo "========================="
git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA
echo "========================="

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | wc -l) -eq 0 ]] && echo "No files are modified => merge commit" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "\.drone\.yml") -gt 0 ]] && echo ".drone.yml is modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "appinfo/info\.xml") -gt 0 ]] && echo "info.xml is modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep --invert-match "^tests/" | grep -c ".php$") -gt 0 ]] && echo "PHP files are modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "^tests/acceptance/") -gt 0 ]] && echo "Acceptance test files are modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "^js/") -gt 0 ]] && echo "JS files are modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "^css/") -gt 0 ]] && echo "CSS files are modified" && exit 0

exit 1
