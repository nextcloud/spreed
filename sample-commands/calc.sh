#!/usr/bin/env bash

CALCULATOR=$(which "bc")
if ! [ -x "$CALCULATOR" ]; then
  echo "Basic calculator package (bc - https://www.gnu.org/software/bc/) not found"
  exit 1
fi

while test $# -gt 0; do
  case "$1" in
    --help)
      echo "/calc - A basic calculator for Nextcloud Talk based on gnu BC"
      echo "See the official documentation for more information:"
      echo "https://www.gnu.org/software/bc/manual/html_mono/bc.html"
      echo " "
      echo "Simple equations: /calc 3 + 4 * 5"
      echo "Complex equations: /calc sin(3) + 3^3 * sqrt(5)"
      exit 0
      ;;
    *)
      break
      ;;
 esac
done

set -f
echo "$@ ="
echo $(echo "$@" | bc)
