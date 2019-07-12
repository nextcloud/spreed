#!/bin/bash

cp "js/simplewebrtc/bundled.js" "js/simplewebrtc/bundled.js.back"

# Rebuild the bundled SimpleWebRTC
set -e
make bundle-simplewebrtc

echo "Comparing js/simplewebrtc/bundled.js to the commited version"
if ! diff -q "js/simplewebrtc/bundled.js" "js/simplewebrtc/bundled.js.back" &>/dev/null
then
    echo "js/simplewebrtc/bundled.js is NOT up-to-date! Please send the proper production build within the pull request"
    diff "js/simplewebrtc/bundled.js" "js/simplewebrtc/bundled.js.back"
    exit 2
fi

rm "js/simplewebrtc/bundled.js.back"
echo "SimpleWebRTC bundle is up-to-date"
