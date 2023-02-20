#
# @copyright Copyright (c) 2023, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
#

build-package-deb:
	python3 -m pip install "setuptools >= 61.0"
	# Since the 60.0.0 release, Setuptools includes a local, vendored copy of
	# distutils; this copy does not seem to work with stdeb, so it needs to be
	# disabled with "SETUPTOOLS_USE_DISTUTILS=stdlib".
	SETUPTOOLS_USE_DISTUTILS=stdlib python3 setup.py --command-packages=stdeb.command bdist_deb
