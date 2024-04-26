#
# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
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

Talkbuchet-cli.py provides wrapper classes to start and interact with
Talkbuchet.js instances. Creating an object of a wrapper class launches a new
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
server. When Talkbuchet-cli.py was started through Talkbuchet-run.sh and a
remote Selenium server is not set the available browser in the container will be
automatically used. Otherwise the browser to be used needs to be explicitly set
with:
>>>> setBrowser(THE-BROWSER-NAME)

Talkbuchet-cli.py supports both the siege and virtual participant modes of
Talkbuchet. Although there are a few common functions each mode has its own set
of specific functions, so there are separate wrapper classes and global
functions for each mode. Switching between the two modes can be done by calling
"switchToSiegeMode()" and "switchToVirtualParticipantMode()". By default
Talkbuchet-cli.py starts in siege mode.

The documentation for each specific mode can be shown with
"help(switchToSiegeMode)" and "help(switchToVirtualParticipantMode)". Note that
there are some slight differences in the behaviour between Firefox and Chrome.
Chrome has a hardcoded limit in the number of connections that can be created,
and it does not properly clean them once closed, so it could be less suitable
for sieges where a high number of connections are typically required.
Nevertheless, this could be overcomed by creating several smaller sieges at the
same time rather than a single, larger one. On the other hand, Firefox requires
more resources for each browser instance than Chrome, so it could be less
suitable for virtual participants where a high number of browser instances are
typically required. But of course this might not be a problem if the system
running the browser has enough resources.

Regarding the sent media, Firefox sends a continuous beep for audio, while
Chrome sends a short beep every ~500ms. Firefox sends more audio data (~11 kBps)
than Chrome (~4 kBps), and also uses more CPU in the system running the browser
when sending and receiving audio (specially on large sieges with a high number
of connections). On the other hand, Firefox uses slightly less CPU than Chrome
in the system running the browser when receiving video, although more when
sending it; Firefox sends a changing colour animation for video (640x480x30FPS,
~40 kBps), while Chrome sends an animation with the time since the video started
and a one second "clock" (640x480x20FPS, ~64 kBps).

Besides the siege and virtual participant modes Talkbuchet-cli.py provides an
additional mode, real participant, that is not part of Talkbuchet itself. This
mode can be used to open browser instances and join the conversation and/or the
call, exactly as it would be done by a real participant. However, please note
that this mode is meant only for developing purposes; load/stress testing should
be done with the other two modes, as the number of participants that can be
simulated with them in the system running the test is much higher.

Unlike the other modes, the real participant mode does not require an HPB server
to be configured in Nextcloud Talk.
"""

import atexit
import json
import threading
import websocket

from datetime import datetime
from pathlib import Path
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
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

        # Headless mode uses a little less memory on each instance, so it is
        # specially useful when there are several virtual participants.
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

        # Headless mode uses a little less memory on each instance, so it is
        # specially useful when there are several virtual participants.
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


class TalkbuchetCommon:
    """
    Base class for Talkbuchet wrappers.

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
        window.getPublishers = getPublishers
        window.getSubscribers = getSubscribers
        window.closeConnections = closeConnections
        window.setAudioEnabled = setAudioEnabled
        window.setVideoEnabled = setVideoEnabled
        window.setSentAudioStreamEnabled = setSentAudioStreamEnabled
        window.setSentVideoStreamEnabled = setSentVideoStreamEnabled
        window.checkPublishersConnections = checkPublishersConnections
        window.checkSubscribersConnections = checkSubscribersConnections
        window.printPublisherStats = printPublisherStats
        window.printSubscriberStats = printSubscriberStats
        window.setCredentials = setCredentials
        window.setToken = setToken
        window.setPublishersAndSubscribersCount = setPublishersAndSubscribersCount
        window.startMedia = startMedia
        window.setConnectionWarningTimeout = setConnectionWarningTimeout
        window.siege = siege
        window.getVirtualParticipant = getVirtualParticipant
        window.startVirtualParticipant = startVirtualParticipant
        window.stopVirtualParticipant = stopVirtualParticipant
        window.sendMediaEnabledStateThroughDataChannel = sendMediaEnabledStateThroughDataChannel
        window.sendSpeakingStateThroughDataChannel = sendSpeakingStateThroughDataChannel
        window.sendNickThroughDataChannel = sendNickThroughDataChannel
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

        In siege mode the credentials always need to be set.

        In virtual participant mode the participant will be a guest if the
        credentials are not set.

        :param user: the user ID.
        :param appToken: the app token for the user.
        """

        self.seleniumHelper.execute('setCredentials(\'' + user + '\', \'' + appToken + '\')')

    def setToken(self, token):
        """
        Sets the conversation token to use.

        In siege mode this should be set only when conversation clustering is
        enabled in the server.

        In virtual participant mode the token always needs to be set.

        :param token: the conversation token.
        """

        self.seleniumHelper.execute('setToken(\'' + token + '\')')

    def startMedia(self, audio, video):
        """
        Starts the media stream to be used by publishers (including virtual
        participants).

        By default in siege mode audio will be used, and in virtual participant
        mode neither audio nor video will be used.

        Only one media stream can be active at the same time, so any previous
        stream is stopped when starting a new one. This should be done only when
        the siege or the virtual participant is not active.

        If both audio and video are False then there will be no media. This is
        only allowed in virtual participant mode, but not in siege mode.

        :param audio: True to start audio, False otherwise
        :param video: True to start video, False otherwise
        """

        self.seleniumHelper.executeAsync('await startMedia(' + ('true' if audio else 'false') + ', ' + ('true' if video else 'false') + ')')


class Siege(TalkbuchetCommon):
    """
    Wrapper for Talkbuchet in siege mode.

    Besides the common functions this wrapper exposes only the Talkbuchet
    functions for siege mode.
    """

    def __init__(self, browser, nextcloudUrl, headless = True, remoteSeleniumUrl = None):
        """
        See :py:meth:`TalkbuchetCommon.__init__`.
        """

        super().__init__(browser, nextcloudUrl, headless, remoteSeleniumUrl)

        # Set default values from Talkbuchet.js.
        self.publishersCount = 5
        self.subscribersPerPublisherCount = 40
        self.connectionWarningTimeout = 5000

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

    def printPublisherStats(self, publisherSessionId):
        """
        Prints the stats of the given publisher connection.

        :param publisherSessionId: the session ID of the publisher.
        """

        self.seleniumHelper.executeAsync('await printPublisherStats(\'' + publisherSessionId + '\', true)')

    def printSubscriberStats(self, index):
        """
        Prints the stats of the given subscriber connection.

        :param index: the index of the subscriber in the list of subscribers.
        """

        self.seleniumHelper.executeAsync('await printSubscriberStats(' + str(index) + ', true)')

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


class VirtualParticipant(TalkbuchetCommon):
    """
    Wrapper for Talkbuchet in virtual participant mode.

    Besides the common functions this wrapper exposes only the Talkbuchet
    functions for virtual participant mode.
    """

    def __init__(self, browser, nextcloudUrl, headless = True, remoteSeleniumUrl = None):
        """
        See :py:meth:`TalkbuchetCommon.__init__`.
        """

        super().__init__(browser, nextcloudUrl, headless, remoteSeleniumUrl)

    def startVirtualParticipant(self):
        """
        Starts the virtual participant.
        """

        self.seleniumHelper.executeAsync('await startVirtualParticipant()')

    def stopVirtualParticipant(self):
        """
        Stops the virtual participant.
        """

        self.seleniumHelper.executeAsync('await stopVirtualParticipant()')

    def sendMediaEnabledStateThroughDataChannel(self, mediaType, enabled):
        """
        Sends the enabled state of the media using a data channel message.

        :param mediaType: "audio" or "video".
        :param enabled: True or False.
        """

        self.seleniumHelper.execute('sendMediaEnabledStateThroughDataChannel(\'' + mediaType + '\', ' + ('true' if enabled else 'false') + ')')

    def sendSpeakingStateThroughDataChannel(self, speaking):
        """
        Sends the speaking state using a data channel message.

        :param speaking: True for speaking, False for not speaking.
        """

        self.seleniumHelper.execute('sendSpeakingStateThroughDataChannel(' + ('true' if speaking else 'false') + ')')

    def sendNickThroughDataChannel(self, nick):
        """
        Sends the nick of the participant using a data channel message.

        :param nick: the nick to send.
        """

        self.seleniumHelper.execute('sendNickThroughDataChannel(\'' + nick + '\')')


class RealParticipant():
    """
    Wrapper for Talkbuchet in real participant mode.

    This wrapper exposes functions to use a real participant in a browser.
    """

    def __init__(self, browser, nextcloudUrl, headless = True, remoteSeleniumUrl = None):
        """
        Starts a real participant in the given Nextcloud URL using the given
        browser.

        :param browser: "firefox" or "chrome".
        :param nextcloudUrl: the URL of the Nextcloud instance to start the real
            participant in.
        :param headless: whether the browser will be started in headless mode or
            not; headless mode is used by default.
        :param remoteSeleniumUrl: the URL of the Selenium server to connect to;
            the local server is used by default.
        """

        self.nextcloudUrl = nextcloudUrl

        self.seleniumHelper = SeleniumHelper()

        if browser == 'chrome':
            self.seleniumHelper.startChrome(headless, remoteSeleniumUrl)
        elif browser == 'firefox':
            self.seleniumHelper.startFirefox(headless, remoteSeleniumUrl)
        else:
            raise Exception('Invalid browser: ' + browser)

        self.seleniumHelper.driver.get(nextcloudUrl)

    def login(self, user, appToken):
        """
        Logs in Nextcloud as the given user with the given app token.

        :param user: the ID of the user to log as.
        :param appToken: an app token of the user.
        """

        # Fetching a Nextcloud URL in the browser console with a user and an app
        # token implicitly does a login with that user. Visiting any page in the
        # Nextcloud server will be done as a logged in user after that.
        self.seleniumHelper.executeAsync('''
            const fetchOptions = {
                headers: {
                    'Authorization': 'Basic ' + btoa(\'''' + user + ':' + appToken + '''\'),
                },
            }

            await fetch(\'''' + self.nextcloudUrl + '''\', fetchOptions)
        ''')

    def joinRoom(self, token):
        """
        Joins the room with the given token.

        If no login was done before the participant will join as a guest.

        :param token: the token of the room to join.
        """

        self.seleniumHelper.driver.get(self.nextcloudUrl + '/call/' + token)

    def joinCall(self):
        """
        Joins (or starts) the call in the current room.

        A room must have been joined before joining the call.
        """

        self.seleniumHelper.driver.find_element(By.CSS_SELECTOR, '.top-bar #call_button').click()

        try:
            # If the device selector is shown click on the "Join call" button
            # in the dialog to actually join the call.
            WebDriverWait(self.seleniumHelper.driver, timeout=5).until(lambda driver: driver.find_element(By.CSS_SELECTOR, '.device-checker #call_button'))
            self.seleniumHelper.driver.find_element(By.CSS_SELECTOR, '.device-checker #call_button').click()
        except:
            pass

    def leaveCall(self):
        """
        Leaves the current call.

        The call must have been joined first.
        """

        self.seleniumHelper.driver.find_element(By.CSS_SELECTOR, '.top-bar #call_button').click()


_talkbuchetMode = ''

_browser = ''
_browserDefault = ''

_nextcloudUrl = ''
_remoteSeleniumUrl = ''
_headless = True

_user = ''
_appToken = ''

_token = ''

_audio = False
_video = False

def _isValidBrowser():
    if not _browser:
        print("Set browser first")
        return False

    if _remoteSeleniumUrl and _browser == 'default':
        print("Set an explicit browser name to be used in the remote Selenium instance")
        return False

    if _browser == 'default' and not _browserDefault:
        print("Set an explicit browser name, no default browser found")
        return False

    return True

def _findDefaultBrowser():
    global _browserDefault

    # Try to get the browser from the Selenium Docker image.
    try:
        _browserDefault = Path('/opt/selenium/browser_name').read_text().strip()
    except:
        pass

    if _browserDefault:
        setBrowser('default')
    else:
        print('No default browser found, please set the browser to use with setBrowser("chrome") or setBrowser("firefox")')

def _getBrowser():
    if _browser == 'chrome' or (_browser == 'default' and _browserDefault == 'chrome'):
        return 'chrome'

    return 'firefox'

def setBrowser(browser):
    """
    Sets the browser to use.

    Supported browsers are "chrome" and "firefox"; the browser needs to be
    available in the Selenium server.

    The special value "default" (which is the default value) can be set to try
    to find which is the default browser in the Selenium server and use it
    without having to specify it.

    This is used only for the global helper functions and is not taken into
    account if a Talkbuchet wrapper is manually created.

    :param browser: "default", "chrome" or "firefox".
    """

    if browser != 'default' and browser != 'chrome' and browser != 'firefox':
        print('Browser value not valid. Allowed values: "default", "chrome" or "firefox"')
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
    at the same time (for example, if several virtual participants are used).

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

    By default in siege mode audio will be used, and in virtual participant
    mode neither audio nor video will be used.

    Note that audio will be used too in siege mode even if both audio and video
    are disabled, as some media needs to be published.

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

def switchToSiegeMode():
    """
    Sets the siege mode as the active one.

    This adjusts the global helper functions to those relevant for this mode
    (so, for example, there will be no function to add a virtual participant).

    A siege can be started in the following way:
    >>>> setTarget('https://THE-NEXTCLOUD-DOMAIN')
    >>>> setCredentials('THE-USER-ID', 'THE-APP-TOKEN')
    >>>> setPublishersAndSubscribersCount(XXX, YYY)
    >>>> startSiege()

    Note that a conversation token does not need to be set, except if the server
    is configured in conversation clustering mode.

    Setting the publishers and subscribers count is not mandatory, but it is
    recommended to adjust it based on the maximum number supported by the
    client machine running the browser, as well as the values that need to be
    tested on the server.

    In any case, it is recommended to initially set a low number, for example
    "setPublishersAndSubscribersCount(1, 2)", start a siege to verify that
    everything works as expected, and then perform the real test.

    Note that starting a new siege does not stop the previous one. That should
    be explicitly done by calling "endSiege()". Starting several sieges instead
    of a single siege with a higher number of publishers and subscribers could
    be needed in powerful client machines that can handle more connections than
    what a single browser is able to (for example, Chromium has a hardcoded
    limit on the maximum number of concurrent connections).

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

    def _isValidConfiguration():
        if not _isValidBrowser():
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

        By the default the number from Talkbuchet.js is used, which is 5
        publishers and 40 subscribers per publisher.

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
        running siege, the updated value will be used only on sieges started
        after they were changed.

        If there is already a running siege it will not be ended when a new one
        is started; the new one will run along the previous one.
        """

        if not _isValidConfiguration():
            return

        siege = Siege(_getBrowser(), _nextcloudUrl, _headless, _remoteSeleniumUrl)

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

    if globals()['_talkbuchetMode'] == 'virtualParticipant':
        if removeVirtualParticipants:
            removeVirtualParticipants()

        del globals()['prepareVirtualParticipant']
        del globals()['prepareVirtualParticipants']
        del globals()['startVirtualParticipants']
        del globals()['startVirtualParticipantsParallel']
        del globals()['stopVirtualParticipants']
        del globals()['stopVirtualParticipantsParallel']
        del globals()['addVirtualParticipant']
        del globals()['addVirtualParticipants']
        del globals()['removeVirtualParticipant']
        del globals()['removeVirtualParticipants']

    if globals()['_talkbuchetMode'] == 'realParticipant':
        if removeRealParticipants:
            removeRealParticipants()

        del globals()['addRealParticipant']
        del globals()['addRealParticipants']
        del globals()['removeRealParticipant']
        del globals()['removeRealParticipants']

    globals()['setPublishersAndSubscribersCount'] = setPublishersAndSubscribersCount
    globals()['startSiege'] = startSiege
    globals()['checkPublishersConnections'] = checkPublishersConnections
    globals()['checkSubscribersConnections'] = checkSubscribersConnections
    globals()['endSiege'] = endSiege

    globals()['_talkbuchetMode'] = 'siege'


virtualParticipants = []

def switchToVirtualParticipantMode():
    """
    Sets the virtual participant mode as the active one.

    This adjusts the global helper functions to those relevant for this mode
    (so, for example, there will be no function to start a siege).

    Virtual participants can be added to a call in the following way:
    >>>> setTarget('https://THE-NEXTCLOUD-DOMAIN')
    >>>> setToken('THE-CONVERSATION-TOKEN')
    >>>> addVirtualParticipants(NUMBER-OF-VIRTUAL-PARTICIPANTS)

    If no credentials are set the added participants will be guests. To add a
    registered user (note that the same user can be added several times) set the
    credentials first before adding the virtual participants:
    >>>> setCredentials('THE-USER-ID', 'THE-APP-TOKEN')

    If no media is explicitly set virtual participants will join without media.
    To join with specific media set the desired type first before adding the
    virtual participants:
    >>>> setMedia(JOIN-WITH-AUDIO, JOIN-WITH-VIDEO)

    Note that adding a new participant does not remove the previous ones. That
    should be explicitly done by calling "removeVirtualParticipants()" (or
    "removeVirtualParticipant(INDEX)" to remove just a specific participant).
    Therefore, it is possible to add several participants with different
    parameters (like several guests and then several users, or participants with
    and without media) by setting the parameters, calling
    "addVirtualParticipants(NUMBER)", setting the new parameters and calling
    "addVirtualParticipants(NUMBER)" again.

    In case joining/leaving a call should be tested, it makes sense to use
    "prepareVirtualParticipant()" / "prepareVirtualParticipants(NUMBER)" and
    then start them with "startVirtualParticipants()" and stop them with
    "stopVirtualParticipants()". In case joining/leaving should be done in
    parallel one can use "startVirtualParticipantsParallel()" and
    "stopVirtualParticipantsParallel()".

    Global functions provided for virtual participants only cover adding and
    removing them. Any specific action, like enabling or disabling media of a
    virtual participant, must be directly called on the Talkbuchet wrapper
    objects in the "virtualParticipants" list. For example:
    >>>> virtualParticipants[0].setAudioEnabled(False)

    Note that clients may not show any nick for the virtual participants unless
    explicitly given, even if the virtual participant is a registered user. The
    nick for a specific virtual participant can be set with:
    >>>> virtualParticipants[INDEX].sendNickThroughDataChannel(NICK)
    """

    def _isValidConfiguration():
        if not _isValidBrowser():
            return False

        if not _nextcloudUrl:
            print("Set target Nextcloud URL first")
            return False

        if not _token:
            print("Set conversation token first")
            return False

        return True

    def prepareVirtualParticipant():
        """
        Prepares a single virtual participant.

        The global target Nextcloud URL and conversation token need to be set
        first.

        If global credentials or media were set they will be applied to the
        virtual participant.

        Note that changing any of those values later will have no effect on an
        existing virtual participant, the updated value will be used only by
        virtual participants added after they were changed.

        This method just prepares a virtual participant, but does not start it.
        See :py:func:`addVirtualParticipant` as an alternative.
        """

        if not _isValidConfiguration():
            return

        virtualParticipant = VirtualParticipant(_getBrowser(), _nextcloudUrl, _headless, _remoteSeleniumUrl)

        virtualParticipants.append(virtualParticipant)

        virtualParticipant.setToken(_token)

        if _user or _appToken:
            virtualParticipant.setCredentials(_user, _appToken)

        if _audio or _video:
            virtualParticipant.startMedia(_audio, _video)

        return virtualParticipant

    def prepareVirtualParticipants(count):
        """
        Prepares as many virtual participants as the given count.

        See :py:func:`prepareVirtualParticipant`.

        :param count: the number of virtual participants to prepare.
        """

        if not _isValidConfiguration():
            return

        for i in range(count):
            prepareVirtualParticipant()

            print('.', end='', flush=True)

        print("")

    def startVirtualParticipants():
        """
        Starts all virtual participants which are prepared.

        Note that there is no check if a virtual participant was already started
        before. The result of starting again a virtual participant before
        stopping it first is undefined, no matter if the virtual participant was
        started with any of the "startVirtualParticipants" or
        "addVirtualParticipant" variants.

        See :py:func:`prepareVirtualParticipant`.
        """
        for virtualParticipant in virtualParticipants:
            virtualParticipant.startVirtualParticipant()

    def startVirtualParticipantsParallel():
        """
        Starts all virtual participants.

        Same as :py:func:`startVirtualParticipants`, but starting each virtual
        participant in parallel.

        This method returns before the virtual participants were fully started,
        so it should be ensured that starting them finished before starting more
        virtual participants or stopping them.
        """

        for virtualParticipant in virtualParticipants:
            startThread = threading.Thread(target=virtualParticipant.startVirtualParticipant)
            startThread.start()

    def stopVirtualParticipants():
        """
        Stops all virtual participants.

        The participants are not removed and can be started again.
        See :py:func:`prepareVirtualParticipant`.

        Note that there is no check if a virtual participant was started/stopped
        before.

        See :py:func:`prepareVirtualParticipant`.
        """

        for virtualParticipant in virtualParticipants:
            virtualParticipant.stopVirtualParticipant()

    def stopVirtualParticipantsParallel():
        """
        Stops all virtual participants.

        Same as :py:func:`stopVirtualParticipants`, but stopping each virtual
        participant in parallel.

        This method returns before the virtual participants were fully stopped,
        so it should be ensured that stopping them finished before starting or
        stopping them again.
        """

        for virtualParticipant in virtualParticipants:
            stopThread = threading.Thread(target=virtualParticipant.stopVirtualParticipant)
            stopThread.start()

    def addVirtualParticipant():
        """
        Adds a single virtual participant.

        The global target Nextcloud URL and conversation token need to be set
        first.

        If global credentials or media were set they will be applied to the
        virtual participant.

        Note that changing any of those values later will have no effect on an
        existing virtual participant, the updated value will be used only by
        virtual participants added after they were changed.

        This method prepares a participant and immediately starts it.
        """

        if not _isValidConfiguration():
            return

        virtualParticipant = prepareVirtualParticipant()

        virtualParticipant.startVirtualParticipant()

    def addVirtualParticipants(count):
        """
        Adds as many virtual participants as the given count.

        See :py:func:`addVirtualParticipant`.

        :param count: the number of virtual participants to add.
        """

        if not _isValidConfiguration():
            return

        for i in range(count):
            addVirtualParticipant()

            print('.', end='', flush=True)

        print("")

    def removeVirtualParticipant(index):
        """
        Removes the virtual participant with the given index.

        :param index: the index in :py:data:`virtualParticipants` of the virtual
            participant to remove.
        """

        if index < 0 or index >= len(virtualParticipants):
            print("Index out of range")
            return

        virtualParticipants[index].stopVirtualParticipant()
        del virtualParticipants[index]

    def removeVirtualParticipants():
        """
        Removes all the virtual participants previously added.
        """

        while virtualParticipants:
            removeVirtualParticipant(0)

    if globals()['_talkbuchetMode'] == 'siege':
        if endSiege:
            endSiege()

        del globals()['setPublishersAndSubscribersCount']
        del globals()['startSiege']
        del globals()['checkPublishersConnections']
        del globals()['checkSubscribersConnections']
        del globals()['endSiege']

    if globals()['_talkbuchetMode'] == 'realParticipant':
        if removeRealParticipants:
            removeRealParticipants()

        del globals()['addRealParticipant']
        del globals()['addRealParticipants']
        del globals()['removeRealParticipant']
        del globals()['removeRealParticipants']

    globals()['prepareVirtualParticipant'] = prepareVirtualParticipant
    globals()['prepareVirtualParticipants'] = prepareVirtualParticipants
    globals()['startVirtualParticipants'] = startVirtualParticipants
    globals()['startVirtualParticipantsParallel'] = startVirtualParticipantsParallel
    globals()['stopVirtualParticipants'] = stopVirtualParticipants
    globals()['stopVirtualParticipantsParallel'] = stopVirtualParticipantsParallel
    globals()['addVirtualParticipant'] = addVirtualParticipant
    globals()['addVirtualParticipants'] = addVirtualParticipants
    globals()['removeVirtualParticipant'] = removeVirtualParticipant
    globals()['removeVirtualParticipants'] = removeVirtualParticipants

    globals()['_talkbuchetMode'] = 'virtualParticipant'


realParticipants = []

def switchToRealParticipantMode():
    """
    Sets the real participant mode as the active one.

    This adjusts the global helper functions to those relevant for this mode
    (so, for example, there will be no function to start a siege).

    Real participants can be added to a conversation in the following way:
    >>>> setTarget('https://THE-NEXTCLOUD-DOMAIN')
    >>>> setToken('THE-CONVERSATION-TOKEN')
    >>>> addRealParticipants(NUMBER-OF-REAL-PARTICIPANTS)

    If no credentials are set the added participants will be guests. To add a
    registered user (note that the same user can be added several times) set the
    credentials first before adding the real participants:
    >>>> setCredentials('THE-USER-ID', 'THE-APP-TOKEN')

    Note that adding a new participant does not remove the previous ones. That
    should be explicitly done by calling "removeRealParticipants()" (or
    "removeRealParticipant(INDEX)" to remove just a specific participant).
    Therefore, it is possible to add several participants with different
    parameters (like several guests and then several users) by setting the
    parameters, calling "addRealParticipants(NUMBER)", setting the new
    parameters and calling "addRealParticipants(NUMBER)" again.

    Global functions provided for real participants only cover joining and
    leaving the conversation. Joining the call must be directly done on the
    Talkbuchet wrapper objects in the "realParticipants" list with:
    >>>> realParticipants[INDEX].joinCall()
    """

    def _isValidConfiguration():
        if not _isValidBrowser():
            return False

        if not _nextcloudUrl:
            print("Set target Nextcloud URL first")
            return False

        if not _token:
            print("Set conversation token first")
            return False

        return True

    def addRealParticipant():
        """
        Adds a single real participant.

        The global target Nextcloud URL and conversation token need to be set
        first.

        If global credentials were set the user will be logged in with them.

        Note that changing any of those values later will have no effect on an
        existing real participant, the updated value will be used only by real
        participants added after they were changed.
        """

        if not _isValidConfiguration():
            return

        realParticipant = RealParticipant(_getBrowser(), _nextcloudUrl, _headless, _remoteSeleniumUrl)

        realParticipants.append(realParticipant)

        if _user or _appToken:
            realParticipant.login(_user, _appToken)

        realParticipant.joinRoom(_token)

    def addRealParticipants(count):
        """
        Adds as many real participants as the given count.

        See :py:func:`addRealParticipant`.

        :param count: the number of real participants to add.
        """

        if not _isValidConfiguration():
            return

        for i in range(count):
            addRealParticipant()

            print('.', end='', flush=True)

        print("")

    def removeRealParticipant(index):
        """
        Removes the real participant with the given index.

        :param index: the index in :py:data:`realParticipants` of the real
            participant to remove.
        """

        if index < 0 or index >= len(realParticipants):
            print("Index out of range")
            return

        del realParticipants[index]

    def removeRealParticipants():
        """
        Removes all the real participants previously added.
        """

        while realParticipants:
            removeRealParticipant(0)

    if globals()['_talkbuchetMode'] == 'siege':
        if endSiege:
            endSiege()

        del globals()['setPublishersAndSubscribersCount']
        del globals()['startSiege']
        del globals()['checkPublishersConnections']
        del globals()['checkSubscribersConnections']
        del globals()['endSiege']

    if globals()['_talkbuchetMode'] == 'virtualParticipant':
        if removeVirtualParticipants:
            removeVirtualParticipants()

        del globals()['prepareVirtualParticipant']
        del globals()['prepareVirtualParticipants']
        del globals()['startVirtualParticipants']
        del globals()['startVirtualParticipantsParallel']
        del globals()['stopVirtualParticipants']
        del globals()['stopVirtualParticipantsParallel']
        del globals()['addVirtualParticipant']
        del globals()['addVirtualParticipants']
        del globals()['removeVirtualParticipant']
        del globals()['removeVirtualParticipants']

    globals()['addRealParticipant'] = addRealParticipant
    globals()['addRealParticipants'] = addRealParticipants
    globals()['removeRealParticipant'] = removeRealParticipant
    globals()['removeRealParticipants'] = removeRealParticipants

    globals()['_talkbuchetMode'] = 'realParticipant'


def _deleteTalkbuchetInstancesOnExit():
    while sieges:
        del sieges[0]

    while virtualParticipants:
        del virtualParticipants[0]

    while realParticipants:
        del realParticipants[0]

# Talkbuchet instances should be explicitly deleted before exiting, as if they
# are implicitly deleted while exiting the Selenium driver may not cleanly quit.
atexit.register(_deleteTalkbuchetInstancesOnExit)

_findDefaultBrowser()

print('Full documentation can be shown by calling help(__name__)')

switchToSiegeMode()
