<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 7.0.4 – 2020-07-21
### Added
- Reduce CPU usage when doing a video call
  [#3416](https://github.com/nextcloud/spreed/pull/3416)

### Changed
- Improve default push notification text for upcoming iOS 13 SDK changes in the iOS mobile app
  [#3849](https://github.com/nextcloud/spreed/pull/3849)

### Fixed
- Fix a failure on logout when an active session was removed from a conversation already
  [#3871](https://github.com/nextcloud/spreed/pull/3871)
- Fix an error when the user list is used to fill a missing conversation name
  [#3629](https://github.com/nextcloud/spreed/pull/3629)
- Fix an error when the parent of a reply was the first item in the message list
  [#3588](https://github.com/nextcloud/spreed/pull/3588)

## 7.0.2 – 2019-11-12
### Changed
- Improve the settings for Talk and extend the explanations
  [#2342](https://github.com/nextcloud/spreed/pull/2342)

### Fixed
- Do not join file conversations automatically to avoid empty conversations
  [#2423](https://github.com/nextcloud/spreed/pull/2423)
  [#2347](https://github.com/nextcloud/spreed/pull/2347)
- Do not load the Talk sidebar on public share page for folders
  [#2340](https://github.com/nextcloud/spreed/pull/2340)

## 7.0.1 – 2019-10-17
### Fixed
- Fix position of the promoted and the current participant in calls
  [#2320](https://github.com/nextcloud/spreed/pull/2320)
- Add a hint for the start time format of the lobby timer
  [#2267](https://github.com/nextcloud/spreed/pull/2267)
- Fix "MessageTooLongException" when mentioning someone in a long comment
  [#2268](https://github.com/nextcloud/spreed/pull/2268)
- Correctly set the unread counter when readding a user to a one-to-one conversation
  [#2259](https://github.com/nextcloud/spreed/pull/2259)

## 7.0.0 – 2019-09-26
### Added
- Added a simple Lobby: moderators can join and prepare a call/meeting while users and guests can not join yet
  [#1926](https://github.com/nextcloud/spreed/pull/1926)
- Add the file call functionality to the public sharing page
  [#2107](https://github.com/nextcloud/spreed/pull/2107)
- Allow to mention guest users
  [#1974](https://github.com/nextcloud/spreed/pull/1974)
- Added a voice level indicator and notify the user when they speak while they are muted
  [#2016](https://github.com/nextcloud/spreed/pull/2016)
- Change the read marker to work based on the message ID and allow clients to set it manually
  [#1214](https://github.com/nextcloud/spreed/pull/1214)
- Prepare the backend for replies to messages so the clients can implement it
  [#2000](https://github.com/nextcloud/spreed/pull/2000)
- Allow to prevent guests from starting a call
  [#2204](https://github.com/nextcloud/spreed/pull/2204)
- Update SimpleWebRTC to the latest version

### Changed
- Load newest chat messages first on joining a conversation
  [#2206](https://github.com/nextcloud/spreed/pull/2206)
- You can now escape commands to show them to your chat partners by prepending a second slash (e.g. //help)
  [#1919](https://github.com/nextcloud/spreed/pull/1919)
- One-to-one conversations are now only deleted if both users leave the conversation
  [#1921](https://github.com/nextcloud/spreed/pull/1921)
- Use the guests name in notifications instead of the anonymous "A guest" string
  [#2104](https://github.com/nextcloud/spreed/pull/2104)

### Fixed
- Allow to have file based calls in folders mounted by the groupfolders app
  [#2012](https://github.com/nextcloud/spreed/pull/2012)
- Only list participants who joined the call in the call summary
  [#2012](https://github.com/nextcloud/spreed/pull/2012)
- Participants in the participant list now offer the contacts menu
  [#1822](https://github.com/nextcloud/spreed/pull/1822)
- Better UI feedback while moderator actions are performed
  [#2117](https://github.com/nextcloud/spreed/pull/2117)
- Make sure the external signaling server is informed about the new state changes (read-only, lobby, etc.)
  [#2103](https://github.com/nextcloud/spreed/pull/2103)
- Show a call summary for calls when there was no user
  [#2177](https://github.com/nextcloud/spreed/pull/2177)
- Allow to mention the conversation by its name additionally to "all"
  [#2198](https://github.com/nextcloud/spreed/pull/2198)
- Fix mentions with users that have a numeric only ID
  [#2173](https://github.com/nextcloud/spreed/pull/2173)
- Enable camera and microphone access in the Nextcloud 17 feature policy
  [#2073](https://github.com/nextcloud/spreed/pull/2073)
- Multiple Nextcloud 17 compatibility fixes

