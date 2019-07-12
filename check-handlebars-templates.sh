#!/bin/bash
set -e

# Compile Handlebars templates
make compile-handlebars-templates

git status

bash -c "[[ ! \"`git status --porcelain js/views/templates.js`\" ]] || ( echo 'Uncommitted changes in compiled Handlebar templates' && exit 1 )"
