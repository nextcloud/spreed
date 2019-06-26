#!/bin/bash

# @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
#
# @license GNU AGPL version 3 or any later version
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# Helper script to run the acceptance tests, which test a running Nextcloud
# Talk instance from the point of view of a real user.
#
# It simply calls the main "run.sh" script from the Nextcloud server setting a
# specific acceptance tests directory (the one for the Talk app), and as such it
# is expected that the grandparent directory of the root directory of the Talk
# app is the root directory of the Nextcloud server.

set -o errexit

# Ensure working directory is script directory, as it is expected when the
# script from the server is called.
cd "$(dirname $0)"

../../../../tests/acceptance/run.sh --acceptance-tests-dir apps/spreed/tests/acceptance/ "$@"
