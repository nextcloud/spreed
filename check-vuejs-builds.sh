#!/bin/bash
set -e

# Build the Vue files
make build-js-production

git status

bash -c "[[ ! \"`git status --porcelain js`\" ]] || ( echo 'Uncommitted changes in built Vue files' && exit 1 )"

bash -c "[[ ! \"`git status --porcelain package-lock.json`\" ]] || ( git diff package-lock.json && echo 'Uncommitted changes in package-lock.json' && exit 1 )"

bash -c "[[ ! \"`git status --porcelain vue/package-lock.json`\" ]] || ( git diff vue/package-lock.json && echo 'Uncommitted changes in vue/package-lock.json' && exit 1 )"
