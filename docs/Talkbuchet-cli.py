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
requires Selenium and certain Python packages to be available in the system). A
Bash script (Talkbuchet-run.sh) is provided to set up a Docker container with
Selenium, a web browser and all the needed Python dependencies for
Talkbuchet-cli.py.

Please refer to the documentation in Talkbuchet.js and Talkbuchet-run.sh for
information on Talkbuchet and on how to easily run it.

Documentation on the control functions provided by Talkbuchet-cli.py can be
printed while Talkbuchet-cli.py is running by calling "help(XXX)" (where XXX is
the function to get help about).

Talkbuchet-cli.py provides a wrapper class to start and interact with
Talkbuchet.js instances. Creating an object of the wrapper class launches a new
browser instance, opens the given Nextcloud URL and loads Talkbuchet.js on it.
After that the methods in the object call their counterpart in the wrapped
Talkbuchet.js instance; the wrapper objects provide full control of their
wrapped Talkbuchet.js instance.

Besides the helper classes Talkbuchet-cli.py also provides a set of global
functions to easily create several wrapper objects with common settings. Some
control functions are also provided, for example, to check the status of
connections during a siege, but in general the global functions only cover
creating and deleting the wrappers, and once created any specific action should
be executed on the wrapper objects themselves.

The values set using the global functions are not taken into account if a
Talkbuchet wrapper is manually created; they only affect the wrappers created
using the global functions. Moreover, existing Talkbuchet wrappers
already created by the global helper functions are not affected either, only
those created after the value was changed.

By default the browser instances will be launched in the local Selenium server.
A remote server can be used instead with:
>>>> setRemoteSeleniumUrl(THE-SELENIUM-SERVER-URL)

Independently of the server used, by default the browser will be launched in
headless mode. If the browser needs to be interacted with this can be disabled
with:
>>>> setHeadless(False)

Talkbuchet-cli.py supports launching Chrome and Firefox instances. Nevertheless,
note that the browser to be used also needs to be supported by the Selenium
server. The browser to be used needs to be explicitly set with:
>>>> setBrowser(THE-BROWSER-NAME)

A siege can be started in the following way:
>>>> setTarget('https://THE-NEXTCLOUD-DOMAIN')
>>>> setCredentials('THE-USER-ID', 'THE-APP-TOKEN')
>>>> setPublishersAndSubscribersCount(XXX, YYY)
>>>> startSiege()

Note that a conversation token does not need to be set, except if the server is
configured in conversation clustering mode.

Setting the publishers and subscribers count is not mandatory, but it is
recommended to adjust it based on the maximum number supported by the client
machine running the browser, as well as the values that need to be tested on the
server.

In any case, it is recommended to initially set a low number, for example
"setPublishersAndSubscribersCount(1, 2)", start a siege to verify that
everything works as expected, and then perform the real test.

Note that starting a new siege does not stop the previous one. That should be
explicitly done by calling "endSiege()". Starting several sieges instead of a
single siege with a higher number of publishers and subscribers could be needed
in powerful client machines that can handle more connections than what a single
browser is able to (for example, Chromium has a hardcoded limit on the maximum
number of concurrent connections).

Sieges are done with audio only connections by default. If a different media
needs to be used (either video but not audio, or both audio and video) this
needs to be specified (before starting the siege) with:
>>>> setMedia(CONNECT-WITH-AUDIO, CONNECT-WITH-VIDEO)

When a siege is active it is possible to check the state of the publisher
connections with "checkPublishersConnections()" and the state of the
subscriber connections with "checkSubscribersConnections()".

Global functions for additional actions, like enabling or disabling media
during the siege, are not provided. They must be directly called on the
Talkbuchet wrapper objects in the "sieges" list. For example:
>>>> sieges[0].setAudioEnabled(False)
"""

import atexit
import json
import threading
import websocket

from datetime import datetime
from pathlib import Path
from selenium import webdriver
from shutil import disk_usage
from time import sleep


class BiDiLogsHelper:
    """
    Helper class to get browser logs using the BiDi protocol.

    A new thread is started by each object to receive the logs, so they can be
    printed in real time even if the main thread is waiting for some script to
    finish.
    """

    def __init__(self, driver):
        if not 'webSocketUrl' in driver.capabilities:
            raise Exception('webSocketUrl not found in capabilities')

        self.realtimeLogsEnabled = False
        self.pendingLogs = []
        self.logsLock = threading.Lock()

        # Web socket connection is rejected by Firefox with "Bad request" if
        # "Origin" header is present; logs show:
        # "The handshake request has incorrect Origin header".
        self.websocket = websocket.create_connection(driver.capabilities['webSocketUrl'], suppress_origin=True)

        self.websocket.send(json.dumps({
            'id': 1,
            'method': 'session.subscribe',
            'params': {
                'events': ['log.entryAdded'],
            },
        }))

        self.initialLogsLock = threading.Lock()
        self.initialLogsLock.acquire()

        self.loggingThread = threading.Thread(target=self.__processLogEvents, daemon=True)
        self.loggingThread.start()

        # Do not return until the existing logs were fetched, except if it is
        # taking too long.
        self.initialLogsLock.acquire(timeout=10)

    def __del__(self):
        if self.websocket:
            self.websocket.close()

        if self.loggingThread:
            self.loggingThread.join()

    def __messageFromEvent(self, event):
            if not 'params' in event:
                return '???'

            method = ''
            if 'method' in event['params']:
                method = event['params']['method']
            elif 'level' in event['params']:
                method = event['params']['level'] if event['params']['level'] != 'warning' else 'warn'

            text = ''
            if 'text' in event['params']:
                text = event['params']['text']

            time = '??:??:??'
            if 'timestamp' in event['params']:
                timestamp = event['params']['timestamp']

                # JavaScript timestamps are millisecond based, Python timestamps
                # are second based.
                time = datetime.fromtimestamp(timestamp / 1000).strftime('%H:%M:%S')

            methodShort = '?'
            if method == 'error':
                methodShort = 'E'
            elif method == 'warn':
                methodShort = 'W'
            elif method == 'log':
                methodShort = 'L'
            elif method == 'info':
                methodShort = 'I'
            elif method == 'debug':
                methodShort = 'D'

            return time + ' ' + methodShort + ' ' + text

    def __processLogEvents(self):
        while True:
            try:
                event = json.loads(self.websocket.recv())
            except:
                print('BiDi WebSocket closed')
                return

            if 'id' in event and event['id'] == 1:
                self.initialLogsLock.release()
                continue

            if not 'method' in event or event['method'] != 'log.entryAdded':
                continue

            message = self.__messageFromEvent(event)

            with self.logsLock:
                if self.realtimeLogsEnabled:
                    print(message)
                else:
                    self.pendingLogs.append(message)

    def clearLogs(self):
        """
        Clears, without printing, the logs received while realtime logs were not
        enabled.
        """

        with self.logsLock:
            self.pendingLogs = []

    def printLogs(self):
        """
        Prints the logs received while realtime logs were not enabled.

        The logs are cleared after printing them.
        """

        with self.logsLock:
            for log in self.pendingLogs:
                print(log)

            self.pendingLogs = []

    def setRealtimeLogsEnabled(self, realtimeLogsEnabled):
        """
        Enable or disable realtime logs.

        If logs are received while realtime logs are not enabled they can be
        printed using "printLogs()".
        """

        with self.logsLock:
            self.realtimeLogsEnabled = realtimeLogsEnabled


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
        self.bidiLogsHelper = None

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

    def startFirefox(self, headless = True, remoteSeleniumUrl = None):
        """
        Starts a Firefox instance.

        :param headless: whether the browser will be started in headless mode or
            not; headless mode is used by default.
        :param remoteSeleniumUrl: the URL of the Selenium server to connect to;
            the local server is used by default.
        """

        options = webdriver.FirefoxOptions()

        # "webSocketUrl" is needed for BiDi; this should be set already by
        # default, but just in case.
        options.set_capability('webSocketUrl', True)
        # In Firefox < 101 BiDi protocol was not enabled by default, although it
        # works fine for getting the logs with Firefox 99, so it is explicitly
        # enabled.
        # https://bugzilla.mozilla.org/show_bug.cgi?id=1753997
        options.set_preference('remote.active-protocols', 3)

        options.set_preference('media.navigator.permission.disabled', True)
        options.set_preference('media.navigator.streams.fake', True)

        # Headless mode uses a little less memory on each instance.
        options.headless = headless

        if remoteSeleniumUrl:
            self.driver = webdriver.Remote(
                command_executor=remoteSeleniumUrl,
                options=options
            )
        else:
            if disk_usage('/tmp').free < 134217728:
                print('Warning: less than 128 MiB available in "/tmp", strange failures may occur')

            self.driver = webdriver.Firefox(
                options=options
            )

        self.bidiLogsHelper = BiDiLogsHelper(self.driver)

    def clearLogs(self):
        """
        Clears browser logs not printed yet.

        This does not affect the logs in the browser itself, only the ones
        received by the SeleniumHelper.
        """

        if self.bidiLogsHelper:
            self.bidiLogsHelper.clearLogs()
            return

        self.driver.get_log('browser')

    def printLogs(self):
        """
        Prints browser logs received since last print.

        These logs do not include realtime logs, as they are printed as soon as
        they are received.
        """

        if self.bidiLogsHelper:
            self.bidiLogsHelper.printLogs()
            return

        for log in self.driver.get_log('browser'):
            print(log['message'])

    def execute(self, script):
        """
        Executes the given script.

        If the script contains asynchronous code "executeAsync()" should be used
        instead to properly wait until the asynchronous code finished before
        returning.

        Technically Chrome (unlike Firefox) works as expected with something
        like "execute('await someFunctionCall(); await anotherFunctionCall()'",
        but "executeAsync" has to be used instead for something like
        "someFunctionReturningAPromise().then(() => { more code })").

        If realtime logs are available logs are printed as soon as they are
        received. Otherwise they will be printed once the script has finished.
        """

        # Real time logs are enabled while the command is being executed.
        if self.bidiLogsHelper:
            self.printLogs()
            self.bidiLogsHelper.setRealtimeLogsEnabled(True)

        self.driver.execute_script(script)

        if self.bidiLogsHelper:
            # Give it some time to receive the last real time logs before
            # disabling them again.
            sleep(0.5)

            self.bidiLogsHelper.setRealtimeLogsEnabled(False)

        self.printLogs()

    def executeAsync(self, script):
        """
        Executes the given script asynchronously.

        This function should be used to execute JavaScript code that needs to
        wait for a promise to be fulfilled, either explicitly or through "await"
        calls.

        The script needs to explicitly signal that the execution has finished by
        including the special text "{RETURN}" (without quotes). If "{RETURN}" is
        not included the function will automatically return once all the root
        statements of the script were executed (which works as expected if using
        "await" calls, but not if the script includes something like
        "someFunctionReturningAPromise().then(() => { more code })"; in that
        case the script should be written as
        "someFunctionReturningAPromise().then(() => { more code {RETURN} })").

        If realtime logs are available logs are printed as soon as they are
        received. Otherwise they will be printed once the script has finished.
        """

        # Real time logs are enabled while the command is being executed.
        if self.bidiLogsHelper:
            self.printLogs()
            self.bidiLogsHelper.setRealtimeLogsEnabled(True)

        # Add an explicit return point at the end of the script if none is
        # given.
        if script.find('{RETURN}') == -1:
            script += '{RETURN}'

        # await is not valid in the root context in Firefox, so the script to be
        # executed needs to be wrapped in an async function.
        script = '(async() => { ' + script  + ' })().catch(error => { console.error(error) {RETURN} })'

        # Asynchronous scripts need to explicitly signal that they are finished
        # by invoking the callback injected as the last argument.
        # https://www.selenium.dev/documentation/legacy/json_wire_protocol/#sessionsessionidexecute_async
        script = script.replace('{RETURN}', '; arguments[arguments.length - 1]()')

        self.driver.execute_async_script(script)

        if self.bidiLogsHelper:
            # Give it some time to receive the last real time logs before
            # disabling them again.
            sleep(0.5)

            self.bidiLogsHelper.setRealtimeLogsEnabled(False)

        self.printLogs()


class Talkbuchet:
    """
    Wrapper for Talkbuchet.

    Talkbuchet wrappers load Talkbuchet on a given Nextcloud URL and provide
    methods to call the different Talkbuchet functions in the browser.
    """

    def __init__(self, browser, nextcloudUrl, headless = True, remoteSeleniumUrl = None):
        """
        Loads Talkbuchet on the given Nextcloud URL using the given browser.

        :param browser: "firefox" or "chrome".
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

        if browser == 'chrome':
            self.seleniumHelper.startChrome(headless, remoteSeleniumUrl)
        elif browser == 'firefox':
            self.seleniumHelper.startFirefox(headless, remoteSeleniumUrl)
        else:
            raise Exception('Invalid browser: ' + browser)

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

        self.seleniumHelper.executeAsync(talkbuchet)

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

        self.seleniumHelper.executeAsync('await startMedia(' + ('true' if audio else 'false') + ', ' + ('true' if video else 'false') + ')')

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

        self.seleniumHelper.executeAsync('await siege()')

        self.seleniumHelper.driver.set_script_timeout(savedScriptTimeout)


_browser = ''

_nextcloudUrl = ''
_remoteSeleniumUrl = ''
_headless = True

_user = ''
_appToken = ''

_token = ''

_audio = False
_video = False

def setBrowser(browser):
    """
    Sets the browser to use.

    Supported browsers are "chrome" and "firefox"; the browser needs to be
    available in the Selenium server.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param browser: "chrome" or "firefox".
    """

    if browser != 'chrome' and browser != 'firefox':
        print('Browser value not valid. Allowed values: "chrome" or "firefox"')
        return

    global _browser
    _browser = browser

def setTarget(nextcloudUrl):
    """
    Sets the Nextcloud URL to use.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param nextcloudUrl: the URL of the Nextcloud instance to test against
        (for example, "https://cloud.mydomain.com").
    """

    global _nextcloudUrl
    _nextcloudUrl = nextcloudUrl

def setHeadless(headless):
    """
    Sets whether the browsers will be started in headless mode or not.

    By default browsers are started in headless mode, as each instance uses a
    little less memory.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param headless: True for headless mode, False otherwise.
    """

    global _headless
    _headless = headless

def setRemoteSeleniumUrl(remoteSeleniumUrl):
    """
    Sets the URL of the remote Selenium server to use.

    By default the local Selenium server is used.

    When a remote Selenium server is used its session timeout (which is
    independent from the timeouts set in the driver) must be kept in mind, as it
    can cause the browser to "unexpectedly" close. Also note that each
    Talkbuchet wrapper will use its own browser instance, so the remote Selenium
    server should have enough available sessions for all the instances running
    at the same time.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param remoteSeleniumUrl: the URL of the remote Selenium server, or None to
        use the local one.
    """

    global _remoteSeleniumUrl
    _remoteSeleniumUrl = remoteSeleniumUrl

def setCredentials(user, appToken):
    """
    Sets the credentials to use.

    An app token/password can be generated in the Security section of the
    personal settings (index.php/settings/user/security).

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param user: the user ID.
    :param appToken: the app token for the user.
    """

    global _user, _appToken
    _user = user
    _appToken = appToken

def setToken(token):
    """
    Sets the conversation token to use.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param token: the conversation token.
    """

    global _token
    _token = token

def setMedia(audio, video):
    """
    Sets the media to be started in the Talkbuchet wrappers.

    By default audio will be used. Note that audio will be used too even if both
    audio and video are disabled, as some media needs to be published.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param audio: True to start audio, False otherwise
    :param video: True to start video, False otherwise
    """

    global _audio, _video
    _audio = audio
    _video = video


_publishersCount = None
_subscribersPerPublisherCount = None

sieges = []

def _isValidConfiguration():
    if not _browser:
        print("Set browser first")
        return False

    if not _nextcloudUrl:
        print("Set target Nextcloud URL first")
        return False

    if not _user or not _appToken:
        print("Set credentials (user and app token) first")
        return False

    return True

def setPublishersAndSubscribersCount(publishersCount, subscribersPerPublisherCount):
    """
    Sets the number of publishers and subscribers per publisher to use.

    By the default the number from Talkbuchet.js is used, which is 5 publishers
    and 40 subscribers per publisher.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param publishersCount: the number of publishers.
    :param subscribersPerPublisherCount: the number of subscribers for each
        publisher.
    """

    global _publishersCount, _subscribersPerPublisherCount
    _publishersCount = publishersCount
    _subscribersPerPublisherCount = subscribersPerPublisherCount

def startSiege():
    """
    Starts a siege.

    The global target Nextcloud URL and credentials need to be set first.

    If global token, media or publishers and subscribers count were set they
    will be applied to the siege.

    Note that changing any of those values later will have no effect on a
    running siege, the updated value will be used only on sieges started after
    they were changed.

    If there is already a running siege it will not be ended when a new one is
    started; the new one will run along the previous one.
    """

    if not _isValidConfiguration():
        return

    siege = Siege(_browser, _nextcloudUrl, _headless, _remoteSeleniumUrl)

    sieges.append(siege)

    siege.setCredentials(_user, _appToken)

    if _token:
        siege.setToken(_token)

    if _audio or _video:
        siege.startMedia(_audio, _video)

    if _publishersCount != None and _subscribersPerPublisherCount != None:
        siege.setPublishersAndSubscribersCount(_publishersCount, _subscribersPerPublisherCount)

    siege.siege()

def _getSiegeIndex(index = None):
    if not sieges:
        return -1

    if index == None and len(sieges) > 1:
        print("Index needs to be specified")
        return -1

    if index == None and len(sieges) == 1:
        index = 0

    if index < 0 or index >= len(sieges):
        print("Index out of range")
        return -1

    return index

def checkPublishersConnections(index = None):
    """
    Checks the publisher connections of the siege with the given index.

    If a single siege is active the index does not need to be specified.

    :param index: the index in :py:data:`sieges` of the siege to check its
        publisher connections.
    """

    index = _getSiegeIndex(index)
    if index < 0:
        return

    sieges[index].checkPublishersConnections()

def checkSubscribersConnections(index = None):
    """
    Checks the subscriber connections of the siege with the given index.

    If a single siege is active the index does not need to be specified.

    :param index: the index in :py:data:`sieges` of the siege to check its
        subscriber connections.
    """

    index = _getSiegeIndex(index)
    if index < 0:
        return

    sieges[index].checkSubscribersConnections()

def endSiege(index = None):
    """
    Ends the siege with the given index.

    If a single siege is active the index does not need to be specified.

    :param index: the index in :py:data:`sieges` of the siege to remove.
    """

    index = _getSiegeIndex(index)
    if index < 0:
        return

    sieges[index].closeConnections()
    del sieges[index]


def _deleteTalkbuchetInstancesOnExit():
    while sieges:
        del sieges[0]

# Talkbuchet instances should be explicitly deleted before exiting, as if they
# are implicitly deleted while exiting the Selenium driver may not cleanly quit.
atexit.register(_deleteTalkbuchetInstancesOnExit)

print('Full documentation can be shown by calling help(__name__)')
