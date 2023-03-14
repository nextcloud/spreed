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

NEXTCLOUD_TALK_RECORDING_VERSION := $(shell cd ../src && python3 -c "from nextcloud.talk.recording import __version__; print(__version__)")

build-package-deb:
	python3 -m pip install "setuptools >= 61.0"

	# Build a source Python package
	python3 -m build --sdist --outdir . ../

	# Extract source Python package
	tar --extract --gzip --file nextcloud-talk-recording-$(NEXTCLOUD_TALK_RECORDING_VERSION).tar.gz

	# Add extra files needed to create Debian packages:
	# - setup.py: legacy setup file needed for stdeb (also needed in the
	#   regenerated Python package, as stdeb is invoked through it to create the
	#   source Debian package).
	cp setup.py nextcloud-talk-recording-$(NEXTCLOUD_TALK_RECORDING_VERSION)/

	# Build a source Debian package and then, from it, a binary Debian package
	# Since the 60.0.0 release, Setuptools includes a local, vendored copy of
	# distutils; this copy does not seem to work with stdeb, so it needs to be
	# disabled with "SETUPTOOLS_USE_DISTUTILS=stdlib".
	cd nextcloud-talk-recording-$(NEXTCLOUD_TALK_RECORDING_VERSION)/ && SETUPTOOLS_USE_DISTUTILS=stdlib python3 setup.py --command-packages=stdeb.command sdist_dsc bdist_deb
