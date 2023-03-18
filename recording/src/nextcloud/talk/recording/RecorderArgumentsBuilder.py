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
    codec, can be customized. Those values are got from the configuration,
    either a specific value set in the configuration file or a default value.
    """

    def __init__(self):
        self._ffmpegOutputAudio = None
        self._ffmpegOutputVideo = None
        self._extension = None

    def getRecorderArguments(self, status, displayId, audioSinkIndex, width, height, extensionlessOutputFileName):
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

        ffmpegArguments = ffmpegCommon
        ffmpegArguments += ffmpegInputAudio

        if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
            ffmpegArguments += ffmpegInputVideo

        ffmpegArguments += ffmpegOutputAudio

        if status == RECORDING_STATUS_AUDIO_AND_VIDEO:
            ffmpegArguments += ffmpegOutputVideo

        return ffmpegArguments + [outputFileName]
