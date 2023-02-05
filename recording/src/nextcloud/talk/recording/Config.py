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

"""
Module for getting the configuration.

Other modules are expected to import the shared "config" object, which will be
loaded with the configuration file at startup.
"""

import logging
import os

from configparser import ConfigParser

class Config:
    def __init__(self):
        self._logger = logging.getLogger(__name__)

        self._configParser = ConfigParser()

        self._backendIdsByBackendUrl = {}

    def load(self, fileName):
        fileName = os.path.abspath(fileName)

        if not os.path.exists(fileName):
            self._logger.warning(f"Configuration file not found: {fileName}")
        else:
            self._logger.info(f"Loading {fileName}")

        self._configParser.read(fileName)

        self._loadBackends()

    def _loadBackends(self):
        self._backendIdsByBackendUrl = {}

        if 'backend' not in self._configParser or 'backends' not in self._configParser['backend']:
            self._logger.warning(f"No configured backends")

            return

        backendIds = self._configParser.get('backend', 'backends')
        backendIds = [backendId.strip() for backendId in backendIds.split(',')]

        for backendId in backendIds:
            if 'url' not in self._configParser[backendId]:
                self._logger.error(f"Missing 'url' property for backend {backendId}")
                continue

            if 'secret' not in self._configParser[backendId]:
                self._logger.error(f"Missing 'secret' property for backend {backendId}")
                continue

            backendUrl = self._configParser[backendId]['url'].rstrip('/')
            self._backendIdsByBackendUrl[backendUrl] = backendId

    def getLogLevel(self):
        """
        Returns the log level.

        Defaults to INFO (20).
        """
        return int(self._configParser.get('logs', 'level', fallback=logging.INFO))

    def getListen(self):
        """
        Returns the IP and port to listen on for HTTP requests.

        Defaults to "127.0.0.1:8000".
        """
        return self._configParser.get('http', 'listen', fallback='127.0.0.1:8000')

    def getBackendSecret(self, backendUrl):
        """
        Returns the shared secret for requests from and to the backend servers.

        Defaults to None.
        """
        if self._configParser.get('backend', 'allowall', fallback=None):
            return self._configParser.get('backend', 'secret')

        backendUrl = backendUrl.rstrip('/')
        if backendUrl in self._backendIdsByBackendUrl:
            backendId = self._backendIdsByBackendUrl[backendUrl]

            return self._configParser.get(backendId, 'secret', fallback=None)

        return None

    def getBackendSkipVerify(self, backendUrl):
        """
        Returns whether the certificate validation of backend endpoints should
        be skipped or not.

        Defaults to False.
        """
        return self._getBackendValue(backendUrl, 'skipverify', False) == 'true'

    def getBackendMaximumMessageSize(self, backendUrl):
        """
        Returns the maximum allowed size in bytes for messages sent by the
        backend.

        Defaults to 1024.
        """
        return int(self._getBackendValue(backendUrl, 'maxmessagesize', 1024))

    def getBackendVideoWidth(self, backendUrl):
        """
        Returns the width for recorded videos.

        Defaults to 1920.
        """
        return int(self._getBackendValue(backendUrl, 'videowidth', 1920))

    def getBackendVideoHeight(self, backendUrl):
        """
        Returns the height for recorded videos.

        Defaults to 1080.
        """
        return int(self._getBackendValue(backendUrl, 'videoheight', 1080))

    def getBackendDirectory(self, backendUrl):
        """
        Returns the temporary directory used to store recordings until uploaded.

        Defaults to False.
        """
        return self._getBackendValue(backendUrl, 'directory', '/tmp')

    def _getBackendValue(self, backendUrl, key, default):
        backendUrl = backendUrl.rstrip('/')
        if backendUrl in self._backendIdsByBackendUrl:
            backendId = self._backendIdsByBackendUrl[backendUrl]

            if self._configParser.get(backendId, key, fallback=None):
                return self._configParser.get(backendId, key)

        return self._configParser.get('backend', key, fallback=default)

config = Config()
