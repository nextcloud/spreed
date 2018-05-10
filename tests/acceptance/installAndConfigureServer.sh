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

# Helper script to install and configure the Nextcloud server as expected by the
# acceptance tests.
#
# This script is not meant to be called manually; it is called when needed by
# the acceptance tests launchers.
#
# It simply extends the default script by enabling the Talk app so it is already
# available when the acceptance tests are run. It also adds other users to test
# with.

set -o errexit

tests/acceptance/installAndConfigureServer.sh "$@"

php occ app:enable spreed

OC_PASS=123456acb php occ user:add --password-from-env talk-user0
