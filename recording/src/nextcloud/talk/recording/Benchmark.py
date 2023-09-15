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

import argparse
import atexit
import logging
import os
import subprocess
from threading import Event, Thread
from time import sleep, time

import psutil
import pulsectl
from pyvirtualdisplay import Display

from nextcloud.talk.recording import RECORDING_STATUS_AUDIO_AND_VIDEO, RECORDING_STATUS_AUDIO_ONLY
from .Config import Config
from .Participant import SeleniumHelper
from .RecorderArgumentsBuilder import RecorderArgumentsBuilder
from .Service import newAudioSink, processLog

class ResourcesTracker:
    """
    Class to track the resources used by the recorder and to stop it once the
    benchmark ends.

    The ResourcesTracker runs in a different thread to the one that started it,
    as that thread needs to block until the recorder process ends.
    """

    def __init__(self):
        self.logger = logging.getLogger("stats")

        self.cpuPercents = []
        self.memoryInfos = []
        self.memoryPercents = []

    def start(self, pid, length, stopResourcesTrackerThread):
        self._thread = Thread(target=self._track, args=[pid, length, stopResourcesTrackerThread], daemon=True)
        self._thread.start()

    def _track(self, pid, length, stopResourcesTrackerThread):
        # Wait a little for the values to stabilize.
        sleep(5)

        if stopResourcesTrackerThread.is_set():
            return

        process = psutil.Process(pid)
        # Get first percent value before the real loop, as the first time it can
        # be 0.
        process.cpu_percent()

        startTime = time()
        count = 0
        while time() - startTime < length:
            sleep(1)
            count += 1

            if stopResourcesTrackerThread.is_set():
                return

            self.logger.info(count)

            cpuPercent = process.cpu_percent()
            self.logger.info(f"CPU percent: {cpuPercent}")
            self.cpuPercents.append(cpuPercent)

            memoryInfo = process.memory_info()
            self.logger.info(f"Memory info: {memoryInfo}")
            self.memoryInfos.append(memoryInfo)

            memoryPercent = process.memory_percent()
            self.logger.info(f"Memory percent: {memoryPercent}")
            self.memoryPercents.append(memoryPercent)

        process.terminate()

class BenchmarkService:
    """
    Class to set up and tear down the needed elements to benchmark the recorder.

    To benchmark the recorder a virtual display server and an audio sink are
    created. Then a video is played in the virtual display server, and its audio
    is routed to the audio sink. This ensures that the benchmark will not
    interfere with other processes that could be running on the machine. Then an
    FFMPEG process to record the virtual display driver and the audio sink is
    started, and finally a helper object to track the resources used by the
    recorder as well as to stop it once the benchmark ends is also started.

    Once the recorder process is stopped the helper elements are automatically
    stopped too.
    """

    def __init__(self):
        self._logger = logging.getLogger()

        self._display = None
        self._audioModuleIndex = None
        self._playerProcess = None
        self._recorderProcess = None

        self._recorderArguments = None
        self._averageCpuPercents = None
        self._averageMemoryInfos = None
        self._averageMemoryPercents = None

    def __del__(self):
        self._stopHelpers()

    def run(self, args):
        directory = os.path.dirname(args.output)

        stopResourcesTrackerThread = Event()

        if not os.path.exists(args.input):
            raise Exception("Input file does not exist")

        try:
            # Ensure that PulseAudio is running.
            # A "long" timeout is used to prevent it from exiting before the
            # player starts.
            subprocess.run(['pulseaudio', '--start', '--exit-idle-time=120'], check=True)

            # Ensure that the directory to store the recording exists.
            os.makedirs(directory, exist_ok=True)

            self._display = Display(size=(args.width, args.height), manage_global_env=False)
            self._display.start()

            # Start new audio sink for the audio output of the player.
            self._audioModuleIndex, audioSinkIndex, audioSourceIndex = newAudioSink("nextcloud-talk-recording-benchmark")
            audioSinkIndex = str(audioSinkIndex)
            audioSourceIndex = str(audioSourceIndex)

            env = self._display.env()
            env['PULSE_SINK'] = audioSinkIndex

            self._logger.debug("Playing video")
            playerArgs = ["ffplay", "-x", str(args.width), "-y", str(args.height), args.input]
            self._playerProcess = subprocess.Popen(playerArgs, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, env=env)

            # Log player output.
            Thread(target=processLog, args=["player", self._playerProcess.stdout, logging.DEBUG], daemon=True).start()

            extensionlessFileName, extension = args.output.rsplit(".", 1)

            status = RECORDING_STATUS_AUDIO_ONLY if args.audio_only else RECORDING_STATUS_AUDIO_AND_VIDEO

            recorderArgumentsBuilder = RecorderArgumentsBuilder()
            recorderArgumentsBuilder.setFfmpegCommon(args.ffmpeg_common.split())
            recorderArgumentsBuilder.setFfmpegOutputAudio(args.ffmpeg_output_audio.split())
            recorderArgumentsBuilder.setFfmpegOutputVideo(args.ffmpeg_output_video.split())
            recorderArgumentsBuilder.setExtension(f".{extension}")
            self._recorderArguments = recorderArgumentsBuilder.getRecorderArguments(status, self._display.new_display_var, audioSourceIndex, args.width, args.height, extensionlessFileName)

            self._fileName = self._recorderArguments[-1]

            if os.path.exists(self._fileName):
                raise Exception("File exists")

            self._logger.debug("Starting recorder")
            self._recorderProcess = subprocess.Popen(self._recorderArguments, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)

            self._resourcesTracker = ResourcesTracker()
            self._resourcesTracker.start(self._recorderProcess.pid, args.length, stopResourcesTrackerThread)

            # Log recorder output.
            Thread(target=processLog, args=["recorder", self._recorderProcess.stdout], daemon=True).start()

            returnCode = self._recorderProcess.wait()

            # recorder process will be explicitly terminated by ResourcesTracker
            # when needed, which returns with 255; any other return code means that
            # it ended without an expected reason.
            if returnCode != 255:
                raise Exception("recorder ended unexpectedly")
        finally:
            stopResourcesTrackerThread.set()
            self._stopHelpers()

        if len(self._resourcesTracker.cpuPercents) > 0:
            self._averageCpuPercents = 0
            for cpuPercents in self._resourcesTracker.cpuPercents:
                self._averageCpuPercents += cpuPercents
            self._averageCpuPercents /= len(self._resourcesTracker.cpuPercents)

        if len(self._resourcesTracker.memoryInfos) > 0:
            self._averageMemoryInfos = {}
            self._averageMemoryInfos["rss"] = 0
            self._averageMemoryInfos["vms"] = 0
            for memoryInfos in self._resourcesTracker.memoryInfos:
                self._averageMemoryInfos["rss"] += memoryInfos.rss
                self._averageMemoryInfos["vms"] += memoryInfos.vms
            self._averageMemoryInfos["rss"] /= len(self._resourcesTracker.memoryInfos)
            self._averageMemoryInfos["vms"] /= len(self._resourcesTracker.memoryInfos)

        if len(self._resourcesTracker.memoryPercents) > 0:
            self._averageMemoryPercents = 0
            for memoryPercents in self._resourcesTracker.memoryPercents:
                self._averageMemoryPercents += memoryPercents
            self._averageMemoryPercents /= len(self._resourcesTracker.memoryPercents)

    def getRecorderArguments(self):
        return self._recorderArguments

    def getAverageCpuPercents(self):
        return self._averageCpuPercents

    def getAverageMemoryInfos(self):
        return self._averageMemoryInfos

    def getAverageMemoryPercents(self):
        return self._averageMemoryPercents

    def _stopHelpers(self):
        if self._recorderProcess:
            self._logger.debug("Stopping recorder")
            try:
                self._recorderProcess.terminate()
                self._recorderProcess.wait()
            except:
                self._logger.exception("Error when terminating recorder")
            finally:
                self._recorderProcess = None

        if self._playerProcess:
            self._logger.debug("Stopping player")
            try:
                self._playerProcess.terminate()
                self._playerProcess.wait()
            except:
                self._logger.exception("Error when terminating player")
            finally:
                self._playerProcess = None

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

benchmarkService = None

def main():
    defaultConfig = Config()

    parser = argparse.ArgumentParser()
    parser.add_argument("-l", "--length", help="benchmark duration (in seconds)", default=180, type=int)
    parser.add_argument("--width", help="output width", default=defaultConfig.getBackendVideoWidth(""), type=int)
    parser.add_argument("--height", help="output height", default=defaultConfig.getBackendVideoHeight(""), type=int)
    parser.add_argument("--ffmpeg-common", help="ffmpeg executable and global options", default=" ".join(defaultConfig.getFfmpegCommon()), type=str)
    parser.add_argument("--ffmpeg-output-audio", help="output audio options for ffmpeg", default=" ".join(defaultConfig.getFfmpegOutputAudio()), type=str)
    parser.add_argument("--ffmpeg-output-video", help="output video options for ffmpeg", default=" ".join(defaultConfig.getFfmpegOutputVideo()), type=str)
    parser.add_argument("--audio-only", help="audio only recording", action="store_true")
    parser.add_argument("-v", "--verbose", help="verbose mode", action="store_true")
    parser.add_argument("--verbose-extra", help="extra verbose mode", action="store_true")
    parser.add_argument("input", help="input video filename")
    parser.add_argument("output", help="output filename")
    args = parser.parse_args()

    if args.verbose:
        logging.basicConfig(level=logging.INFO)
    if args.verbose_extra:
        logging.basicConfig(level=logging.DEBUG)

    global benchmarkService
    benchmarkService = BenchmarkService()
    benchmarkService.run(args)

    output = benchmarkService.getRecorderArguments()[-1]
    print(f"Recorder args: {' '.join(benchmarkService.getRecorderArguments())}")
    print(f"File size: {os.stat(output).st_size}")
    print(f"Average CPU percents: {benchmarkService.getAverageCpuPercents()}")
    print(f"Average memory infos: {benchmarkService.getAverageMemoryInfos()}")
    print(f"Average memory percents: {benchmarkService.getAverageMemoryPercents()}")

def _stopServiceOnExit():
    global benchmarkService
    if benchmarkService:
        del benchmarkService

# The service should be explicitly deleted before exiting, as if it is
# implicitly deleted while exiting the helpers may not cleanly quit.
atexit.register(_stopServiceOnExit)

if __name__ == '__main__':
    main()
