#!/bin/sh
set -e

STYLELINT=$(which stylelint || true)
if [ -z "$STYLELINT" ]; then
    echo "Can't find command \"stylelint\" in $PATH"
    exit 1
fi

echo Checking stylesheets with $STYLELINT ...
find css/ -name "*.css" -print0 | xargs -0 $STYLELINT
find css/ -name "*.scss" -print0 | xargs -0 $STYLELINT
