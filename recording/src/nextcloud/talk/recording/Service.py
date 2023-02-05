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
Module to start and stop the recording for a specific call.
"""

import logging
import os
import pulsectl
import subprocess
from datetime import datetime
from pyvirtualdisplay import Display
from secrets import token_urlsafe
from threading import Thread

from . import BackendNotifier
from .Config import config
from .Participant import Participant

RECORDING_STATUS_AUDIO_AND_VIDEO = 1
RECORDING_STATUS_AUDIO_ONLY = 2

def getRecorderArgs(status, displayId, audioSinkIndex, width, height, extensionlessOutputFileName):
    """
    Returns the list of arguments to start the recorder process.

    :param status: whether to record audio and video or only audio.
    :param displayId: the ID of the display that the browser is running in.
    :param audioSinkIndex: the index of the sink for the browser audio output.
    :param width: the width of the display and the recording.
    :param height: the height of the display and the recording.
    :param extensionlessOutputFileName: the file name for the recording, without
           extension.
    :returns: the file name for the recording, with extension.
    """

    ffmpegCommon = ['ffmpeg', '-loglevel', 'level+warning', '-n']
    ffmpegInputAudio = ['-f', 'pulse', '-i', audioSinkIndex]
    ffmpegInputVideo = ['-f', 'x11grab', '-draw_mouse', '0', '-video_size', f'{width}x{height}', '-i', displayId]
    ffmpegOutputAudio = ['-c:a', 'libopus']
    ffmpegOutputVideo = ['-c:v', 'libvpx', '-quality:v', 'realtime']

    extension = '.ogg'
    if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
        extension = '.webm'

    outputFileName = extensionlessOutputFileName + extension

    ffmpegArgs = ffmpegCommon
    ffmpegArgs += ffmpegInputAudio

    if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
        ffmpegArgs += ffmpegInputVideo

    ffmpegArgs += ffmpegOutputAudio

    if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
        ffmpegArgs += ffmpegOutputVideo

    return ffmpegArgs + [outputFileName]

def newAudioSink(sanitizedBackend, token):
    """
    Start new audio sink for the audio output of the browser.

    Each browser instance uses its own sink that will then be captured by the
    recorder. Otherwise several browsers would use the same default sink, and
    their audio output would be mixed.

    The sink is created by loading a null sink module. This module needs to be
    unloaded once the sink is no longer needed to remove it.

    :param sanitizedBackend: the backend of the call; it is expected to have
           been sanitized and to contain only alpha-numeric characters.
    :param token: the token of the call.
    :return: a tuple with the module index and the sink index, both as ints.
    """

    # A random value is appended to the backend and token to "ensure" that there
    # will be no name clashes if a previous sink for that backend and module was
    # not unloaded yet.
    sinkName = f"{sanitizedBackend}-{token}-{token_urlsafe(32)}"

    # Module names can be, at most, 127 characters, so the name is truncated if
    # needed.
    sinkName = sinkName[:127]

    with pulsectl.Pulse(f"{sinkName}-loader") as pacmd:
        pacmd.module_load("module-null-sink", f"sink_name={sinkName}")

        moduleIndex = None
        moduleList = pacmd.module_list()
        for module in moduleList:
            if module.argument == f"sink_name={sinkName}":
                moduleIndex = module.index

        if not moduleIndex:
            raise Exception(f"New audio module for sink {sinkName} not found ({moduleList})")

        sinkIndex = None
        sinkList = pacmd.sink_list()
        for sink in sinkList:
            if sink.name == sinkName:
                sinkIndex = sink.index

        if not sinkIndex:
            raise Exception(f"New audio sink {sinkName} not found ({sinkList})")

    return moduleIndex, sinkIndex

def recorderLog(backend, token, pipe):
    """
    Logs the recorder output.

    :param backend: the backend of the call.
    :param token: the token of the call.
    :param pipe: Pipe to the recorder process output.
    """
    logger = logging.getLogger(f"{__name__}.recorder-{backend}-{token}")

    with pipe:
        for line in pipe:
            # Lines captured from the recorder have a trailing new line, so it
            # needs to be removed.
            logger.info(line.rstrip('\n'))

class Service:
    """
    Class to set up and tear down the needed elements to record a call.

    To record a call a virtual display server and an audio sink are created.
    Then a browser is launched in kiosk mode inside the virtual display server,
    and its audio is routed to the audio sink. This ensures that several
    Services / browsers can be running at the same time without interfering with
    each other, and that the virtual display driver will only show the browser
    contents, without any browser UI. Then the call is joined in the browser,
    and an FFMPEG process to record the virtual display driver and the audio
    sink is started.

    Once the recording is stopped the helper elements are also stopped and the
    recording is uploaded to the Nextcloud server.

    "start()" blocks until the recording ends, so "start()" and "stop()" are
    expected to be called from different threads.
    """

    def __init__(self, backend, token, status, owner):
        self._logger = logging.getLogger(f"{__name__}-{backend}-{token}")

        self.backend = backend
        self.token = token
        self.status = status
        self.owner = owner

        self._display = None
        self._audioModuleIndex = None
        self._participant = None
        self._process = None
        self._fileName = None

    def __del__(self):
        self._stopHelpers()

    def start(self):
        """
        Starts the recording.

        This method blocks until the recording ends.

        :raise Exception: if the recording ends unexpectedly (including if it
               could not be started).
        """

        width = config.getBackendVideoWidth(self.backend)
        height = config.getBackendVideoHeight(self.backend)

        directory = config.getBackendDirectory(self.backend).rstrip('/')

        sanitizedBackend = ''.join([character for character in self.backend if character.isalnum()])

        fullDirectory = f'{directory}/{sanitizedBackend}/{self.token}'

        try:
            # Ensure that PulseAudio is running.
            # A "long" timeout is used to prevent it from exiting before the
            # call was joined.
            subprocess.run(['pulseaudio', '--start', '--exit-idle-time=120'], check=True)

            # Ensure that the directory to start the recordings exists.
            os.makedirs(fullDirectory, exist_ok=True)

            self._display = Display(size=(width, height), manage_global_env=False)
            self._display.start()

            # Start new audio sink for the audio output of the browser.
            self._audioModuleIndex, audioSinkIndex = newAudioSink(sanitizedBackend, self.token)
            audioSinkIndex = str(audioSinkIndex)

            env = self._display.env()
            env['PULSE_SINK'] = audioSinkIndex

            self._logger.debug("Starting participant")
            self._participant = Participant('firefox', self.backend, width, height, env, self._logger)

            self._logger.debug("Joining call")
            self._participant.joinCall(self.token)

            extensionlessFileName = f'{fullDirectory}/recording-{datetime.now().strftime("%Y%m%d-%H%M%S")}'

            recorderArgs = getRecorderArgs(self.status, self._display.new_display_var, audioSinkIndex, width, height, extensionlessFileName)

            self._fileName = recorderArgs[-1]

            self._logger.debug("Starting recorder")
            self._process = subprocess.Popen(recorderArgs, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)

            # Log recorder output.
            Thread(target=recorderLog, args=[self.backend, self.token, self._process.stdout], daemon=True).start()

            returnCode = self._process.wait()

            # recorder process will be explicitly terminated when needed, which
            # returns with 255; any other return code means that it ended
            # without an expected reason.
            if returnCode != 255:
                raise Exception("recorder ended unexpectedly")
        except Exception as exception:
            self._stopHelpers()

            raise

    def stop(self):
        """
        Stops the recording and uploads it.

        The recording is removed from the temporary directory once uploaded,
        although it is kept if the upload fails.

        :raise Exception: if the file could not be uploaded.
        """

        self._stopHelpers()

        if not self._fileName:
            self._logger.error(f"Recording stopping before starting, nothing to upload")

            return

        if not os.path.exists(self._fileName):
            self._logger.error(f"Recording can not be uploaded, {self._fileName} does not exist")

            return

        BackendNotifier.uploadRecording(self.backend, self.token, self._fileName, self.owner)

        os.remove(self._fileName)

    def _stopHelpers(self):
        if self._process:
            self._logger.debug("Stopping recorder")
            try:
                self._process.terminate()
                self._process.wait()
            except:
                self._logger.exception("Error when terminating recorder")
            finally:
                self._process = None

        if self._participant:
            self._logger.debug("Leaving call")
            try:
                self._participant.leaveCall()
            except:
                self._logger.exception("Error when leaving call")
            finally:
                self._participant = None

        if self._audioModuleIndex:
            self._logger.debug("Unloading audio module")
            try:
                with pulsectl.Pulse(f"audio-module-{self._audioModuleIndex}-unloader") as pacmd:
                    pacmd.module_unload(self._audioModuleIndex)
            except:
                self._logger.exception("Error when unloading audio module")
            finally:
                self._audioModuleIndex = None

        if self._display:
            self._logger.debug("Stopping display")
            try:
                self._display.stop()
            except:
                self._logger.exception("Error when stopping display")
            finally:
                self._display = None
