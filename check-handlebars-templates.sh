#!/usr/bin/env bash

REPODIR=`git rev-parse --show-toplevel`

cd $REPODIR

bash compile-handlebars-templates.sh || exit 1

if [[ $(git diff --name-only) ]]; then
    echo "Please submit your compiled handlebars templates"
    echo
    git diff
    exit 1
fi

echo "All up to date! Carry on :D"
exit 0
