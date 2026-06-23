<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 8.0.15 – 2021-01-08
### Fixed
- Don't remove a chat when a self-joined user leaves
  [#4904](https://github.com/nextcloud/spreed/pull/4904)

## 8.0.14 – 2020-12-18
### Fixed
- Fix potentially multiple guests joining in a password request conversation
  [#4796](https://github.com/nextcloud/spreed/pull/4796)

## 8.0.13 – 2020-11-17
### Fixed
- Reduce the load when mounting many shares
  [#4511](https://github.com/nextcloud/spreed/pull/4511)
- Fix handling of unavailable commands
  [#4579](https://github.com/nextcloud/spreed/pull/4579)
- Correctly delete a conversation when the last moderator leaves
  [#4611](https://github.com/nextcloud/spreed/pull/4611)

## 8.0.12 – 2020-10-23
### Fixed
- Remove pulse animation from call button to reduce CPU load in chrome-based browsers
  [#4303](https://github.com/nextcloud/spreed/pull/4303)
- Fix minimum length calculation of the call token
  [#4371](https://github.com/nextcloud/spreed/pull/4371)
- Fix duplicate session issue in files sidebar
  [#4428](https://github.com/nextcloud/spreed/pull/4428)

## 8.0.11 – 2020-09-17
### Fixed
- Internet Explorer 11 unable to load the interface
  [#3983](https://github.com/nextcloud/spreed/pull/3983)
- Speaker promotion with newer Janus versions
  [#3952](https://github.com/nextcloud/spreed/pull/3952)
- Prevent infinite loop when opening a data channel and retransmitting old messages
  [#4070](https://github.com/nextcloud/spreed/pull/4070)
- Installation on Oracle fails
  [#4130](https://github.com/nextcloud/spreed/pull/4130)
- Fix clientside memory leaks due to missing unsubscribe of events when destroying interface components
  [#4149](https://github.com/nextcloud/spreed/pull/4149)
  [#4161](https://github.com/nextcloud/spreed/pull/4161)
  [#4163](https://github.com/nextcloud/spreed/pull/4163)

## 8.0.10 – 2020-07-21
### Added
- Warn the user when their connection or computer is busy and others might not be able to see or hear them correctly anymore.
  [#3899](https://github.com/nextcloud/spreed/pull/3899)

### Changed
- Improve default push notification text for upcoming iOS 13 SDK changes in the iOS mobile app
  [#3848](https://github.com/nextcloud/spreed/pull/3848)

### Fixed
- Always try to connect without camera in case it failed
  [#3781](https://github.com/nextcloud/spreed/pull/3781)
- Update lib to parse links in chat messages to fix an issue with trailing spaces
  [#3925](https://github.com/nextcloud/spreed/pull/3925)
- Remove automatic ping when getting chat messages via the web UI
  [#3793](https://github.com/nextcloud/spreed/pull/3793)

## 8.0.9 – 2020-05-13
### Changed
- Reduce CPU usage when doing a video call
  [#3414](https://github.com/nextcloud/spreed/pull/3414)
- Automatic scaling of video quality to allow bigger video calls to further reduce required CPU and bandwidth
  [#3468](https://github.com/nextcloud/spreed/pull/3468)
- Notify users when talk was updated in the background and a reload is necessary
  [#3373](https://github.com/nextcloud/spreed/pull/3373)
- Improve the layout of the video stripe when the videos don't fit anymore
  [#3433](https://github.com/nextcloud/spreed/pull/3433)

### Fixed
- Guest names not shown in video calls with the HPB
  [#3502](https://github.com/nextcloud/spreed/pull/3502)
- Don't mark the tab "unread" for own messages and messages you read already
  [#3378](https://github.com/nextcloud/spreed/pull/3378)
- Try harder to connect with microphone when camera is not readable
  [#3494](https://github.com/nextcloud/spreed/pull/3494)
- Fix multiple issues when the connection was interrupted
  [#3405](https://github.com/nextcloud/spreed/pull/3405)
  [#3461](https://github.com/nextcloud/spreed/pull/3461)
  [#3467](https://github.com/nextcloud/spreed/pull/3467)
  [#3466](https://github.com/nextcloud/spreed/pull/3466)
  [#3452](https://github.com/nextcloud/spreed/pull/3452)
- Fix a type error while pinging the sessions with the HPB
  [#3375](https://github.com/nextcloud/spreed/pull/3375)

## 8.0.8 – 2020-04-20
### Changed
- Show a warning to users when they use an unsupported browser already when chatting
  [#3296](https://github.com/nextcloud/spreed/pull/3296)

### Fixed
- Further performance improvements when interacting with participant sessions
  [#3345](https://github.com/nextcloud/spreed/pull/3345)
  [#3322](https://github.com/nextcloud/spreed/pull/3322)
- Show a warning and error in case the signaling connection is failing
  [#3288](https://github.com/nextcloud/spreed/pull/3288)
- Fix missing header element to make Chrome aware of the screenshare extension
  [#3281](https://github.com/nextcloud/spreed/pull/3281)
- Don't error when a guest tries to open the Talk app without a conversation token
  [#3344](https://github.com/nextcloud/spreed/pull/3344)
- Removed unnecessary double-quote argument parameter from commands
  [#3362](https://github.com/nextcloud/spreed/pull/3362)

## 8.0.7 – 2020-04-02
### Fixed
- Calls in files and public sharing sidebar don't work
  [#3241](https://github.com/nextcloud/spreed/pull/3241)
- Add another missing index to the participants table to reduce the load
  [#3239](https://github.com/nextcloud/spreed/pull/3239)
- Fix blank page on Internet Explorer 11
  [#3240](https://github.com/nextcloud/spreed/pull/3240)

## 8.0.6 – 2020-04-01
### Added
- Remember the video/audio setting per conversation
  [#3205](https://github.com/nextcloud/spreed/pull/3205)
- Added the join button to the "Alice started a call" message to make it easier to join
  [#3135](https://github.com/nextcloud/spreed/pull/3135)
- Added a warning when no TURN server is configured but no ICE connection could be established
  [#3162](https://github.com/nextcloud/spreed/pull/3162)
- Added more aria labels to support screenreaders
  [#3215](https://github.com/nextcloud/spreed/pull/3215)
- **Tech Preview:** Added a setting to prefer H.264 video codec
  [#3224](https://github.com/nextcloud/spreed/pull/3224)

### Changed
- Allow guests to set their name while they are already in a call
  [#3169](https://github.com/nextcloud/spreed/pull/3169)
- Automatically hide the left sidebar when in full screen in a call
  [#3158](https://github.com/nextcloud/spreed/pull/3158)

### Fixed
- Fix unnecessary high load when users update their room list (every 30 seconds)
  [#3225](https://github.com/nextcloud/spreed/pull/3225)
- Fix videos being overlayed with the video/audio control icons
  [#3149](https://github.com/nextcloud/spreed/pull/3149)
- Correctly load/hide the guest avatar based on the WebRTC connection state
  [#3196](https://github.com/nextcloud/spreed/pull/3196)
- Stop signaling correctly when switching to another conversation
  [#3200](https://github.com/nextcloud/spreed/pull/3200)
- Do not constantly try to reconnect to peers without any streams
  [#3199](https://github.com/nextcloud/spreed/pull/3199)
- Do not retrigger an update of the participant list when it is already being updated
  [#3202](https://github.com/nextcloud/spreed/pull/3202)
- Fix a problem when trying to set a password with question marks or hash sign
  [#3145](https://github.com/nextcloud/spreed/pull/3145)
- Fix issues when a user or guest is being demoted from moderators while already being in a call with the lobby enabled with the external signaling server
  [#3057](https://github.com/nextcloud/spreed/pull/3057)
- Fix issues with interrupted audio streams when closing the sidebar in the files app while having a call
  [#3044](https://github.com/nextcloud/spreed/pull/3044)

## 8.0.5 – 2020-03-03
### Added
- Add a link to the file in the conversation info for file conversations
  [#2974](https://github.com/nextcloud/spreed/pull/2974)

### Fixed
- Fix copy and paste behaviour with WebKit based browsers
  [#2982](https://github.com/nextcloud/spreed/pull/2982)
- Improve chat loading for guests to be fast again
  [#3026](https://github.com/nextcloud/spreed/pull/3026)
- Improve reaction time to conversation status changes for guests
  [#3038](https://github.com/nextcloud/spreed/pull/3038)
- Fix collision on (empty) tab id with systemtags app in the sidebar
  [#2964](https://github.com/nextcloud/spreed/pull/2964)
- Focus the chat input in the sidebar when opening the chat tab
  [#2975](https://github.com/nextcloud/spreed/pull/2975)
- Also end call state of conversation when the last user leaves, has a timeout or logs out directly
  [#2952](https://github.com/nextcloud/spreed/pull/2952)
  [#2984](https://github.com/nextcloud/spreed/pull/2984)
- Await response of "Leave call" before leaving the conversation
  [#2951](https://github.com/nextcloud/spreed/pull/2951)
- Don't provide a default stun when the server has no internet connection
  [#2982](https://github.com/nextcloud/spreed/pull/2982)
  [#3028](https://github.com/nextcloud/spreed/pull/3028)
- Fix call notifications stating "You missed a call" with a lot of participants
  [#2945](https://github.com/nextcloud/spreed/pull/2945)
- Only try to join and leave real conversations when opening the files sidebar
  [#2977](https://github.com/nextcloud/spreed/pull/2977)


## 8.0.4 – 2020-02-11
### Added
- Readd fullscreen option for the interface with f as a shortcut
  [#2937](https://github.com/nextcloud/spreed/pull/2937)

### Fixed
- Fix Files sidebar integration, public share page and video verification
  [#2935](https://github.com/nextcloud/spreed/pull/2935)

## 8.0.3 – 2020-02-10
### Fixed
- Fix calls not working anymore due to error when handling signaling messages
  [#2928](https://github.com/nextcloud/spreed/pull/2928)
- Do not show favorite and call icon overlapping each others
  [#2927](https://github.com/nextcloud/spreed/pull/2927)
- Fix issues in the participants list when there are multiple guests
  [#2929](https://github.com/nextcloud/spreed/pull/2929)
- Fix error in console when adding a conversation to favorites
  [#2930](https://github.com/nextcloud/spreed/pull/2930)

## 8.0.2 – 2020-02-07
### Added
- Allow admins to select a default notification level for group conversations
  [#2903](https://github.com/nextcloud/spreed/pull/2903)
- Add a red video icon to the conversation list when a call is in progress
  [#2910](https://github.com/nextcloud/spreed/pull/2910)

### Changed
- Make unread message counter and last message preview more responsive
  [#2865](https://github.com/nextcloud/spreed/pull/2865)
  [#2904](https://github.com/nextcloud/spreed/pull/2904)
- Further improve the dialog to create group conversations
  [#2878](https://github.com/nextcloud/spreed/pull/2878)

### Fixed
- Improve loading performance of chats
  [#2901](https://github.com/nextcloud/spreed/pull/2901)
- Continue scrolling a conversation when new messages arrive while the tab is inactive
  [#2901](https://github.com/nextcloud/spreed/pull/2901)
- Do not send last message again when hitting enter on an empty input field
  [#2868](https://github.com/nextcloud/spreed/pull/2868)
- Fix flows to correctly send notifications on mentions
  [#2867](https://github.com/nextcloud/spreed/pull/2867)
- Reduce server load when using autocomplete on chat mentions
  [#2871](https://github.com/nextcloud/spreed/pull/2871)

## 8.0.1 – 2020-01-27
### Added
- Add details to "New in Talk 8" list
  [#2812](https://github.com/nextcloud/spreed/pull/2812)
- Allow to "right click" > "Copy link address" on the conversations list again
  [#2832](https://github.com/nextcloud/spreed/pull/2832)
- Add an indicator to the participant list if a user is in the call
  [#2840](https://github.com/nextcloud/spreed/pull/2840)

### Changed
- Require confirmation before deleting a conversation
  [#2843](https://github.com/nextcloud/spreed/pull/2843)

### Fixed
- Re-add missing shortcuts for turning on/off audio (m) and video (v)
  [#2828](https://github.com/nextcloud/spreed/pull/2828)
- Fix visiting index.php/ links when rewrite is enabled
  [#2833](https://github.com/nextcloud/spreed/pull/2833)
- Adding a circle does not correctly add all accepted members
  [#2834](https://github.com/nextcloud/spreed/pull/2834)
- Correctly handle guest names in chats and calls when they are changed
  [#2849](https://github.com/nextcloud/spreed/pull/2849)
- Contacts menu not redirecting to one-to-one conversations on "Talk to …"
  [#2809](https://github.com/nextcloud/spreed/pull/2809)
- Increase tolerence for automatically show new messages and scroll to bottom
  [#2821](https://github.com/nextcloud/spreed/pull/2821)

## 8.0.0 – 2020-01-17
### Added
- Recreation of the frontend in Vue.JS
- Allow to reply directly to messages
- Filter the conversations and participants list when searching
- Ask for confirmation when a user navigates away while being in a call
- Support for circles when creating a new conversation and adding participants
- Add a first version of Flow support

### Changed
- Allow to write multiple chat messages in a row
- Improve the way how conversations are created
- Make single emojis bigger to improve readability

### Fixed
- A lot of fixes, see [Github](https://github.com/nextcloud/spreed/milestone/27?closed=1) for a complete list

