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

from nextcloud.talk.recording import RECORDING_STATUS_AUDIO_AND_VIDEO, RECORDING_STATUS_AUDIO_ONLY
from .Config import config

class RecorderArgumentsBuilder:
    """
    Helper class to get the arguments to start the recorder process.

    Some of the recorder arguments, like the arguments for the output video
    codec, can be customized. By default they are got from the configuration,
    either a specific value set in the configuration file or a default value,
    but the configuration value can be overriden by explicitly setting it in
    RecorderArgumentsBuilder.
    """

    def __init__(self):
        self._ffmpegCommon = None
        self._ffmpegOutputAudio = None
        self._ffmpegOutputVideo = None
        self._extension = None

    def getRecorderArguments(self, status, displayId, audioSourceIndex, width, height, extensionlessOutputFileName):
        """
        Returns the list of arguments to start the recorder process.

        :param status: whether to record audio and video or only audio.
        :param displayId: the ID of the display that the browser is running in.
        :param audioSourceIndex: the index of the source for the browser audio
               output.
        :param width: the width of the display and the recording.
        :param height: the height of the display and the recording.
        :param extensionlessOutputFileName: the file name for the recording, without
               extension.
        :returns: the file name for the recording, with extension.
        """

        ffmpegCommon = self.getFfmpegCommon()
        ffmpegInputAudio = ['-f', 'pulse', '-i', audioSourceIndex]
        ffmpegInputVideo = ['-f', 'x11grab', '-draw_mouse', '0', '-video_size', f'{width}x{height}', '-i', displayId]
        ffmpegOutputAudio = self.getFfmpegOutputAudio()
        ffmpegOutputVideo = self.getFfmpegOutputVideo()

        extension = self.getExtension(status)

        outputFileName = extensionlessOutputFileName + extension

        ffmpegArguments = ffmpegCommon
        ffmpegArguments += ffmpegInputAudio

        if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
            ffmpegArguments += ffmpegInputVideo

        ffmpegArguments += ffmpegOutputAudio

        if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
            ffmpegArguments += ffmpegOutputVideo

        return ffmpegArguments + [outputFileName]

    def getFfmpegCommon(self):
        if self._ffmpegCommon != None:
            return self._ffmpegCommon

        return config.getFfmpegCommon()

    def getFfmpegOutputAudio(self):
        if self._ffmpegOutputAudio != None:
            return self._ffmpegOutputAudio

        return config.getFfmpegOutputAudio()

    def getFfmpegOutputVideo(self):
        if self._ffmpegOutputVideo != None:
            return self._ffmpegOutputVideo

        return config.getFfmpegOutputVideo()

    def getExtension(self, status):
        if self._extension:
            return self._extension

        if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
            return config.getFfmpegExtensionVideo()

        return config.getFfmpegExtensionAudio()

    def setFfmpegCommon(self, ffmpegCommon):
        self._ffmpegCommon = ffmpegCommon

    def setFfmpegOutputAudio(self, ffmpegOutputAudio):
        self._ffmpegOutputAudio = ffmpegOutputAudio

    def setFfmpegOutputVideo(self, ffmpegOutputVideo):
        self._ffmpegOutputVideo = ffmpegOutputVideo

    def setExtension(self, extension):
        self._extension = extension
