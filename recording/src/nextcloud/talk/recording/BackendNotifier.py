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
import json
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

def backendRequest(backend, data):
    """
    Sends the data to the backend on the endpoint to receive notifications from
    the recording server.

    The data is automatically wrapped in a request for the appropriate URL and
    with the needed headers.

    :param backend: the backend to send the data to.
    :param data: the data to send.
    """
    url = backend + '/ocs/v2.php/apps/spreed/api/v1/recording/backend'

    data = json.dumps(data).encode()

    random, checksum = getRandomAndChecksum(backend, data)

    headers = {
        'Content-Type': 'application/json',
        'OCS-ApiRequest': 'true',
        'Talk-Recording-Random': random,
        'Talk-Recording-Checksum': checksum,
    }

    backendRequest = Request(url, data, headers)

    doRequest(backend, backendRequest)

def started(backend, token, status, actorType, actorId):
    """
    Notifies the backend that the recording was started.

    :param backend: the backend of the conversation.
    :param token: the token of the conversation.
    :param actorType: the actor type of the Talk participant that started the
           recording.
    :param actorId: the actor id of the Talk participant that started the
           recording.
    """

    backendRequest(backend, {
        'type': 'started',
        'started': {
            'token': token,
            'status': status,
            'actor': {
                'type': actorType,
                'id': actorId,
            },
        },
    })

def stopped(backend, token, actorType, actorId):
    """
    Notifies the backend that the recording was stopped.

    :param backend: the backend of the conversation.
    :param token: the token of the conversation.
    :param actorType: the actor type of the Talk participant that stopped the
           recording.
    :param actorId: the actor id of the Talk participant that stopped the
           recording.
    """

    data = {
        'type': 'stopped',
        'stopped': {
            'token': token,
        },
    }

    if actorType != None and actorId != None:
        data['stopped']['actor'] = {
            'type': actorType,
            'id': actorId,
        }

    backendRequest(backend, data)

def failed(backend, token):
    """
    Notifies the backend that the recording failed.

    :param backend: the backend of the conversation.
    :param token: the token of the conversation.
    """

    data = {
        'type': 'failed',
        'failed': {
            'token': token,
        },
    }

    backendRequest(backend, data)

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
