<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 5.0.4 – 2019-06-06
### Fixed
- Fix message list not reloaded after switching tabs in the sidebar
  [#1867](https://github.com/nextcloud/spreed/pull/1867)
- Fix multiple issues related to screensharing
  [#1762](https://github.com/nextcloud/spreed/pull/1762)
  [#1754](https://github.com/nextcloud/spreed/pull/1754)
  [#1746](https://github.com/nextcloud/spreed/pull/1746)

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

