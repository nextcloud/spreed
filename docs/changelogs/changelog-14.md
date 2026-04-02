<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 14.0.11 – 2023-05-25
### Changed
- Allow Brave browser without unsupported warning
  [#9172](https://github.com/nextcloud/spreed/issues/9172)
- Update dependencies

### Fixed
- Fix call summary when a user has a full numeric user ID
  [#9503](https://github.com/nextcloud/spreed/issues/9503)

## 14.0.10 – 2023-03-24
### Fixed
- fix(calls): Fix RemoteVideoBlocker still active after removing its associated model
  [#9133](https://github.com/nextcloud/spreed/pull/9133)
- fix(reactions): Fix reacting to people that left
  [#8887](https://github.com/nextcloud/spreed/pull/8887)

## 14.0.9 – 2023-02-23
### Changed
- Update some dependencies

### Fixed
- Only filter mentions for participants of the conversation
  [#8666](https://github.com/nextcloud/spreed/pull/8666)
- Fix interaction of self-joined users with multiple sessions when navigating away
  [#8730](https://github.com/nextcloud/spreed/pull/8730)

## 14.0.8 – 2023-01-19
### Fixed
- Allow autocompleting conversation names from the middle
  [#8506](https://github.com/nextcloud/spreed/pull/8506)
- Call view not shown when rejoining a call in the file sidebar
  [#8508](https://github.com/nextcloud/spreed/pull/8508)
- Fix leaving the call when switching to another conversation
  [#8530](https://github.com/nextcloud/spreed/pull/8530)

## 14.0.7 – 2022-12-01
### Changed
- Allow to disable the changelog conversation with an app config
  [#8365](https://github.com/nextcloud/spreed/pull/8365)

### Fixed
- Fix in_call flag on the "Join room" API response
  [#8372](https://github.com/nextcloud/spreed/pull/8372)
- Fix bottom stripe of speaker view with high DPI
  [#8320](https://github.com/nextcloud/spreed/pull/8320)

## 14.0.6 – 2022-11-03
### Changed
- Take the device pixel ratio into account when calculating minimum grid size (should see more videos now on High DPI settings like MacOS and most 4k setup)
  [#8247](https://github.com/nextcloud/spreed/pull/8247)

### Fixed
- Fix XML API endpoint for chats with empty reactions result
  [#8110](https://github.com/nextcloud/spreed/pull/8110)
- Hide talk dashboard when user can not use the Talk app
  [#8237](https://github.com/nextcloud/spreed/pull/8237)
- Hide talk sidebar integration when user can not use the Talk app
  [#8241](https://github.com/nextcloud/spreed/pull/8241)
- Fix participant sessions not sent to the HPB
  [#8099](https://github.com/nextcloud/spreed/pull/8099)
- Don't search in lobbied conversations
  [#8116](https://github.com/nextcloud/spreed/pull/8116)
- Fix an issue with detecting Safari on iOS version
  [#8135](https://github.com/nextcloud/spreed/pull/8135)

## 14.0.5 – 2022-09-15
### Fixed
- Fix notification sending when the user is blocked by the lobby
  [#7794](https://github.com/nextcloud/spreed/pull/7794)
- Fix missing local media controls in public share sidebar
  [#7758](https://github.com/nextcloud/spreed/pull/7758)
- Fix missing screenshares in sidebar
  [#7760](https://github.com/nextcloud/spreed/pull/7760)
- Fix inconsistent state when leaving a call fails
  [#7803](https://github.com/nextcloud/spreed/pull/7803)

## 14.0.4 – 2022-08-11
### Added
- Extend search result attributes for better handling in mobile clients
  [#7588](https://github.com/nextcloud/spreed/pull/7588)
  [#7587](https://github.com/nextcloud/spreed/pull/7587)

### Fixed
- Location shares not visible in chat anymore (only in the sidebar tab)
  [#7550](https://github.com/nextcloud/spreed/pull/7550)
- Reduce sent information with disabled videos
  [#7709](https://github.com/nextcloud/spreed/pull/7709)
- Multiple accessibility fixes
  [#7599](https://github.com/nextcloud/spreed/pull/7599)
  [#7654](https://github.com/nextcloud/spreed/pull/7654)
  [#7553](https://github.com/nextcloud/spreed/pull/7553)
  [#7570](https://github.com/nextcloud/spreed/pull/7570)

## 14.0.3 – 2022-07-08
### Added
- Add brute force protection for conversation tokens and passwords
  [#7535](https://github.com/nextcloud/spreed/pull/7535)
- Allow the HPB to group session pinging across multiple conversations
  [#7444](https://github.com/nextcloud/spreed/pull/7444)
- Add a capability for unified search to enable the feature on the clients
  [#7448](https://github.com/nextcloud/spreed/pull/7448)

### Fixed
- Fix backend URL in request to HPB from command line
  [#7440](https://github.com/nextcloud/spreed/pull/7440)
- Fix error when setting user status while not being in any conversation
  [#7466](https://github.com/nextcloud/spreed/pull/7466)

## 14.0.2 – 2022-05-26
### Changed
- Add "Others" section to shared items tab to list unknown items
  [#7350](https://github.com/nextcloud/spreed/pull/7350)

### Fixed
- Only declare changed sessions as such instead of all sessions of that participant
  [#7382](https://github.com/nextcloud/spreed/pull/7382)
- Ensure display name of conversation owner is stored correctly
  [#7376](https://github.com/nextcloud/spreed/pull/7376)
- Don't show promotion options for circles and groups
  [#7404](https://github.com/nextcloud/spreed/pull/7404)
- Don't show permissions options for circles and groups
  [#7360](https://github.com/nextcloud/spreed/pull/7360)
- Don't show reactions option for command messages
  [#7345](https://github.com/nextcloud/spreed/pull/7345)
- Fix forwarding replies
  [#7343](https://github.com/nextcloud/spreed/pull/7343)

## 14.0.1 – 2022-05-07
### Fixed
- Fix memory consumption with emoji picker in conversations with a lot of chat messages with reactions
  [#7328](https://github.com/nextcloud/spreed/pull/7328)
- Fix endless offer loop with SIP participants
  [#7288](https://github.com/nextcloud/spreed/pull/7288)
- Fix room selector in deck and maps integration
  [#7290](https://github.com/nextcloud/spreed/pull/7290)
  [#7294](https://github.com/nextcloud/spreed/pull/7294)
- Fix transceiver kind for participants without the HPB
  [#7263](https://github.com/nextcloud/spreed/pull/7263)
- Fix error on console when initiating a screenshare
  [#7330](https://github.com/nextcloud/spreed/pull/7330)
- Add missing translations of 24 branch
  [#7330](https://github.com/nextcloud/spreed/pull/7330)

## 14.0.0 – 2022-05-02
### Added
- Reactions for chat messages
- Media tab in the sidebar to show all the shared items
- Implement `OCP\Talk\IBroker` to allow apps to create conversations
- Sharing a browser tab in Chrome-based browsers can now also share the audio of that tab

### Changed
- Messages of shared objects and files can now be deleted (shares will be removed, files persist)
- Actions like calling and chatting in big rooms are now much smoother
- Compatibility with Nextcloud 24

## 14.0.0-rc.4 – 2022-04-29
### Added
- Add a modal to show more shared items

### Fixed
- Make reactions work for guests and handle guests without name in the summary
  [#7217](https://github.com/nextcloud/spreed/pull/7217)
- Add a link to notification sound settings from talk settings
  [#7224](https://github.com/nextcloud/spreed/pull/7224)
- Fix migration with Postgres and Oracle
  [#7211](https://github.com/nextcloud/spreed/pull/7211)
- Add programmatic output options to talk:active-calls command
  [#7227](https://github.com/nextcloud/spreed/pull/7227)
- Fix media tab and reactions summary in read-only rooms
  [#7236](https://github.com/nextcloud/spreed/pull/7236)

## 14.0.0-rc.3 – 2022-04-22
### Fixed
- Move message.reactions.self to message.reactionsSelf to not merge different data structures
  [#7182](https://github.com/nextcloud/spreed/pull/7182)
- Use actor and time information from the reaction not the message author
  [#7190](https://github.com/nextcloud/spreed/pull/7190)
- Fix migration of attachment types for media
  [#7196](https://github.com/nextcloud/spreed/pull/7196)
- Open chat tab by default in sidebar while in a call
  [#7201](https://github.com/nextcloud/spreed/pull/7201)
- Fix access to undefined key
  [#7195](https://github.com/nextcloud/spreed/pull/7195)
- Only set header when the value changed and the status is not 304
  [#7200](https://github.com/nextcloud/spreed/pull/7200)

### Still in progress
- Media tab showing all shared items of the conversation

## 14.0.0-rc.2 – 2022-04-19
### Fixed
- Remove event to delete shares when user leave of room
  [#7168](https://github.com/nextcloud/spreed/pull/7168)
- Add item shares from chat messages directly to the store
  [#7149](https://github.com/nextcloud/spreed/pull/7149)
- Only switch to the participant tab when the token changes not any other detail
  [#7146](https://github.com/nextcloud/spreed/pull/7146)
- Don't update last message of conversation with invisible message
  [#7142](https://github.com/nextcloud/spreed/pull/7142)

### Still in progress
- Media tab showing all shared items of the conversation

## 14.0.0-rc.1 – 2022-04-13
### Added
- Reactions for chat messages
- Media tab in the sidebar to show all the shared items
- Implement `OCP\Talk\IBroker` to allow apps to create conversations
- Sharing a browser tab in Chrome-based browsers can now also share the audio of that tab
  [#6810](https://github.com/nextcloud/spreed/pull/6810)

### Still in progress
- Media tab showing all shared items of the conversation

### Changed
- Messages of shared objects and files can now be deleted (shares will be removed, files persist)
  [#7047](https://github.com/nextcloud/spreed/pull/7047)
- Actions like calling and chatting in big rooms should now be much smoother
- Compatibility with Nextcloud 24

