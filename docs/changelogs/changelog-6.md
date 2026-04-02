<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

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
- Administrators can now define commands which can be used in the chat. See [the commands documentation](https://nextcloud-talk.readthedocs.io/en/latest/commands/) for more information. You can install some sample commands via the console.
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

