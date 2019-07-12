#!/bin/bash
set -e

# Build the Vue files
make build-js-production

git status

bash -c "[[ ! \"`git status --porcelain js`\" ]] || ( echo 'Uncommitted changes in built Vue files' && exit 1 )"
