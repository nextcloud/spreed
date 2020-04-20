# Changelog
All notable changes to this project will be documented in this file.

## 6.0.5 – 2020-04-20
### Fixed
- Removed unnecessary double-quote argument parameter from commands
  [#3364](https://github.com/nextcloud/spreed/pull/3364)

## 6.0.4 – 2019-07-31
### Fixed
- Audio missing in chromium when enabling video until a video is received
  [#2058](https://github.com/nextcloud/spreed/pull/2058)
- Correctly handle password public conversations in projects
  [#2057](https://github.com/nextcloud/spreed/pull/2057)
- Update the nextcloud-vue-collections library for better projects handling
  [#2054](https://github.com/nextcloud/spreed/pull/2054)
- Fix pending reconnections after WebSocket is reconnected
  [#2033](https://github.com/nextcloud/spreed/pull/2033)

## 6.0.3 – 2019-07-22
### Changed
- Chat messages can now be longer than 1.000 characters
  [#1901](https://github.com/nextcloud/spreed/pull/1901)
- Users matching the autocomplete at the beginning of their name are now sorted to the top
  [#1968](https://github.com/nextcloud/spreed/pull/1968)
- Only strings starting with http:// or https:// are now made clickable as links
  [#1965](https://github.com/nextcloud/spreed/pull/1965)

### Fixed
- Fix layout issues of the chat on the share authentication page
  [#1901](https://github.com/nextcloud/spreed/pull/1901)
- Fix issues with calls when a user logs out while being in a call
  [#1947](https://github.com/nextcloud/spreed/pull/1947)
- Fix a problem when joining a public conversation as a non-invited logged-in user
  [#1914](https://github.com/nextcloud/spreed/pull/1914)
- Fix missing tooltip with full date on timestamp for the first message of a user in a grouped block
  [#1914](https://github.com/nextcloud/spreed/pull/1914)
- Commands based on the Symfony Command component can now provide a useful help message
  [#1901](https://github.com/nextcloud/spreed/pull/1901)

## 6.0.2 – 2019-06-06
### Fixed
- Fix message list not reloaded after switching tabs in the sidebar
  [#1867](https://github.com/nextcloud/spreed/pull/1867)
- Show warning when browser silently fails to get user media
  [#1874](https://github.com/nextcloud/spreed/pull/1874)
- Fix view for participants without streams
  [#1873](https://github.com/nextcloud/spreed/pull/1873)
- Fix forced reconnection with external signaling
  [#1850](https://github.com/nextcloud/spreed/pull/1850)
- Do not send volume datachannel message
  [#1849](https://github.com/nextcloud/spreed/pull/1849)


## 5.0.4 – 2019-06-06
### Fixed
- Fix message list not reloaded after switching tabs in the sidebar
  [#1867](https://github.com/nextcloud/spreed/pull/1867)
- Fix multiple issues related to screensharing
  [#1762](https://github.com/nextcloud/spreed/pull/1762)
  [#1754](https://github.com/nextcloud/spreed/pull/1754)
  [#1746](https://github.com/nextcloud/spreed/pull/1746)
  
## 6.0.1 – 2019-05-16
### Changed
- Do not send black video by default in bigger calls
  [#1830](https://github.com/nextcloud/spreed/pull/1830)
  [#1827](https://github.com/nextcloud/spreed/pull/1827)
- Improve the grouping of chat messages so more fit on the screen
  [#1826](https://github.com/nextcloud/spreed/pull/1826)

### Fixed
- Fix password protected conversations
  [#1775](https://github.com/nextcloud/spreed/pull/1775)
- Fix chat not automatically loading new messages after a command was used with the external signaling server
  [#1808](https://github.com/nextcloud/spreed/pull/1808)
- Fix screensharing for users not in the call
  [#1753](https://github.com/nextcloud/spreed/pull/1753)
- Conversation list does not update with read/unread status using the external signaling server
  [#1431](https://github.com/nextcloud/spreed/pull/1431)

## 6.0.0 – 2019-04-25
### Added
- Administrators can now define commands which can be used in the chat. See [commands.md](https://github.com/nextcloud/spreed/blob/master/docs/commands.md) for more information. You can install some sample commands via the console.
  [#1453](https://github.com/nextcloud/spreed/pull/1453)
  [#1662](https://github.com/nextcloud/spreed/pull/1662)
- There is now a "Talk updates" conversation which will help the user to discover some features
  [#1616](https://github.com/nextcloud/spreed/pull/1616)
  [#1662](https://github.com/nextcloud/spreed/pull/1662)
- `@all` mentions all participants in the conversation
  [#1531](https://github.com/nextcloud/spreed/pull/1531)
- Allow to get the last sent message again with `arrow-up`
  [#1520](https://github.com/nextcloud/spreed/pull/1520)
- Conversations can be added to the new Nextcloud 16 projects
  [#1611](https://github.com/nextcloud/spreed/pull/1611)
  [#1663](https://github.com/nextcloud/spreed/pull/1663)
- Conversations associated to files now have a link to the file
  [#1387](https://github.com/nextcloud/spreed/pull/1387)
- The Talk app can now be restricted to a group of users in the Talk administration settings
  [#1585](https://github.com/nextcloud/spreed/pull/1585)
- Show a warning when a call has many participants and no external signaling server is used
  [#1649](https://github.com/nextcloud/spreed/pull/1649)
- Added an easy-to-find option to copy the link of a conversation
  [#1670](https://github.com/nextcloud/spreed/pull/1670)

### Changed
- One-to-one conversations are now persistent and can not be turned into group conversations by accident. Also when one of the participants leaves the conversation, the conversation is not automatically deleted anymore.
  [#1591](https://github.com/nextcloud/spreed/pull/1591)
  [#1588](https://github.com/nextcloud/spreed/pull/1588)
- Conversations must have a name now
  [#1567](https://github.com/nextcloud/spreed/pull/1567)

### Fixed
- Fix multiple race-conditions that could interrupt connections, end calls or prevent connections between single participants
  [#1522](https://github.com/nextcloud/spreed/pull/1522)
  [#1533](https://github.com/nextcloud/spreed/pull/1533)
  [#1534](https://github.com/nextcloud/spreed/pull/1534)
  [#1549](https://github.com/nextcloud/spreed/pull/1549)
- Use better icons when a file without preview or a folder is shared into the chat
  [#1601](https://github.com/nextcloud/spreed/pull/1601)
- Prevent issues when two participants share their screens
  [#1571](https://github.com/nextcloud/spreed/pull/1571)
- Correctly remember last media state when reloading in a call
  [#1548](https://github.com/nextcloud/spreed/pull/1548)
  [#5174](https://github.com/nextcloud/spreed/pull/1574)
- Do not show conversation names and other details if the user is not a participant
  [#1426](https://github.com/nextcloud/spreed/pull/1426)
  [#1496](https://github.com/nextcloud/spreed/pull/1496)
  [#1502](https://github.com/nextcloud/spreed/pull/1502)
- Fixed an issue when a link was posted into the chat at the end of a line
  [#1666](https://github.com/nextcloud/spreed/pull/1666)

## 5.0.3 – 2019-04-11
### Changed
- Remove some conversation informations for non-participants
  [#1518](https://github.com/nextcloud/spreed/pull/1518)

### Fixed
- Fix duplicated call summary message when multiple people leave at the same time
  [#1599](https://github.com/nextcloud/spreed/pull/1599)
- Allow multiline text insertion in chrome-based browsers
  [#1579](https://github.com/nextcloud/spreed/pull/1579)
- Fix multiple race-conditions that could interrupt connections, end calls or prevent connections between single participants
  [#1523](https://github.com/nextcloud/spreed/pull/1523)
  [#1542](https://github.com/nextcloud/spreed/pull/1542)
  [#1543](https://github.com/nextcloud/spreed/pull/1543)
- Enable "Plan B" for chrome/chromium for better MCU support
  [#1613](https://github.com/nextcloud/spreed/pull/1613)
- Delay signaling messages when the socket is not yet opened
  [#1551](https://github.com/nextcloud/spreed/pull/1551)
- Correctly readd the default STUN server on empty values
  [#1501](https://github.com/nextcloud/spreed/pull/1501)

## 4.0.4 – 2019-04-11
### Fixed
- Enable "Plan B" for chrome/chromium for better MCU support
  [#1614](https://github.com/nextcloud/spreed/pull/1614)
- Delay signaling messages when the socket is not yet opened
  [#1552](https://github.com/nextcloud/spreed/pull/1552)

## 5.0.2 – 2019-01-30
### Changed
- Show autocompletion as soon as "@" is typed 
  [#1483](https://github.com/nextcloud/spreed/pull/1483)

### Fixed
- Fix parse error on PHP 7.0
  [#1493](https://github.com/nextcloud/spreed/pull/1493)
- Add global Content Security Policy for signaling servers
  [#1462](https://github.com/nextcloud/spreed/pull/1462)
- Shared file messages show the name of the file as seen by the owner instead of by the current user
  [#1487](https://github.com/nextcloud/spreed/pull/1487)
- Multiple fixes for dark-theme
  [#1494](https://github.com/nextcloud/spreed/pull/1494)
  [#1472](https://github.com/nextcloud/spreed/pull/1472)
  [#1486](https://github.com/nextcloud/spreed/pull/1486)
- Do not show room names when the user is not part of it 
  [#1497](https://github.com/nextcloud/spreed/pull/1497)
  [#1495](https://github.com/nextcloud/spreed/pull/1495)
- Fix page title not updated when room name is updated
  [#1468](https://github.com/nextcloud/spreed/pull/1468)
- Reduce the number of loaded JS and CSS files
  [#1491](https://github.com/nextcloud/spreed/pull/1491)
- Always use white icons for conversation images (also in dark-theme)
  [#1463](https://github.com/nextcloud/spreed/pull/1463)
- Fix submit button in public share authentication page
  [#1481](https://github.com/nextcloud/spreed/pull/1481)

## 4.0.3 – 2019-01-30
### Fixed
- Do not show room names when the user is not part of it 
  [#1498](https://github.com/nextcloud/spreed/pull/1498)
- Fix mentions when adding multiple directly after each other
  [#1393](https://github.com/nextcloud/spreed/pull/1393)
- Load more messages after loading the first batch when entering a room
  [#1402](https://github.com/nextcloud/spreed/pull/1402)
- Pass empty list of session ids when notifying about removed guests to avoid errors
  [#1414](https://github.com/nextcloud/spreed/pull/1414)

## 3.2.8 – 2019-01-30
### Fixed
- Fix mentions when adding multiple directly after each other
  [#1394](https://github.com/nextcloud/spreed/pull/1394)
- Load more messages after loading the first batch when entering a room
  [#1403](https://github.com/nextcloud/spreed/pull/1403)

## 5.0.1 – 2019-01-23
### Changed
- Add a hook so the external signaling can set participant data
  [#1418](https://github.com/nextcloud/spreed/pull/1418)
  
### Fixed
- Fix dark theme for better accessibility
  [#1451](https://github.com/nextcloud/spreed/pull/1451)
- Correctly mark notifications as resolved when you join the room directly
  [#1436](https://github.com/nextcloud/spreed/pull/1436)
- Fix history back and forth in Talk and the Files app
  [#1456](https://github.com/nextcloud/spreed/pull/1456)
- Favorite icon has grey avatar shadow
  [#1419](https://github.com/nextcloud/spreed/pull/1419)

## 5.0.0 – 2018-12-14
### Added
- Chat and call option in the Files app sidebar
  [#1323](https://github.com/nextcloud/spreed/pull/1323)
  [#1312](https://github.com/nextcloud/spreed/pull/1312)
- Users can now select for each conversation whether they want to be notified: always, on mention or never
  [#1230](https://github.com/nextcloud/spreed/pull/1230)
- Password protection via Talk now also works for link shares
  [#1273](https://github.com/nextcloud/spreed/pull/1273)
- Guests can now be promoted to moderators in on going calls
  [#1078](https://github.com/nextcloud/spreed/pull/1078)
- Groups can now be selected when adding participants and will add all members as participants
  [#1268](https://github.com/nextcloud/spreed/pull/1268)
- Email addresses can now be added to conversations which will make the room public and send the link via email
  [#1090](https://github.com/nextcloud/spreed/pull/1090)
- TURN server settings can now be tested in the admin settings
  [#1177](https://github.com/nextcloud/spreed/pull/1177)

### Changed
- Improve performance of chats with multiple hundred messages
  [#1271](https://github.com/nextcloud/spreed/pull/1271)

### Fixed
- Fix screensharing in Chrome 71+ and other Chrome based browsers
  [#1369](https://github.com/nextcloud/spreed/pull/1369)
- Fix mentions for users with spaces in their user id
  [#1254](https://github.com/nextcloud/spreed/pull/1254)
- Fix avatars in messages by guests
  [#1240](https://github.com/nextcloud/spreed/pull/1240)
- Gracefully handle messages with more than 1000 characters
  [#1229](https://github.com/nextcloud/spreed/pull/1229)
- Stop signaling when leaving a conversation
  [#1330](https://github.com/nextcloud/spreed/pull/1330)
- Fix scroll position when the chat is moved to the sidebar
  [#1302](https://github.com/nextcloud/spreed/pull/1302)
- When a files is shared a second time into a chat no error is displayed
  [#1196](https://github.com/nextcloud/spreed/pull/1196)
- Send initial screensharing stream to participants that don't publish video
  [#1372](https://github.com/nextcloud/spreed/pull/1372)

## 4.0.2 – 2018-12-11
### Fixed
- Fix screensharing in Chrome 71+ and other Chrome based browsers
  [#1369](https://github.com/nextcloud/spreed/pull/1369)
- Fix broken chat when a file that was shared into a room is deleted
  [#1352](https://github.com/nextcloud/spreed/pull/1352)
- Send initial screensharing stream to participants that don't publish video
  [#1372](https://github.com/nextcloud/spreed/pull/1372)

## 3.2.7 – 2018-12-11
### Fixed
- Fix screensharing in Chrome 71+ and other Chrome based browsers
  [#1369](https://github.com/nextcloud/spreed/pull/1369)
- Send initial screensharing stream to participants that don't publish video
  [#1372](https://github.com/nextcloud/spreed/pull/1372)

## 4.0.1 – 2018-11-15
### Added
- Add an option to test the TURN configuration in the admin settings
  [#1294](https://github.com/nextcloud/spreed/pull/1294)

### Changed
- Improve the notifications when a share password is requested
  [#1296](https://github.com/nextcloud/spreed/pull/1296)
- Do not show an error when a file is shared a second time into a conversation
  [#1295](https://github.com/nextcloud/spreed/pull/1295)

### Fixed
- Custom Signaling, STUN and TURN configurations are not loaded for the user requesting the password for a share
  [#1297](https://github.com/nextcloud/spreed/pull/1297)
- Fix position of the contacts menu when clicking on the avatar of a chat author
  [#1293](https://github.com/nextcloud/spreed/pull/1293)
- Avatars in messages/mentions by guests show the guest avatar instead of the user
  [#1292](https://github.com/nextcloud/spreed/pull/1292)
- Information about user state in a call is bugged
  [#1291](https://github.com/nextcloud/spreed/pull/1291)
- Wrong conversation name of password requests in the details sidebar
  [#1290](https://github.com/nextcloud/spreed/pull/1290)
- Fix rendering, reloading and interaction with the participant list
  [#1222](https://github.com/nextcloud/spreed/pull/1222)
  [#1289](https://github.com/nextcloud/spreed/pull/1289)

## 3.2.6 – 2018-09-20
### Fixed
- Fix turn credential generation
  [#1203](https://github.com/nextcloud/spreed/pull/1203)
- Fix several inconsistencies with the internal api
  [#1202](https://github.com/nextcloud/spreed/pull/1202)
  [#1201](https://github.com/nextcloud/spreed/pull/1201)
  [#1200](https://github.com/nextcloud/spreed/pull/1200)

## 4.0.0 – 2018-09-06
### Added
- Video verification for password protected email shares
  [#1123](https://github.com/nextcloud/spreed/pull/1123)
  [#1049](https://github.com/nextcloud/spreed/pull/1049)
- Add a file picker to the chat to share files and folders into a chat room
  [#1151](https://github.com/nextcloud/spreed/pull/1151)
  [#1050](https://github.com/nextcloud/spreed/pull/1050)
- Log the activity of a conversation in the chat (user added/removed, call happened, …)
  [#1067](https://github.com/nextcloud/spreed/pull/1067)
- Allow to favor conversations so they are pinned to the top of the list
  [#1025](https://github.com/nextcloud/spreed/pull/1025)
  
### Changed
- Mentions in the chat now show the avatar of the user and highlight yourself more prominent
  [#1142](https://github.com/nextcloud/spreed/pull/1142)
- Messages in one2one chats now always send a notification
  [#1029](https://github.com/nextcloud/spreed/pull/1029)
- Conversations are now sorted by last activity rather then your last visit
  [#1061](https://github.com/nextcloud/spreed/pull/1061)

### Fixed
- Fix turn credentials generation
  [#1176](https://github.com/nextcloud/spreed/pull/1176)
- Do not turn all `@…` strings into a mention
  [#1118](https://github.com/nextcloud/spreed/pull/1118)

## 3.2.5 – 2018-07-23
### Fixed
- Fix handling of malicious usernames while autocompleting in chat

## 3.2.4 – 2018-07-12
### Added
- Allow external signaling servers to integrate a MCU
  [#398](https://github.com/nextcloud/spreed/pull/398)

### Fixed
- Support chat with a standalone signaling servers
  [#890](https://github.com/nextcloud/spreed/pull/890)
  [#887](https://github.com/nextcloud/spreed/pull/887)

## 3.2.3 – 2018-07-11
### Changed
- Only paste the content of HTML into the chat input without the actual HTML 
  [#1018](https://github.com/nextcloud/spreed/pull/1018)

### Fixed
- Fixes for standalone signaling server
  [#910](https://github.com/nextcloud/spreed/pull/910)
- Name not shown for participants without audio and video
  [#982](https://github.com/nextcloud/spreed/pull/982)
- Correctly timeout users when they are chatting/calling and got disconnected 
  [#935](https://github.com/nextcloud/spreed/pull/935)
- Multiple layout fixes

## 3.2.2 – 2018-06-06
### Added
- Add toggle to show and hide video from other participants
  [#937](https://github.com/nextcloud/spreed/pull/937)

### Changed
- Activities and Notifications text (Calls->Conversations)
  [#919](https://github.com/nextcloud/spreed/pull/919)

### Fixed
- Send call notifications to every room participant that is not in the call
  [#926](https://github.com/nextcloud/spreed/pull/926)
- Mark messages directly as read when waiting for new messages
  [#936](https://github.com/nextcloud/spreed/pull/936)
- Fix tab header icons not shown
  [#929](https://github.com/nextcloud/spreed/pull/929)
- Fix room and participants menu buttons
  [#934](https://github.com/nextcloud/spreed/pull/934)
  [#941](https://github.com/nextcloud/spreed/pull/941)
- Fix local audio and video not disabled when not available
  [#938](https://github.com/nextcloud/spreed/pull/938)
- Fix "Add participant" shown to normal participants
  [#939](https://github.com/nextcloud/spreed/pull/939)
- Fix adding the same participant several times in a row
  [#940](https://github.com/nextcloud/spreed/pull/940)


## 3.2.1 – 2018-05-11
### Added
- Standalone signaling server now supports the 3.2 changes
  [#864](https://github.com/nextcloud/spreed/pull/864)
  [#869](https://github.com/nextcloud/spreed/pull/869)

### Fixed
- Only join the room after media permission request was answered
  [#854](https://github.com/nextcloud/spreed/pull/854)
- Do not reload the participant everytime a guest sends a chat message
  [#866](https://github.com/nextcloud/spreed/pull/866)
- Make sure the web UI still works after you left the current conversation or call
  [#871](https://github.com/nextcloud/spreed/pull/871)
  [#872](https://github.com/nextcloud/spreed/pull/872)
  [#874](https://github.com/nextcloud/spreed/pull/874)
- Allow to scroll on long participant lists again
  [#896](https://github.com/nextcloud/spreed/pull/896)
- Do not throw an error when starting a call in a conversation without any chat message
  [#861](https://github.com/nextcloud/spreed/pull/861)
- Enable media controls when media is approved on a second request
  [#861](https://github.com/nextcloud/spreed/pull/861)
- Limit the unread message counter to 99+
  [#845](https://github.com/nextcloud/spreed/pull/845)


## 3.2.0 – 2018-05-03
### Added
- Shortcuts have been added when a call is active: (m)ute, (v)ideo, (f)ullscreen, (c)hat and (p)articipant list
  [#730](https://github.com/nextcloud/spreed/pull/730)
  [#750](https://github.com/nextcloud/spreed/pull/750)
- Allow users to chat in multiple tabs in multiple chats at the same time
  [#748](https://github.com/nextcloud/spreed/pull/748)
- Guest names are now handled better in chat and the participant list
  [#733](https://github.com/nextcloud/spreed/pull/733)
- Users which are participanting in a call now have a video icon in the participant list
  [#777](https://github.com/nextcloud/spreed/pull/777)
- Unread chat message count is now displayed in the room list
  [#806](https://github.com/nextcloud/spreed/pull/806)
  [#824](https://github.com/nextcloud/spreed/pull/824)

### Changed
- It is now possible to join a call without camera and/or microphone
  [#758](https://github.com/nextcloud/spreed/pull/758)
- Chat does now not require Media permissions anymore
  [#711](https://github.com/nextcloud/spreed/pull/711)
- Leaving a call will free up the Media permissions
  [#735](https://github.com/nextcloud/spreed/pull/735)
- Participants can now be `@mentioned` in the chat by starting to type `@` followed by the name of the user
  [#805](https://github.com/nextcloud/spreed/pull/805)
  [#812](https://github.com/nextcloud/spreed/pull/812)
  [#813](https://github.com/nextcloud/spreed/pull/813)

### Fixed
- Correctly catch the input on the chat in firefox (instead of writing to the placeholder)
  [#737](https://github.com/nextcloud/spreed/pull/737)
- Keep scrolling position when switching from chat to call or back
  [#838](https://github.com/nextcloud/spreed/pull/838)
- Delete rooms when the last logged in user leaves
  [#727](https://github.com/nextcloud/spreed/pull/727)
- Correctly update chat UI when leaving current room
  [#743](https://github.com/nextcloud/spreed/pull/743)
- Various layout fixes with videos and screensharing
  [#702](https://github.com/nextcloud/spreed/pull/702)
  [#712](https://github.com/nextcloud/spreed/pull/712)
  [#713](https://github.com/nextcloud/spreed/pull/713)
- Fix issues with users that have a numerical name or id
  [#694](https://github.com/nextcloud/spreed/pull/694)
- Fix contacts menu entry when no user was found
  [#686](https://github.com/nextcloud/spreed/pull/686)


## 3.1.0 – 2018-02-14
### Added
- Finish support for go-based external signaling backend
  [#492](https://github.com/nextcloud/spreed/pull/492)

### Changed
- Make capabilities and signaling settings available for guests
  [#644](https://github.com/nextcloud/spreed/pull/644) [#654](https://github.com/nextcloud/spreed/pull/654)
- Use the search name as room name when creating a new room
  [#592](https://github.com/nextcloud/spreed/pull/592)
- Make links in chat clickable
  [#579](https://github.com/nextcloud/spreed/pull/579)

### Fixed
- Fix screensharing layout for guests
  [#611](https://github.com/nextcloud/spreed/pull/611)
- Correctly remember guest names when a guest is rejoining an existing call
  [#593](https://github.com/nextcloud/spreed/pull/593)
- Better date time divider in chat view
  [#591](https://github.com/nextcloud/spreed/pull/591)

## 3.0.1 – 2018-01-12
### Added
- Added capabilities so the mobile files apps can link to the mobile talk apps
  [#585](https://github.com/nextcloud/spreed/pull/585)

### Fixed
- Fixed issues when updating with Postgres and versions before 2.0.0
  [#584](https://github.com/nextcloud/spreed/pull/584)

## 3.0.0 – 2018-01-10
### Added
 - Added simple text chat
  [#429](https://github.com/nextcloud/spreed/pull/429)
 - Added activities for calls: "You had a call with ABC (Duration: 15:20)"
  [#438](https://github.com/nextcloud/spreed/pull/438)
 - Introduced different participant permission levels: owner, moderator and user
  [#353](https://github.com/nextcloud/spreed/pull/353)
 - Added support for room passwords on public shared rooms
  [#402](https://github.com/nextcloud/spreed/pull/402)
 - Added option to run an external signaling backend
  [#366](https://github.com/nextcloud/spreed/pull/366)

### Changed
 - Rename the app to "Talk" since it now contains chat, voice and video calls
  [#444](https://github.com/nextcloud/spreed/pull/444)
 - Moved admin settings to separate category and allowed to configure multiple STUN and TURN servers
  [#427](https://github.com/nextcloud/spreed/pull/427)
 - Moved signaling from EventSource to long polling for compatibility with HTTP2
  [#363](https://github.com/nextcloud/spreed/pull/363)
 - Moved room API to OCS so apps and 3rd party tools can use it
  [#342](https://github.com/nextcloud/spreed/pull/342)

### Fixed
 - Fixed compatibility with Postgres
  [#537](https://github.com/nextcloud/spreed/pull/537)
 - Fixed compatibility with Oracle
  [#371](https://github.com/nextcloud/spreed/pull/371)
 - Compatibility with Nextcloud 13


## 2.0.2 – 2017-11-28
### Fixed
 - Re-send data channels messages when they could not be sent.
  [#335](https://github.com/nextcloud/spreed/pull/335)

## 2.0.1 – 2017-05-22
### Added
 - Display the connection state in the interface and try to reconnect in case of an issue
  [#317](https://github.com/nextcloud/spreed/pull/317)

### Changed
 - Is now more tolerant towards server ping issues
  [#320](https://github.com/nextcloud/spreed/pull/320)

### Fixed
 - Fix several issues that caused missing avatars
  [#312](https://github.com/nextcloud/spreed/pull/312)
  [#313](https://github.com/nextcloud/spreed/pull/313)
 - Fix visibility of guest names on light themes
  [#321](https://github.com/nextcloud/spreed/pull/321)

## 2.0.0 – 2017-05-02
### Added
 - Screensharing is now supported in Chrome 42+ (requires an extension) and Firefox 52+
  [#227](https://github.com/nextcloud/spreed/pull/227)
 - Integration in the new Nextcloud 12 contacts menu
  [#300](https://github.com/nextcloud/spreed/pull/300)

### Changed
 - URLs are now short random strings instead of iteratible numbers
  [#258](https://github.com/nextcloud/spreed/pull/258)
 - Logged-in users can now join public rooms
  [#296](https://github.com/nextcloud/spreed/pull/296)

### Fixed
 - Fix error when response of TURN and STUN server arrive in the wrong order
  [#295](https://github.com/nextcloud/spreed/pull/295)


## 1.2.0 – 2017-01-18
### Added
 - Translations for multiple languages now available
  [#177](https://github.com/nextcloud/spreed/pull/177)
 - Open call-search when user has no calls
  [#111](https://github.com/nextcloud/spreed/pull/111)
 - Disable video by default, when more then 5 people are in a room

### Changed
 - TURN settings can only be changed by admins now
  [#185](https://github.com/nextcloud/spreed/pull/185)
 - App can not be restricted to groups anymore to prevent issues with public rooms
  [#201](https://github.com/nextcloud/spreed/pull/201)

### Fixed
 - Allow to connect via Firefox without a camera
  [#160](https://github.com/nextcloud/spreed/pull/160)
 - Leaving the current room does not refresh the full page anymore
  [#163](https://github.com/nextcloud/spreed/pull/163)
 - "Undefined index" log entry when visiting admin page
  [#151](https://github.com/nextcloud/spreed/pull/151)
  [#57](https://github.com/nextcloud/spreed/pull/57)
