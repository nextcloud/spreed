#!/usr/bin/env bash

# Prefer the local handlebars script, and fall back to the global one.
export PATH=./node_modules/.bin/:$PATH

handlebars -n OCA.VideoCalls.Admin.Templates js/admin/templates/ -f js/admin/templates.js

handlebars -n OCA.Talk.Views.Templates js/views/templates/ -f js/views/templates.js
