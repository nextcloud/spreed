#!/bin/bash
set -e

# Rebuild the bundled SimpleWebRTC
make bundle-simplewebrtc

git status

bash -c "[[ ! \"`git status --porcelain js/simplewebrtc/bundled.js`\" ]] || ( echo 'Uncommitted changes in bundled SimpleWebRTC' && exit 1 )"
