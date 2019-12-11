#!/bin/sh
set -e

STYLELINT=$(which stylelint || true)
if [ -z "$STYLELINT" ]; then
    echo "Can't find command \"stylelint\" in $PATH"
    exit 1
fi

echo Checking stylesheets with $STYLELINT ...
$STYLELINT -f verbose css
