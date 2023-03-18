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
from threading import Event, Thread

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
    ffmpegOutputAudio = config.getFfmpegOutputAudio()
    ffmpegOutputVideo = config.getFfmpegOutputVideo()

    extension = config.getFfmpegExtensionAudio()
    if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
        extension = config.getFfmpegExtensionVideo()

    outputFileName = extensionlessOutputFileName + extension

    ffmpegArgs = ffmpegCommon
    ffmpegArgs += ffmpegInputAudio

    if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
        ffmpegArgs += ffmpegInputVideo

    ffmpegArgs += ffmpegOutputAudio

    if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
        ffmpegArgs += ffmpegOutputVideo

    return ffmpegArgs + [outputFileName]

def newAudioSink(baseSinkName):
    """
    Start new audio sink for the audio output of the browser.

    Each browser instance uses its own sink that will then be captured by the
    recorder. Otherwise several browsers would use the same default sink, and
    their audio output would be mixed.

    The sink is created by loading a null sink module. This module needs to be
    unloaded once the sink is no longer needed to remove it.

    :param baseSinkName: the base name for the sink; it is expected to have been
           sanitized and to contain only alpha-numeric characters.
    :return: a tuple with the module index and the sink index, both as ints.
    """

    # A random value is appended to the base sink name to "ensure" that there
    # will be no name clashes if a previous sink with that base name was not
    # unloaded yet.
    sinkName = f"{baseSinkName}-{token_urlsafe(32)}"

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

def recorderLog(loggerName, pipe):
    """
    Logs the recorder output.

    :param loggerName: the name of the logger.
    :param pipe: Pipe to the recorder process output.
    """
    logger = logging.getLogger(loggerName)

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

        self._started = Event()
        self._stopped = Event()

        self._display = None
        self._audioModuleIndex = None
        self._participant = None
        self._process = None
        self._fileName = None

    def __del__(self):
        self._stopHelpers()

    def start(self, actorType, actorId):
        """
        Starts the recording.

        This method blocks until the recording ends.

        :param actorType: the actor type of the Talk participant that started
               the recording.
        :param actorId: the actor id of the Talk participant that started the
               recording.
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

            if self._stopped.is_set():
                raise Exception("Display started after recording was stopped")

            # Start new audio sink for the audio output of the browser.
            self._audioModuleIndex, audioSinkIndex = newAudioSink(f"{sanitizedBackend}-{self.token}")
            audioSinkIndex = str(audioSinkIndex)

            if self._stopped.is_set():
                raise Exception("Audio sink started after recording was stopped")

            env = self._display.env()
            env['PULSE_SINK'] = audioSinkIndex

            self._logger.debug("Starting participant")
            self._participant = Participant('firefox', self.backend, width, height, env, self._logger)

            self._logger.debug("Joining call")
            self._participant.joinCall(self.token)

            if self._stopped.is_set():
                # Not strictly needed, as if the participant is started or the
                # call is joined after the recording was stopped there will be
                # no display and it will automatically fail, but just in case.
                raise Exception("Call joined after recording was stopped")

            self._started.set()

            BackendNotifier.started(self.backend, self.token, self.status, actorType, actorId)

            extensionlessFileName = f'{fullDirectory}/recording-{datetime.now().strftime("%Y%m%d-%H%M%S")}'

            recorderArgs = getRecorderArgs(self.status, self._display.new_display_var, audioSinkIndex, width, height, extensionlessFileName)

            self._fileName = recorderArgs[-1]

            self._logger.debug("Starting recorder")
            self._process = subprocess.Popen(recorderArgs, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)

            # Log recorder output.
            Thread(target=recorderLog, args=[f"{__name__}.recorder-{self.backend}-{self.token}", self._process.stdout], daemon=True).start()

            if self._stopped.is_set():
                # Not strictly needed, as if the recorder is started after the
                # recording was stopped there will be no display and it will
                # automatically fail, but just in case.
                raise Exception("Call joined after recording was stopped")

            returnCode = self._process.wait()

            # recorder process will be explicitly terminated when needed, which
            # returns with 255; any other return code means that it ended
            # without an expected reason.
            if returnCode != 255:
                raise Exception("recorder ended unexpectedly")
        except Exception as exception:
            self._stopHelpers()

            if self._stopped.is_set() and not self._started.is_set():
                # If the service fails before being started but it was already
                # stopped the error does not need to be notified; the error was
                # probably caused by stopping the helpers, and even if it was
                # something else it does not need to be notified either given
                # that the recording was not started yet.
                raise

            try:
                BackendNotifier.failed(self.backend, self.token)
            except:
                pass

            raise

    def stop(self, actorType, actorId):
        """
        Stops the recording and uploads it.

        The recording is removed from the temporary directory once uploaded,
        although it is kept if the upload fails.

        :param actorType: the actor type of the Talk participant that stopped
               the recording.
        :param actorId: the actor id of the Talk participant that stopped the
               recording.
        :raise Exception: if the file could not be uploaded.
        """

        self._stopped.set()

        self._stopHelpers()

        BackendNotifier.stopped(self.backend, self.token, actorType, actorId)

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
            self._logger.debug("Disconnecting from signaling server")
            try:
                self._participant.disconnect()
            except:
                self._logger.exception("Error when disconnecting from signaling server")
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
