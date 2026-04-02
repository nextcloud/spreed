<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 12.2.8 – 2022-11-03
### Fixed
- Fix participant sessions not sent to the HPB
  [#8114](https://github.com/nextcloud/spreed/pull/8114)
- Fix guest names in search results
  [#7591](https://github.com/nextcloud/spreed/pull/7591)
- Fix an issue with detecting Safari on iOS version
  [#8277](https://github.com/nextcloud/spreed/pull/8277)

## 12.2.7 – 2022-07-08
### Added
- Add brute force protection for conversation tokens and passwords
  [#7537](https://github.com/nextcloud/spreed/pull/7537)
- Add a capability for unified search to enable the feature on the clients
  [#7450](https://github.com/nextcloud/spreed/pull/7450)

### Fixed
- Fix backend URL in request to HPB from command line
  [#7442](https://github.com/nextcloud/spreed/pull/7442)
- Fix error when setting user status while not being in any conversation
  [#7468](https://github.com/nextcloud/spreed/pull/7468)

## 12.2.6 – 2022-05-26
### Fixed
- Ensure display name of conversation owner is stored correctly
  [#7378](https://github.com/nextcloud/spreed/pull/7378)
- Don't show promotion options for circles and groups
  [#7406](https://github.com/nextcloud/spreed/pull/7406)

## 12.2.5 – 2022-04-08
### Fixed
- Compatibility with LDAP user backends and more than 64 characters display names
  [#7074](https://github.com/nextcloud/spreed/pull/7074)
- Compatibility with Oracle and MySQL ONLY_FULL_GROUP_BY
  [#7040](https://github.com/nextcloud/spreed/pull/7040)

## 12.2.4 – 2022-03-17
### Fixed
- Fix several modals, dialogs and popovers in fullscreen mode
  [#6884](https://github.com/nextcloud/spreed/pull/6884)
- Fix mentions inside brackets
  [#6870](https://github.com/nextcloud/spreed/pull/6870)
- Fix call flags update when track is disabled
  [#7015](https://github.com/nextcloud/spreed/pull/7015)

## 12.2.3 – 2022-01-24
### Fixed
- Allow joining open conversations which are also shared as link with a password
  [#6710](https://github.com/nextcloud/spreed/pull/6710)
- Prevent handleScroll on initial loading of a conversation
  [#6718](https://github.com/nextcloud/spreed/pull/6718)
- Don't force a signaling mode when starting/ending the HPB trial
  [#6823](https://github.com/nextcloud/spreed/pull/6823)
- Add conversation token and message id to search results
  [#6746](https://github.com/nextcloud/spreed/pull/6746)

## 12.2.2 – 2021-12-07
### Changed
- Show user status and message as description in 1-to-1 conversations
  [#6369](https://github.com/nextcloud/spreed/pull/6369)
- Allow apps to override/modify the TURN server list
  [#6428](https://github.com/nextcloud/spreed/pull/6428)

### Fixed
- Fix connection analyzer when using simulcast with Chromium
  [#6530](https://github.com/nextcloud/spreed/pull/6530)
- Properly allow sha256 checksums for reference ids as advertised
  [#6406](https://github.com/nextcloud/spreed/pull/6406)
- Fix forwarding object shares to other conversations
  [#6398](https://github.com/nextcloud/spreed/pull/6398)
- Fix invisible emoji picker on Safari
  [#6352](https://github.com/nextcloud/spreed/pull/6352)
- Limit deck integration to the current instance for now
  [#6412](https://github.com/nextcloud/spreed/pull/6412)

## 12.2.1 – 2021-10-15
### Changed
- Simplify sidebar in one-to-one conversations
  [#6275](https://github.com/nextcloud/spreed/pull/6275)

### Fixed
- Reuse participant information when rendering peers in a call
  [#6344](https://github.com/nextcloud/spreed/pull/6344)
- Ignore sessions with timeouted ping when sending notifications
  [#6329](https://github.com/nextcloud/spreed/pull/6329)
- Fix errors with the speaking events
  [#6298](https://github.com/nextcloud/spreed/pull/6298)
- Don't fail hard when adding a participant in parallel
  [#6264](https://github.com/nextcloud/spreed/pull/6264)
- Fix invisible emoji picker in Safari
  [#6351](https://github.com/nextcloud/spreed/pull/6351)

## 12.2.0 – 2021-09-17
### Added
- Add "Create conversation" button on first page of dialog when creating a public conversation
  [#6206](https://github.com/nextcloud/spreed/pull/6206)

### Fixed
- Add some validation for shared geo-locations
  [#6242](https://github.com/nextcloud/spreed/pull/6242)
- Move unreadMessageElement from computed to a method
  [#6241](https://github.com/nextcloud/spreed/pull/6241)
- Fix logged-in users are unable to join a password protected public conversation
  [#6230](https://github.com/nextcloud/spreed/pull/6230)
- Don't toggle the video on/off when pasting files into the chat and first releasing the CTRL key
  [#6198](https://github.com/nextcloud/spreed/pull/6198)
- Disable recording voice messages on readonly conversations
  [#6182](https://github.com/nextcloud/spreed/pull/6182)

## 12.1.2 – 2021-09-17
### Fixed
- Add some validation for shared geo-locations
  [#6243](https://github.com/nextcloud/spreed/pull/6243)
- Fix logged-in users are unable to join a password protected public conversation
  [#6229](https://github.com/nextcloud/spreed/pull/6229)
- Don't toggle the video on/off when pasting files into the chat and first releasing the CTRL key
  [#6199](https://github.com/nextcloud/spreed/pull/6199)
- Fix blocked audio recording preview when Talk is embedded in the sidebar
  [#6130](https://github.com/nextcloud/spreed/pull/6130)
- Fix infinite loop when the media constraints can not be decreased
  [#6126](https://github.com/nextcloud/spreed/pull/6126)
- Fix connection quality warning not disappearing when media is stopped
  [#6146](https://github.com/nextcloud/spreed/pull/6146)
- Send offer again when own peer was not initially connected
  [#6080](https://github.com/nextcloud/spreed/pull/6080)

## 12.1.1 – 2021-08-30
### Changed
- Improved device preference storing to better recover after switching between docking-station and normal usage with a device
  [#6179](https://github.com/nextcloud/spreed/pull/6179)

### Fixed
- Fix laggy video with Full HD 60 FPS webcams in Chrome and Chromium
  [#6159](https://github.com/nextcloud/spreed/pull/6159)
- Fix infinite loop when video constrains can not be satisfied
  [#6125](https://github.com/nextcloud/spreed/pull/6125)
- Fix connection quality warning after media was stopped
  [#6147](https://github.com/nextcloud/spreed/pull/6147)
- Hide forward action for guests
  [#6143](https://github.com/nextcloud/spreed/pull/6143)
- Don't select the own video when trying to open the video settings
  [#6158](https://github.com/nextcloud/spreed/pull/6158)
- Fix issues with several popovers in fullscreen mode or sidebar mode
  [#6155](https://github.com/nextcloud/spreed/pull/6155)
- Fix issue with video recording in sidebar mode
  [#6129](https://github.com/nextcloud/spreed/pull/6129)
  [#6131](https://github.com/nextcloud/spreed/pull/6131)

## 12.1.0 – 2021-08-10
### Added
- Allow to forward messages to another chat
  [#6053](https://github.com/nextcloud/spreed/pull/6053)
  [#6057](https://github.com/nextcloud/spreed/pull/6057)
  [#6076](https://github.com/nextcloud/spreed/pull/6076)
- Allow to clear chat history
  [#6052](https://github.com/nextcloud/spreed/pull/6052)
  [#5971](https://github.com/nextcloud/spreed/pull/5971)

### Changed
- Add "missed call" chat system message for one-to-one calls
  [#6031](https://github.com/nextcloud/spreed/pull/6031)

### Fixed
- Remove div tags when pasting or writing multiline messages in Safari
  [#6086](https://github.com/nextcloud/spreed/pull/6086)
- Add list of "What's new in Talk 12"
  [#6050](https://github.com/nextcloud/spreed/pull/6050)

## 12.0.1 – 2021-07-15
### Fixed
- Unshare all items directly when deleting a room
  [#5975](https://github.com/nextcloud/spreed/pull/5975)
- Fix date picker for lobby not being visible
  [#5984](https://github.com/nextcloud/spreed/pull/5984)
- Fix initial camera quality with Chromium and Chromebased browsers
  [#6000](https://github.com/nextcloud/spreed/pull/6000)
- Wait for the wav encoder to be initialized before allowing recordings
  [#6012](https://github.com/nextcloud/spreed/pull/6012)
  [#6014](https://github.com/nextcloud/spreed/pull/6014)
- Fix displaying moderation options in one-to-ones
  [#6008](https://github.com/nextcloud/spreed/pull/6008)
- Never show read marker on the very last message
  [#5969](https://github.com/nextcloud/spreed/pull/5969)

## 12.0.0 – 2021-07-06
### Added
- Add support for simulcast streams when the high-performance backend is used
  [#5535](https://github.com/nextcloud/spreed/pull/5535)
- Allow users to have multiple session in the same conversation
  [#5194](https://github.com/nextcloud/spreed/pull/5194)
- Voice messages, location sharing and contacts sharing
  [#5610](https://github.com/nextcloud/spreed/pull/5610)
  [#5573](https://github.com/nextcloud/spreed/pull/5573)
  [#5731](https://github.com/nextcloud/spreed/pull/5731)
- Play a sound when a participant joins or leaves the call
  [#5410](https://github.com/nextcloud/spreed/pull/5410)
- Add unread message marker in the chat view
  [#3825](https://github.com/nextcloud/spreed/pull/3825)
- Show a "new message" indicator while being in the call
  [#5534](https://github.com/nextcloud/spreed/pull/5534)
- Sync group members with conversation participants
  [#4810](https://github.com/nextcloud/spreed/pull/4810)
- Add an option to reply privately
  [#4855](https://github.com/nextcloud/spreed/pull/4855)
- Conversation TopBar with icon, name and description
  [#5596](https://github.com/nextcloud/spreed/pull/5596)

### Changed
- Use all defined STUN and TURN servers instead of a random one
  [#5503](https://github.com/nextcloud/spreed/pull/5503)
  [#5491](https://github.com/nextcloud/spreed/pull/5491)
- Compatibility with Nextcloud 22

### Fixed
- Allow to mention groupfolder users in file chats
  [#4246](https://github.com/nextcloud/spreed/pull/4246)
- Fix not sending signaling messages to participants without a Peer object
  [#4686](https://github.com/nextcloud/spreed/pull/4686)
- Fix several issues with video, stream and screenshare selections

### Removed
- 🏁 Conversations API v1, v2 and v3
- 🏁 Call API v1, v2 and v3
- 🏁 Signaling API v1 and v2
- 🏁 Support for Internet Explorer

