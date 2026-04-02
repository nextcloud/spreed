<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 4.0.4 – 2019-04-11
### Fixed
- Enable "Plan B" for chrome/chromium for better MCU support
  [#1614](https://github.com/nextcloud/spreed/pull/1614)
- Delay signaling messages when the socket is not yet opened
  [#1552](https://github.com/nextcloud/spreed/pull/1552)

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

## 4.0.2 – 2018-12-11
### Fixed
- Fix screensharing in Chrome 71+ and other Chrome based browsers
  [#1369](https://github.com/nextcloud/spreed/pull/1369)
- Fix broken chat when a file that was shared into a room is deleted
  [#1352](https://github.com/nextcloud/spreed/pull/1352)
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

