#
# @copyright Copyright (c) 2022, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
Helper script that provides a command line interface for Talkbuchet, the helper
tool for load/stress testing of Nextcloud Talk.

Talkbuchet is a JavaScript script (Talkbuchet.js), and it is run using a web
browser. A Python script (Talkbuchet-cli.py) is provided to launch a web
browser, load Talkbuchet and control it from a command line interface (which
requires Selenium and certain Python packages to be available in the system).

Please refer to the documentation in Talkbuchet.js for information on
Talkbuchet.

Talkbuchet-cli.py provides a wrapper class to start and interact with
Talkbuchet.js instances. Creating an object of the wrapper class launches a new
browser instance, opens the given Nextcloud URL and loads Talkbuchet.js on it.
After that the methods in the object call their counterpart in the wrapped
Talkbuchet.js instance; the wrapper objects provide full control of their
wrapped Talkbuchet.js instance.
"""

import atexit

from pathlib import Path
from selenium import webdriver
from shutil import disk_usage


class SeleniumHelper:
    """
    Helper class to start a browser and execute scripts in it using Selenium.

    By default the browser will be started in headless mode, so the browser will
    not be visible. It is not possible to make it visible once the browser was
    launched; in order to show it the browser has to be started again in
    non-headless mode.

    The browser, as well as the Selenium server, are expected to be available in
    the local system. A remote Selenium server can be used instead by specifying
    the URL when starting the browser. However, when a remote Selenium server is
    used its session timeout (which is independent from the timeouts set in the
    driver) must be kept in mind, as it can cause the browser to "unexpectedly"
    close.
    """

    def __init__(self):
        self.driver = None

    def __del__(self):
        if self.driver:
            # The session must be explicitly quit to remove the temporary files
            # created in "/tmp".
            self.driver.quit()

    def startChrome(self, headless = True, remoteSeleniumUrl = None):
        """
        Starts a Chrome instance.

        :param headless: whether the browser will be started in headless mode or
            not; headless mode is used by default.
        :param remoteSeleniumUrl: the URL of the Selenium server to connect to;
            the local server is used by default.
        """

        options = webdriver.ChromeOptions()
        options.set_capability("goog:loggingPrefs", { 'browser': 'ALL' })
        options.add_argument('--use-fake-device-for-media-stream')
        options.add_argument('--use-fake-ui-for-media-stream')

        # Headless mode uses a little less memory on each instance.
        if headless:
            options.add_argument('--headless')

        if remoteSeleniumUrl:
            self.driver = webdriver.Remote(
                command_executor=remoteSeleniumUrl,
                options=options
            )
        else:
            # Error messages like "Failed to fetch" or crashes when starting the
            # driver are usually caused by not having enough space in "/dev/shm"
            # or in "/tmp".
            # Using "/dev/shm" provides better performance, but it is not
            # strictly needed, so it can be disabled if there is not enough free
            # space. The limit is set to 64 MiB just based on the memory usage
            # observed during some tests.
            if disk_usage('/dev/shm').free < 67108864:
                print('Less than 64 MiB available in "/dev/shm", usage disabled')

                options.add_argument("--disable-dev-shm-usage")

            if disk_usage('/tmp').free < 134217728:
                print('Warning: less than 128 MiB available in "/tmp", strange failures may occur')

            self.driver = webdriver.Chrome(
                options=options
            )

    def clearLogs(self):
        """
        Clears browser logs not printed yet.

        This does not affect the logs in the browser itself, only the ones
        received by the SeleniumHelper.
        """

        self.driver.get_log('browser')

    def printLogs(self):
        """
        Prints browser logs received since last print.
        """

        for log in self.driver.get_log('browser'):
            print(log['message'])

    def execute(self, script):
        """
        Executes the given script.
        """

        self.driver.execute_script(script)

        self.printLogs()


class Talkbuchet:
    """
    Wrapper for Talkbuchet.

    Talkbuchet wrappers load Talkbuchet on a given Nextcloud URL and provide
    methods to call the different Talkbuchet functions in the browser.
    """

    def __init__(self, nextcloudUrl, headless = True, remoteSeleniumUrl = None):
        """
        Loads Talkbuchet on the given Nextcloud URL.

        :param nextcloudUrl: the URL of the Nextcloud instance to load
            Talkbuchet on.
        :param headless: whether the browser will be started in headless mode or
            not; headless mode is used by default.
        :param remoteSeleniumUrl: the URL of the Selenium server to connect to;
            the local server is used by default.
        """

        # Set default values from Talkbuchet.js.
        self.publishersCount = 5
        self.subscribersPerPublisherCount = 40
        self.connectionWarningTimeout = 5000

        self.seleniumHelper = SeleniumHelper()

        self.seleniumHelper.startChrome(headless, remoteSeleniumUrl)

        self.seleniumHelper.driver.get(nextcloudUrl)

        self.__loadTalkbuchet()

    def __loadTalkbuchet(self):
        talkbuchet = Path('Talkbuchet.js').read_text()

        # Explicitly assign all the needed functions defined in Talkbuchet.js to
        # the Window object to be able to access them at a later point.
        talkbuchet = talkbuchet + '''
        window.closeConnections = closeConnections
        window.setAudioEnabled = setAudioEnabled
        window.setVideoEnabled = setVideoEnabled
        window.setSentAudioStreamEnabled = setSentAudioStreamEnabled
        window.setSentVideoStreamEnabled = setSentVideoStreamEnabled
        window.checkPublishersConnections = checkPublishersConnections
        window.checkSubscribersConnections = checkSubscribersConnections
        window.setCredentials = setCredentials
        window.setToken = setToken
        window.setPublishersAndSubscribersCount = setPublishersAndSubscribersCount
        window.startMedia = startMedia
        window.setConnectionWarningTimeout = setConnectionWarningTimeout
        window.siege = siege
        '''

        # Clear previous logs
        self.seleniumHelper.clearLogs()

        self.seleniumHelper.execute(talkbuchet)

    def setAudioEnabled(self, audioEnabled):
        """
        Sets the enabled state of the audio track in the media stream.

        :param audioEnabled: True to enable, False to disable.
        """

        self.seleniumHelper.execute('setAudioEnabled(' + ('true' if audioEnabled else 'false') + ')')

    def setVideoEnabled(self, videoEnabled):
        """
        Sets the enabled state of the video track in the media stream.

        :param videoEnabled: True to enable, False to disable.
        """

        self.seleniumHelper.execute('setVideoEnabled(' + ('true' if videoEnabled else 'false') + ')')

    def setSentAudioStreamEnabled(self, sentAudioStreamEnabled):
        """
        Sets whether the audio track is sent or not.

        :param sentAudioStreamEnabled: True to send the actual track, False to
            send a null track.
        """

        self.seleniumHelper.execute('setSentAudioStreamEnabled(' + ('true' if sentAudioStreamEnabled else 'false') + ')')

    def setSentVideoStreamEnabled(self, sentVideoStreamEnabled):
        """
        Sets whether the video track is sent or not.

        :param sentVideoStreamEnabled: True to send the actual track, False to
            send a null track.
        """

        self.seleniumHelper.execute('setSentVideoStreamEnabled(' + ('true' if sentVideoStreamEnabled else 'false') + ')')

    def setCredentials(self, user, appToken):
        """
        The user and app token to use.

        An app token/password can be generated in the Security section of the
        personal settings (index.php/settings/user/security).

        The credentials always need to be set.

        :param user: the user ID.
        :param appToken: the app token for the user.
        """

        self.seleniumHelper.execute('setCredentials(\'' + user + '\', \'' + appToken + '\')')

    def setToken(self, token):
        """
        Sets the conversation token to use.

        This should be set only when conversation clustering is enabled in the
        server.

        :param token: the conversation token.
        """

        self.seleniumHelper.execute('setToken(\'' + token + '\')')

    def startMedia(self, audio, video):
        """
        Starts the media stream to be used by publishers.

        By default audio will be used.

        Only one media stream can be active at the same time, so any previous
        stream is stopped when starting a new one. This should be done only when
        the siege is not active.

        :param audio: True to start audio, False otherwise
        :param video: True to start video, False otherwise
        """

        self.seleniumHelper.execute('await startMedia(' + ('true' if audio else 'false') + ', ' + ('true' if video else 'false') + ')')

    def closeConnections(self):
        """
        Stops the siege by closing the publisher and subscriber connections

        As the media stream is no longer needed it is also stopped.
        """

        self.seleniumHelper.execute('closeConnections()')

    def checkPublishersConnections(self):
        """
        Prints the state of the publisher connections.
        """

        self.seleniumHelper.execute('checkPublishersConnections()')

    def checkSubscribersConnections(self):
        """
        Prints the state of the subscriber connections.
        """

        self.seleniumHelper.execute('checkSubscribersConnections()')

    def setPublishersAndSubscribersCount(self, publishersCount, subscribersPerPublisherCount):
        """
        Sets the number of publishers and subscribers per publisher to use.

        If not explicitly set the default number from Talkbuchet.js is used,
        which is 5 publishers and 40 subscribers per publisher.

        :param publishersCount: the number of publishers.
        :param subscribersPerPublisherCount: the number of subscribers for each
            publisher.
        """

        self.publishersCount = publishersCount
        self.subscribersPerPublisherCount = subscribersPerPublisherCount

        self.seleniumHelper.execute('setPublishersAndSubscribersCount(' + str(publishersCount) + ', ' + str(subscribersPerPublisherCount) + ')')

    def setConnectionWarningTimeout(self, connectionWarningTimeout):
        """
        Sets the milliseconds to wait before warning about connection problems.

        A message is printed when a connection was not established after or has
        been disconnected for more than the given time. However, note that the
        message might not be printed in CLI until another command is executed.

        :param connectionWarningTimeout: the milliseconds to wait before
            warning about connection issues.
        """

        self.connectionWarningTimeout = connectionWarningTimeout

        self.seleniumHelper.execute('setConnectionWarningTimeout(' + str(connectionWarningTimeout) + ')')

    def siege(self):
        """
        Starts a siege.
        """

        savedScriptTimeout = self.seleniumHelper.driver.timeouts.script

        # Adjust script timeout to prevent it from ending before the siege has
        # started.
        scriptTimeout = (self.publishersCount + self.publishersCount * self.subscribersPerPublisherCount) * (self.connectionWarningTimeout / 1000)
        if scriptTimeout > savedScriptTimeout:
            self.seleniumHelper.driver.set_script_timeout(scriptTimeout)

        self.seleniumHelper.execute('await siege()')

        self.seleniumHelper.driver.set_script_timeout(savedScriptTimeout)
