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
Module to handle incoming requests.
"""

import atexit
import json
import hashlib
import hmac
from threading import Lock, Thread

from flask import Flask, jsonify, request
from werkzeug.exceptions import BadRequest, Forbidden, NotFound

from nextcloud.talk import recording
from .Config import config
from .Service import RECORDING_STATUS_AUDIO_AND_VIDEO, Service

app = Flask(__name__)

services = {}
servicesStopping = {}
servicesLock = Lock()

@app.route("/api/v1/welcome", methods=["GET"])
def welcome():
    return jsonify(version=recording.__version__)

@app.route("/api/v1/room/<token>", methods=["POST"])
def handleBackendRequest(token):
    backend, data = _validateRequest()

    if 'type' not in data:
        raise BadRequest()

    if data['type'] == 'start':
        return startRecording(backend, token, data)

    if data['type'] == 'stop':
        return stopRecording(backend, token, data)

def _validateRequest():
    """
    Validates the current request.

    :return: the backend that sent the request and the object representation of
             the body.
    """

    if 'Talk-Recording-Backend' not in request.headers:
        app.logger.warning("Missing Talk-Recording-Backend header")
        raise Forbidden()

    backend = request.headers['Talk-Recording-Backend']

    secret = config.getBackendSecret(backend)
    if not secret:
        app.logger.warning(f"No secret configured for backend {backend}")
        raise Forbidden()

    if 'Talk-Recording-Random' not in request.headers:
        app.logger.warning("Missing Talk-Recording-Random header")
        raise Forbidden()

    random = request.headers['Talk-Recording-Random']

    if 'Talk-Recording-Checksum' not in request.headers:
        app.logger.warning("Missing Talk-Recording-Checksum header")
        raise Forbidden()

    checksum = request.headers['Talk-Recording-Checksum']

    maximumMessageSize = config.getBackendMaximumMessageSize(backend)

    if not request.content_length or request.content_length > maximumMessageSize:
        app.logger.warning(f"Message size above limit: {request.content_length} {maximumMessageSize}")
        raise BadRequest()

    body = request.get_data()

    expectedChecksum = _calculateChecksum(secret, random, body)
    if not hmac.compare_digest(checksum, expectedChecksum):
        app.logger.warning(f"Checksum verification failed: {checksum} {expectedChecksum}")
        raise Forbidden()

    return backend, json.loads(body)

def _calculateChecksum(secret, random, body):
    secret = secret.encode()
    message = random.encode() + body

    hmacValue = hmac.new(secret, message, hashlib.sha256)

    return hmacValue.hexdigest()

def startRecording(backend, token, data):
    serviceId = f'{backend}-{token}'

    if 'start' not in data:
        raise BadRequest()

    if 'owner' not in data['start']:
        raise BadRequest()

    if 'actor' not in data['start']:
        raise BadRequest()

    if 'type' not in data['start']['actor']:
        raise BadRequest()

    if 'id' not in data['start']['actor']:
        raise BadRequest()

    status = RECORDING_STATUS_AUDIO_AND_VIDEO
    if 'status' in data['start']:
        status = data['start']['status']

    owner = data['start']['owner']

    actorType = data['start']['actor']['type']
    actorId = data['start']['actor']['id']

    service = None
    with servicesLock:
        if serviceId in services:
            app.logger.warning(f"Trying to start recording again: {backend} {token}")
            return {}

        service = Service(backend, token, status, owner)

        services[serviceId] = service

    app.logger.info(f"Start recording: {backend} {token}")

    serviceStartThread = Thread(target=_startRecordingService, args=[service, actorType, actorId], daemon=True)
    serviceStartThread.start()

    return {}

def _startRecordingService(service, actorType, actorId):
    """
    Helper function to start a recording service.

    The recording service will be removed from the list of services if it can
    not be started.

    :param service: the Service to start.
    """
    serviceId = f'{service.backend}-{service.token}'

    try:
        service.start(actorType, actorId)
    except Exception as exception:
        with servicesLock:
            if serviceId not in services:
                # Service was already stopped, exception should have been caused
                # by stopping the helpers even before the recorder started.
                app.logger.info(f"Recording stopped before starting: {service.backend} {service.token}", exc_info=exception)
                
                return

            app.logger.exception(f"Failed to start recording: {service.backend} {service.token}")

            services.pop(serviceId)

def stopRecording(backend, token, data):
    serviceId = f'{backend}-{token}'

    if 'stop' not in data:
        raise BadRequest()

    actorType = None
    actorId = None
    if 'actor' in data['stop'] and 'type' in data['stop']['actor'] and 'id' in data['stop']['actor']:
        actorType = data['stop']['actor']['type']
        actorId = data['stop']['actor']['id']

    service = None
    with servicesLock:
        if serviceId not in services and serviceId in servicesStopping:
            app.logger.info(f"Trying to stop recording again: {backend} {token}")
            return {}

        if serviceId not in services:
            app.logger.warning(f"Trying to stop unknown recording: {backend} {token}")
            raise NotFound()

        service = services[serviceId]

        services.pop(serviceId)

        servicesStopping[serviceId] = service

    app.logger.info(f"Stop recording: {backend} {token}")

    serviceStopThread = Thread(target=_stopRecordingService, args=[service, actorType, actorId], daemon=True)
    serviceStopThread.start()

    return {}

def _stopRecordingService(service, actorType, actorId):
    """
    Helper function to stop a recording service.

    The recording service will be removed from the list of services being
    stopped once it is fully stopped.

    :param service: the Service to stop.
    """
    serviceId = f'{service.backend}-{service.token}'

    try:
        service.stop(actorType, actorId)
    except Exception as exception:
        app.logger.exception(f"Failed to stop recording: {service.backend} {service.token}")
    finally:
        with servicesLock:
            if serviceId not in servicesStopping:
                # This should never happen.
                app.logger.error(f"Recording stopped when not in the list of stopping services: {service.backend} {service.token}")

                return

            servicesStopping.pop(serviceId)

# Despite this handler it seems that in some cases the geckodriver could have
# been killed already when it is executed, which unfortunately prevents a proper
# cleanup of the temporary files opened by the browser.
def _stopServicesOnExit():
    with servicesLock:
        serviceIds = list(services.keys())
        for serviceId in serviceIds:
            service = services.pop(serviceId)
            del service

# Services should be explicitly deleted before exiting, as if they are
# implicitly deleted while exiting the Selenium driver may not cleanly quit.
atexit.register(_stopServicesOnExit)
