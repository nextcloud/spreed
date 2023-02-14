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
Module to send requests to the Nextcloud server.
"""

import hashlib
import hmac
import logging
import os
import ssl
from secrets import token_urlsafe
from urllib.request import Request, urlopen
from urllib3 import encode_multipart_formdata

from .Config import config

logger = logging.getLogger(__name__)

def getRandomAndChecksum(backend, data):
    """
    Returns a random string and the checksum of the given data with that random.

    :param backend: the backend to send the data to.
    :param data: the data, as bytes.
    """
    secret = config.getBackendSecret(backend).encode()
    random = token_urlsafe(64)
    hmacValue = hmac.new(secret, random.encode() + data, hashlib.sha256)

    return random, hmacValue.hexdigest()

def doRequest(backend, request, retries=3):
    """
    Send the request to the backend.

    SSL verification will be skipped if configured.

    :param backend: the backend to send the request to.
    :param request: the request to send.
    :param retries: the number of times to retry in case of failure.
    """
    context = None

    if config.getBackendSkipVerify(backend):
        context = ssl.create_default_context()
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE

    try:
        urlopen(request, context=context)
    except Exception as exception:
        if retries > 1:
            logger.exception(f"Failed to send message to backend, {retries} retries left!")
            doRequest(backend, request, retries - 1)
        else:
            logger.exception(f"Failed to send message to backend, giving up!")
            raise

def uploadRecording(backend, token, fileName, owner):
    """
    Upload the recording specified by fileName.

    The name of the uploaded file is the basename of the original file.

    :param backend: the backend to upload the file to.
    :param token: the token of the conversation that was recorded.
    :param fileName: the recording file name.
    :param owner: the owner of the uploaded file.
    """

    logger.info(f"Upload recording {fileName} to {backend} in {token} as {owner}")

    url = backend + '/ocs/v2.php/apps/spreed/api/v1/recording/' + token + '/store'

    fileContents = None
    with open(fileName, 'rb') as file:
        fileContents = file.read()

    # Plain values become arguments, while tuples become files; the body used to
    # calculate the checksum is empty.
    data = {
        'owner': owner,
        'file': (os.path.basename(fileName), fileContents),
    }
    data, contentType = encode_multipart_formdata(data)

    random, checksum = getRandomAndChecksum(backend, token.encode())

    headers = {
        'Content-Type': contentType,
        'OCS-ApiRequest': 'true',
        'Talk-Recording-Random': random,
        'Talk-Recording-Checksum': checksum,
    }

    uploadRequest = Request(url, data, headers)

    doRequest(backend, uploadRequest)
