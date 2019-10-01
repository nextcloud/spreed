#!/usr/bin/env bash

# Prefer the local handlebars script, and fall back to the global one.
export PATH=./node_modules/.bin/:$PATH

node node_modules/handlebars/bin/handlebars -n OCA.Talk.Views.Templates js/views/templates/ -f js/views/templates.js

echo "OK"
