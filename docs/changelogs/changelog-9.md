<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 9.0.10 – 2021-06-04
### Fixed
- Regenerate session id after entering conversation password
  [#5642](https://github.com/nextcloud/spreed/pull/5642)

## 9.0.9 – 2021-02-22
### Fixed
- Fix collaboration resource options not loading
  [#5143](https://github.com/nextcloud/spreed/pull/5143)
- Fixed a bug that would prevent attachments going into the Talk/ folder
  [#5080](https://github.com/nextcloud/spreed/pull/5080)

## 9.0.8 – 2021-01-08
### Fixed
- Don't remove a chat when a self-joined user leaves
  [#4903](https://github.com/nextcloud/spreed/pull/4903)

## 9.0.7 – 2020-12-18
### Fixed
- Fix potentially multiple guests joining in a password request conversation
  [#4797](https://github.com/nextcloud/spreed/pull/4797)

## 9.0.6 – 2020-11-17
### Fixed
- Reduce the load when mounting many shares
  [#4510](https://github.com/nextcloud/spreed/pull/4510)
- Fix handling of unavailable commands
  [#4578](https://github.com/nextcloud/spreed/pull/4578)
- Correctly delete a conversation when the last moderator leaves
  [#4499](https://github.com/nextcloud/spreed/pull/4499)

## 9.0.5 – 2020-10-23
### Fixed
- Remove pulse animation from call button to reduce CPU load in chrome-based browsers
  [#4302](https://github.com/nextcloud/spreed/pull/4302)
- Only show the "Session conflict" dialog when in a call
  [#4443](https://github.com/nextcloud/spreed/pull/4443)
- Fix minimum length calculation of the call token
  [#4370](https://github.com/nextcloud/spreed/pull/4370)
- Fix duplicate session issue in files sidebar
  [#4427](https://github.com/nextcloud/spreed/pull/4427)
- Lobby date not shown in the moderator menu
  [#4323](https://github.com/nextcloud/spreed/pull/4323)

## 9.0.4 – 2020-09-17
### Added
- Moderators can now mute all participants with a single button in the moderator menu
  [#4054](https://github.com/nextcloud/spreed/pull/4054)

### Fixed
- Prevent infinite loop when opening a data channel and retransmitting old messages
  [#4069](https://github.com/nextcloud/spreed/pull/4069)
- Installation on Oracle fails
  [#4129](https://github.com/nextcloud/spreed/pull/4129)
- Fix clientside memory leaks due to missing unsubscribe of events when destroying interface components
  [#4148](https://github.com/nextcloud/spreed/pull/4148)
  [#4156](https://github.com/nextcloud/spreed/pull/4156)
  [#4160](https://github.com/nextcloud/spreed/pull/4160)
  [#4162](https://github.com/nextcloud/spreed/pull/4162)

## 9.0.3 – 2020-08-04
### Fixed
- Fix compatibility with Janus 0.10.4 and the newest High-performance backend
  [#3940](https://github.com/nextcloud/spreed/pull/3940)
  [#3979](https://github.com/nextcloud/spreed/pull/3979)
- Allow Internet Explorer 11 to render the page again
  [#3967](https://github.com/nextcloud/spreed/pull/3967)
  [#3982](https://github.com/nextcloud/spreed/pull/3982)
- Only show the browser warning when interacting with Nextcloud Talk features
  [#3978](https://github.com/nextcloud/spreed/pull/3978)
- Avatar menu is opaque for participants that are offline
  [#3959](https://github.com/nextcloud/spreed/pull/3959)
- Always allow to communicate with the HPB even when it's a local url
  [#3965](https://github.com/nextcloud/spreed/pull/3965)

## 9.0.2 – 2020-07-21
### Added
- Warn the user when their connection or computer is busy and others might not be able to see or hear them correctly anymore.
  [#3923](https://github.com/nextcloud/spreed/pull/3923)
- Warn the user when joining twice in the same conversation which breaks the calling experience for others.
  [#3866](https://github.com/nextcloud/spreed/pull/3866)

### Changed
- Improve default push notification text for upcoming iOS 13 SDK changes in the iOS mobile app
  [#3847](https://github.com/nextcloud/spreed/pull/3847)

### Fixed
- Fix timeout issue with users having only numeric ids
  [#3791](https://github.com/nextcloud/spreed/pull/3791)
- Fix a failure on logout when an active session was removed from a conversation already
  [#3869](https://github.com/nextcloud/spreed/pull/3869)
- Unify the blur on all videos to a same higher factor
  [#3821](https://github.com/nextcloud/spreed/pull/3821)
  [#3887](https://github.com/nextcloud/spreed/pull/3887)
- Fix attachment selection when default folder can not be created.
  [#3797](https://github.com/nextcloud/spreed/pull/3797)
- Update lib to parse links in chat messages to fix an issue with trailing spaces
  [#3924](https://github.com/nextcloud/spreed/pull/3924)

## 9.0.1 – 2020-06-08
### Added
- Store the guest name in browser storage again and read it from there
  [#3709](https://github.com/nextcloud/spreed/pull/3709)
- Automatically switch to promoted view upon screenshare
  [#3711](https://github.com/nextcloud/spreed/pull/3711)
- Added autocomplete support for talk:room:* commands
  [#3728](https://github.com/nextcloud/spreed/pull/3728)

### Changed
- Reload page on changing conversation and disable start/join call when Talk UI needs reloading
  [#3699](https://github.com/nextcloud/spreed/pull/3699)
- Show sidebar when Viewer is opened
  [#3679](https://github.com/nextcloud/spreed/pull/3679)

### Fixed
- Properly sort participants by display name again
  [#3763](https://github.com/nextcloud/spreed/pull/3763)
- Make joining the call synchronous to avoid toggling in and out of the callview
  [#3682](https://github.com/nextcloud/spreed/pull/3682)
- Do not fetch all conversations on some signaling events
  [#3673](https://github.com/nextcloud/spreed/pull/3673)
- Reduce database load when sending out push notifications
  [#3782](https://github.com/nextcloud/spreed/pull/3782)
- Remove automatic ping when getting chat messages via the web UI
  [#3784](https://github.com/nextcloud/spreed/pull/3784)
- Always try to connect without camera in case it failed
  [#3780](https://github.com/nextcloud/spreed/pull/3780)
- Fix talk:room:* commands showing "Guest" as actor in the chat
  [#3754](https://github.com/nextcloud/spreed/pull/3754)
- Do not break LessThan3 in last message of conversations list
  [#3705](https://github.com/nextcloud/spreed/pull/3705)

## 9.0.0 – 2020-05-26
### Added
- Added a grid view for calls and made the promoted view more usable in huge calls as well as one-to-one calls
  [#1056](https://github.com/nextcloud/spreed/pull/1056)
  [#3569](https://github.com/nextcloud/spreed/pull/3569)
- Allow to use multiple High-performance backends in parallel for different conversations
  [#3292](https://github.com/nextcloud/spreed/pull/3292)
  [#3605](https://github.com/nextcloud/spreed/pull/3605)
- Allow selecting which video is shown big in the promoted view
  [#3497](https://github.com/nextcloud/spreed/pull/3497)
- Open files with the viewer apps registered in Nextcloud
  [#2778](https://github.com/nextcloud/spreed/pull/2778)
- Allow to upload and drag'n'drop files into the chat
  [#2891](https://github.com/nextcloud/spreed/pull/2891)
  [#3045](https://github.com/nextcloud/spreed/pull/3045)
- Allow pasting images/screenshot directly into the chat
  [#3399](https://github.com/nextcloud/spreed/pull/3399)
- Allow selecting a directory for shared files
  [#2876](https://github.com/nextcloud/spreed/pull/2876)
  [#2983](https://github.com/nextcloud/spreed/pull/2983)
- Allow to limit creating public and group conversations to a group
  [#3095](https://github.com/nextcloud/spreed/pull/3095)
- Added OCC commands to do basic administrations of conversations
  [#3465](https://github.com/nextcloud/spreed/pull/3465)
- Allow guests to set their name while waiting in the lobby
  [#3133](https://github.com/nextcloud/spreed/pull/3133)
- Allow moderators to turn off the microphone of participants
  [#3015](https://github.com/nextcloud/spreed/pull/3015)

### Changed
- Reduce CPU usage when doing a video call
  [#3413](https://github.com/nextcloud/spreed/pull/3413)
- Automatic scaling of video quality to allow bigger video calls to further reduce required CPU and bandwidth
  [#3419](https://github.com/nextcloud/spreed/pull/3419)
- Notify users when talk was updated in the background and a reload is necessary
  [#3336](https://github.com/nextcloud/spreed/pull/3336)
- Adjust color of leave and join buttons to common styles
  [#3398](https://github.com/nextcloud/spreed/pull/3398)
  [#3348](https://github.com/nextcloud/spreed/pull/3348)

### Fixed
- Try harder to connect with microphone when camera is not readable
  [#3474](https://github.com/nextcloud/spreed/pull/3474)
- Fix multiple issues when the connection was interrupted
  [#3383](https://github.com/nextcloud/spreed/pull/3383)
  [#3457](https://github.com/nextcloud/spreed/pull/3457)
  [#3460](https://github.com/nextcloud/spreed/pull/3460)
  [#3456](https://github.com/nextcloud/spreed/pull/3456)
  [#3402](https://github.com/nextcloud/spreed/pull/3402)

