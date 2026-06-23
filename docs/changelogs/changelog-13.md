<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 13.0.11 – 2022-12-01
### Changed
- Allow to disable the changelog conversation with an app config
  [#8366](https://github.com/nextcloud/spreed/pull/8366)

### Fixed
- Fix bottom stripe of speaker view with high DPI
  [#8321](https://github.com/nextcloud/spreed/pull/8321)

## 13.0.10 – 2022-11-03
### Changed
- Take the device pixel ratio into account when calculating minimum grid size (should see more videos now on High DPI settings like MacOS and most 4k setup)
  [#8248](https://github.com/nextcloud/spreed/pull/8248)

### Fixed
- Hide talk dashboard when user can not use the Talk app
  [#8238](https://github.com/nextcloud/spreed/pull/8238)
- Hide talk sidebar integration when user can not use the Talk app
  [#8242](https://github.com/nextcloud/spreed/pull/8242)
- Fix participant sessions not sent to the HPB
  [#8100](https://github.com/nextcloud/spreed/pull/8100)
- Don't search in lobbied conversations
  [#8117](https://github.com/nextcloud/spreed/pull/8117)
- Fix an issue with detecting Safari on iOS version
  [#8136](https://github.com/nextcloud/spreed/pull/8136)

## 13.0.9 – 2022-09-15
### Fixed
- Fix notification sending when the user is blocked by the lobby
  [#7796](https://github.com/nextcloud/spreed/pull/7796)
- Fix missing local media controls in public share sidebar
  [#7759](https://github.com/nextcloud/spreed/pull/7759)
- Fix missing screenshares in sidebar
  [#7763](https://github.com/nextcloud/spreed/pull/7763)
- Fix inconsistent state when leaving a call fails
  [#7804](https://github.com/nextcloud/spreed/pull/7804)

## 13.0.8 – 2022-08-11
### Added
- Extend search result attributes for better handling in mobile clients
  [#7590](https://github.com/nextcloud/spreed/pull/7590)
  [#7589](https://github.com/nextcloud/spreed/pull/7589)

### Fixed
- Reduce sent information with disabled videos
  [#7710](https://github.com/nextcloud/spreed/pull/7710)

## 13.0.7 – 2022-07-08
### Added
- Add brute force protection for conversation tokens and passwords
  [#7536](https://github.com/nextcloud/spreed/pull/7536)
- Add a capability for unified search to enable the feature on the clients
  [#7449](https://github.com/nextcloud/spreed/pull/7449)

### Fixed
- Fix backend URL in request to HPB from command line
  [#7441](https://github.com/nextcloud/spreed/pull/7441)
- Fix error when setting user status while not being in any conversation
  [#7467](https://github.com/nextcloud/spreed/pull/7467)

## 13.0.6 – 2022-05-26
### Fixed
- Ensure display name of conversation owner is stored correctly
  [#7377](https://github.com/nextcloud/spreed/pull/7377)
- Don't show promotion options for circles and groups
  [#7409](https://github.com/nextcloud/spreed/pull/7409)
- Don't show permissions options for circles and groups
  [#7405](https://github.com/nextcloud/spreed/pull/7405)

## 13.0.5 – 2022-04-08
### Fixed
- Fix reconnection when media permissions change
  [#7092](https://github.com/nextcloud/spreed/pull/7092)
- Fix forced reconnection without the High-performance backend
  [#7095](https://github.com/nextcloud/spreed/pull/7095)
- Compatibility with LDAP user backends and more than 64 characters display names
  [#7073](https://github.com/nextcloud/spreed/pull/7073)
- Compatibility with Oracle and MySQL ONLY_FULL_GROUP_BY
  [#7036](https://github.com/nextcloud/spreed/pull/7036)
- Fix broken avatars when search for users to add to a conversation
  [#7037](https://github.com/nextcloud/spreed/pull/7037)
- Fix sort order of guests and logged-in users
  [#7053](https://github.com/nextcloud/spreed/pull/7053)
- Allow copying links of open conversations without joining
  [#7070](https://github.com/nextcloud/spreed/pull/7070)

## 13.0.4 – 2022-03-17
### Fixed
- Fix several modals, dialogs and popovers in fullscreen mode
  [#6885](https://github.com/nextcloud/spreed/pull/6885)
- Fix issues when permissions of a participant or conversation are changed right before joining a call
  [#6996](https://github.com/nextcloud/spreed/pull/6996)
  [#7018](https://github.com/nextcloud/spreed/pull/7018)
- Fix media automatically enabled after selecting a device during a call
  [#7017](https://github.com/nextcloud/spreed/pull/7017)
- Fix call flags update when track is disabled
  [#7016](https://github.com/nextcloud/spreed/pull/7016)
- Show the version number also when the HPB backend is not older
  [#6890](https://github.com/nextcloud/spreed/pull/6890)
- Correctly stop waiting sound when someone joins the call
  [#6919](https://github.com/nextcloud/spreed/pull/6919)
- Improve performance when starting a call in a conversation with many participants
  [#6933](https://github.com/nextcloud/spreed/pull/6933)

## 13.0.3 – 2022-02-07
### Fixed
- Fix stopping a screenshare for recipients
  [#6857](https://github.com/nextcloud/spreed/pull/6857)
- Fix switching between screenshares
  [#6850](https://github.com/nextcloud/spreed/pull/6850)
- Fix mentions inside brackets
  [#6869](https://github.com/nextcloud/spreed/pull/6869)
- Allow using mentions with the mouse
  [#6838](https://github.com/nextcloud/spreed/pull/6838)
- Deduplicate round trips when a moderator ends the meeting for everyone
  [#6841](https://github.com/nextcloud/spreed/pull/6841)
- Fix message menu misbehaving when the message scrolls outside of the viewport
  [#6855](https://github.com/nextcloud/spreed/pull/6855)

## 13.0.2 – 2022-01-24
### Changed
- Improve the join/leave sounds to be shorter
  [#6720](https://github.com/nextcloud/spreed/pull/6720)
- Keep emoji picker open when selecting an emoji
  [#6792](https://github.com/nextcloud/spreed/pull/6792)
- Reduce the set of avatar sizes so browser cache hits more often
  [#6799](https://github.com/nextcloud/spreed/pull/6799)
- Improve signaling events for the roomlist
  [#6719](https://github.com/nextcloud/spreed/pull/6719)
- Don't update the participant list when only you joined
  [#6805](https://github.com/nextcloud/spreed/pull/6805)
- Recognize voice messages, object and file shares as unread messages
  [#6826](https://github.com/nextcloud/spreed/pull/6826)

### Fixed
- Allow joining open conversations which are also shared as link with a password
  [#6709](https://github.com/nextcloud/spreed/pull/6709)
- Prevent handleScroll on initial loading of a conversation
  [#6717](https://github.com/nextcloud/spreed/pull/6717)
- Don't force a signaling mode when starting/ending the HPB trial
  [#6822](https://github.com/nextcloud/spreed/pull/6823)
- Add conversation token and message id to search results
  [#6745](https://github.com/nextcloud/spreed/pull/6745)
- Fix enabling background blur when video is disabled
  [#6705](https://github.com/nextcloud/spreed/pull/6705)
- Fix several issues with the own peer when connections are slow
  [#6774](https://github.com/nextcloud/spreed/pull/6774)

## 13.0.1 – 2021-12-13
### Fixed
- Fix various issues with enabling/disabling the camera and background blur
  [#6688](https://github.com/nextcloud/spreed/pull/6688)
- Fix unregistering of user status listener which lead to a memory leak with Nextcloud 23
  [#6643](https://github.com/nextcloud/spreed/pull/6643)
- Device not released when closing settings dialog immediate after opening
  [#6638](https://github.com/nextcloud/spreed/pull/6638)
- Disable emoji picker in read-only conversations
  [#6662](https://github.com/nextcloud/spreed/pull/6662)
- Make conversation settings scrollable again
  [#6682](https://github.com/nextcloud/spreed/pull/6682)
- Make the pagination buttons more and clearly visible in the grid view
  [#6695](https://github.com/nextcloud/spreed/pull/6695)

## 13.0.0 – 2021-11-30
### Added
- Moderators can now set permissions for all or individual users to control if they can enable audio and video, do a screenshare, start a call and ignore the lobby
- When starting a call is limited to moderators, they can now also end the call for all participants
- A device checker makes sure users start with the desired microphone and camera and also allows them to disable the devices before joining a call
- Users can now blur their background (Not supported on Safari)
- Users can now opt-out of call notifications on a conversation basis

### Changed
- The layout of the call view has been redesigned
- The size of the grid view is now limited to 20 videos by default to reduce the performance impact
- Version 0.4.0 of the signaling server of the High-performance backend is now required

### Fixed
- Show more details instead of a loading spinner only in case of connection issues
- And a lot more things here and there

## 13.0.0-rc.4 – 2021-11-25
### Changed
- The segmentation model of the background blur was replaced
  [#6597](https://github.com/nextcloud/spreed/pull/6597)

### Fixed
- Fix missing default permissions when opening the permissions editor
  [#6614](https://github.com/nextcloud/spreed/pull/6614)
- Fix issues with device selection after muting, disconnecting and more
  [#6601](https://github.com/nextcloud/spreed/pull/6601)
  [#6609](https://github.com/nextcloud/spreed/pull/6609)
  [#6615](https://github.com/nextcloud/spreed/pull/6615)
- Fix issues with the background blurring
  [#6594](https://github.com/nextcloud/spreed/pull/6594)
  [#6600](https://github.com/nextcloud/spreed/pull/6600)

## 13.0.0-rc.3 – 2021-11-22
### Fixed
- Fix several issues with the new call view and media controls
  [#6554](https://github.com/nextcloud/spreed/pull/6554)
  [#6571](https://github.com/nextcloud/spreed/pull/6571)
  [#6576](https://github.com/nextcloud/spreed/pull/6576)
  [#6575](https://github.com/nextcloud/spreed/pull/6575)
- Don't always enable devices when the device picker is shown
  [#6567](https://github.com/nextcloud/spreed/pull/6567)
- Opening the conversation settings via the conversation list opened with wrong data
  [#6584](https://github.com/nextcloud/spreed/pull/6584)
- Fix issues with the background blurring
  [#6586](https://github.com/nextcloud/spreed/pull/6586)
- Fix call summary in one-to-one conversations
  [#6564](https://github.com/nextcloud/spreed/pull/6564)

### Known issue
- The video will "zoom in and out" when more than 4 people have video enabled and people with background blur start or stop speaking

## 13.0.0-rc.2 – 2021-11-18
### Fixed
- Fix several issues with the new call view and media controls
  [#6486](https://github.com/nextcloud/spreed/pull/6486)
  [#6489](https://github.com/nextcloud/spreed/pull/6489)
  [#6520](https://github.com/nextcloud/spreed/pull/6520)
  [#6541](https://github.com/nextcloud/spreed/pull/6541)
  [#6543](https://github.com/nextcloud/spreed/pull/6543)
  [#6548](https://github.com/nextcloud/spreed/pull/6548)
- Can not pass the device checker with a small screen
  [#6508](https://github.com/nextcloud/spreed/pull/6508)
  [#6539](https://github.com/nextcloud/spreed/pull/6539)
- Fix several issues with the background blurring
  [#6502](https://github.com/nextcloud/spreed/pull/6502)
  [#6532](https://github.com/nextcloud/spreed/pull/6532)
  [#6535](https://github.com/nextcloud/spreed/pull/6535)
  [#6546](https://github.com/nextcloud/spreed/pull/6546)
  [#6547](https://github.com/nextcloud/spreed/pull/6547)
- Update hasCall state of a conversation when a chat message indicates that
  [#6509](https://github.com/nextcloud/spreed/pull/6509)
- Don't end meeting for everyone when a moderator leaves
  [#6484](https://github.com/nextcloud/spreed/pull/6484)

## 13.0.0-rc.1 – 2021-11-11
### Added
- Moderators can now set permissions for all or individual users to control if they can enable audio and video, do a screenshare, start a call and ignore the lobby
- When starting a call is limited to moderators, they can now also end the call for all participants
- A device checker makes sure users start with the desired microphone and camera and also allows them to disable the devices before joining a call
- Users can now blur their background (Not supported on Safari)
- Users can now opt-out of call notifications on a conversation basis

### Changed
- The layout of the call view has been redesigned
- The size of the grid view is now limited to 20 videos by default to reduce the performance impact
- Version 0.4.0 of the signaling server of the High-performance backend is now required

### Fixed
- Show more details instead of a loading spinner only in case of connection issues
- And a lot more things here and there

