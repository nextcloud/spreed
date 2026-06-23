<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 11.3.6 – 2022-03-17
### Fixed
- Fix several modals, dialogs and popovers in fullscreen mode
  [#6886](https://github.com/nextcloud/spreed/pull/6886)
- Fix mentions inside brackets
  [#6871](https://github.com/nextcloud/spreed/pull/6871)

## 11.3.5 – 2022-01-24
### Fixed
- Allow joining open conversations which are also shared as link with a password
  [#6711](https://github.com/nextcloud/spreed/pull/6711)
- Don't force a signaling mode when starting/ending the HPB trial
  [#6824](https://github.com/nextcloud/spreed/pull/6824)
- Add conversation token and message id to search results
  [#6747](https://github.com/nextcloud/spreed/pull/6747)

## 11.3.4 – 2021-12-07
### Changed
- Remove Plan-B option from the HPB integration
  [#6449](https://github.com/nextcloud/spreed/pull/6449)
- Allow apps to override/modify the TURN server list
  [#6430](https://github.com/nextcloud/spreed/pull/6430)

### Fixed
- Properly allow sha256 checksums for reference ids as advertised
  [#6529](https://github.com/nextcloud/spreed/pull/6529)
- Limit deck integration to the current instance for now
  [#6414](https://github.com/nextcloud/spreed/pull/6414)

## 11.3.3 – 2021-10-22
### Fixed
- Fix crash of Chrome/Chromium 95
  [#6384](https://github.com/nextcloud/spreed/pull/6384)

## 11.3.2 – 2021-09-17
### Fixed
- Fix logged-in users are unable to join a password protected public conversation
  [#6231](https://github.com/nextcloud/spreed/pull/6231)
- Don't toggle the video on/off when pasting files into the chat and first releasing the CTRL key
  [#6200](https://github.com/nextcloud/spreed/pull/6200)
- Fix infinite loop when the media constraints can not be decreased
  [#6127](https://github.com/nextcloud/spreed/pull/6127)
- Fix connection quality warning not disappearing when media is stopped
  [#6148](https://github.com/nextcloud/spreed/pull/6148)
- Send offer again when own peer was not initially connected
  [#6081](https://github.com/nextcloud/spreed/pull/6081)
- Don't refresh the room when the participant list changes
  [#5786](https://github.com/nextcloud/spreed/pull/5786)

## 11.3.1 – 2021-07-15
### Fixed
- Add UI feedback when local participant is not connected
  [#5797](https://github.com/nextcloud/spreed/pull/5797)
- Allow to open files with ctrl click
  [#5761](https://github.com/nextcloud/spreed/pull/5761)
- Prevent submitting the message when the user is composing a character
  [#5941](https://github.com/nextcloud/spreed/pull/5941)
- Fix connection quality warning shown due to stalled stats
  [#5924](https://github.com/nextcloud/spreed/pull/5924)
- Fix connection quality stats not reset when setting a new peer connection
  [#5768](https://github.com/nextcloud/spreed/pull/5768)
- Ignore current participant when listing rooms if removed concurrently
  [#5758](https://github.com/nextcloud/spreed/pull/5758)
  [#5740](https://github.com/nextcloud/spreed/pull/5740)
- Fix links to documentation
  [#5947](https://github.com/nextcloud/spreed/pull/5947)

## 11.3.0 – 2021-06-04
### Fixed
- Inject the preloaded user status into the avatar component
  [#5694](https://github.com/nextcloud/spreed/pull/5694)
- Fix switching devices in Firefox
  [#5580](https://github.com/nextcloud/spreed/pull/5580)
- Fix switching devices during a call if started without audio nor video
  [#5662](https://github.com/nextcloud/spreed/pull/5662)
- Redirect to not-found page while in a call
  [#5601](https://github.com/nextcloud/spreed/pull/5601)
- Regenerate session id after entering conversation password
  [#5638](https://github.com/nextcloud/spreed/pull/5638)
- Fix a problem when a deleted user is recreated again
  [#5643](https://github.com/nextcloud/spreed/pull/5643)
- Encode dav path segments for direct GIF preview
  [#5674](https://github.com/nextcloud/spreed/pull/5674)
- Fix raised hand handler not detached when a participant leaves
  [#5676](https://github.com/nextcloud/spreed/pull/5676)
- Register flow operation via dedicated instead of legacy event
  [#5650](https://github.com/nextcloud/spreed/pull/5650)

## 11.2.2 – 2021-06-04
### Fixed
- Regenerate session id after entering conversation password
  [#5639](https://github.com/nextcloud/spreed/pull/5639)
- Fix a problem when a deleted user is recreated again
  [#5644](https://github.com/nextcloud/spreed/pull/5644)
- Encode dav path segments for direct GIF preview
  [#5692](https://github.com/nextcloud/spreed/pull/5692)
- Fix raised hand handler not detached when a participant leaves
  [#5677](https://github.com/nextcloud/spreed/pull/5677)
- Register flow operation via dedicated instead of legacy event
  [#5651](https://github.com/nextcloud/spreed/pull/5651)

## 11.2.1 – 2021-05-06
### Fixed
- Fix redirect when deleting the current conversation
  [#5315](https://github.com/nextcloud/spreed/pull/5315)
- Reset the page title when the conversation is not found
  [#5493](https://github.com/nextcloud/spreed/pull/5493)
- Fix quality warning appearing again in certain conditions
  [#5552](https://github.com/nextcloud/spreed/pull/5552)
- Fix camera quality starting bad in some cases
  [#5556](https://github.com/nextcloud/spreed/pull/5556)
- Allow to copy the link of open conversations which the user didn't join
  [#5562](https://github.com/nextcloud/spreed/pull/5562)
- Fix search focus when typing in the conversation search
  [#5566](https://github.com/nextcloud/spreed/pull/5566)
- Fix sorting of users which set their user status to offline
  [#5569](https://github.com/nextcloud/spreed/pull/5569)

## 11.2.0 – 2021-04-12
### Added
- Added a temporary OCS Api for clients to upload avatars
  [#5401](https://github.com/nextcloud/spreed/pull/5401)

### Changed
- Direct reply button in message row
  [#5361](https://github.com/nextcloud/spreed/pull/5361)

### Fixed
- Show error notification also when hello signaling message fails
  [#5344](https://github.com/nextcloud/spreed/pull/5344)
- Fix UI feedback when remote participants lose connection
  [#5345](https://github.com/nextcloud/spreed/pull/5345)
- Handle failed server requests more gracefully
  [#5455](https://github.com/nextcloud/spreed/pull/5455)

- Only use the local file as preview for some types when uploading
  [#5423](https://github.com/nextcloud/spreed/pull/5423)
- Fix an issue with the migration to the new attendees table
  [#5427](https://github.com/nextcloud/spreed/pull/5427)
- Fix the background job checking the schema
  [#5374](https://github.com/nextcloud/spreed/pull/5374)
- Fix a bug with the raised hand of users that disconnect
  [#5418](https://github.com/nextcloud/spreed/pull/5418)

## 11.1.2 – 2021-04-12
### Fixed
- Only use the local file as preview for some types when uploading
  [#5424](https://github.com/nextcloud/spreed/pull/5424)
- Fix an issue with the migration to the new attendees table
  [#5428](https://github.com/nextcloud/spreed/pull/5428)
- Fix the background job checking the schema
  [#5373](https://github.com/nextcloud/spreed/pull/5373)
- Fix a bug with the raised hand of users that disconnect
  [#5419](https://github.com/nextcloud/spreed/pull/5419)

## 11.1.1 – 2021-03-04
### Fixed
- Fixed a bug in the migration that could prevent copying all participants to the attendee table
  [#5244](https://github.com/nextcloud/spreed/pull/5244)

## 11.1.0 – 2021-02-23
### Added
- Integrate with Deck to allow posting Deck cards to Talk conversations
  [#5201](https://github.com/nextcloud/spreed/pull/5201)
  [#5202](https://github.com/nextcloud/spreed/pull/5202)
  [#5203](https://github.com/nextcloud/spreed/pull/5203)
- Allow other apps to register message actions, e.g. Deck can create a Deck card out of a chat message
  [#5204](https://github.com/nextcloud/spreed/pull/5204)
- Allow to delete chat messages
  [#5205](https://github.com/nextcloud/spreed/pull/5205)
  [#5206](https://github.com/nextcloud/spreed/pull/5206)
- Add information about callFlags of a conversation to the API so mobile clients can show if it's a audio or video call
  [#5208](https://github.com/nextcloud/spreed/pull/5208)

### Fixed
- Prevent loading old messages twice on scroll which could skip some messages
  [#5209](https://github.com/nextcloud/spreed/pull/5209)

## 11.0.0 – 2021-02-22
### Added
- Implement read status for messages including a privacy setting
  [#4231](https://github.com/nextcloud/spreed/pull/4231)
- Allow moderators to "open" conversations so users can join themselves
  [#4706](https://github.com/nextcloud/spreed/pull/4706)
- Add conversation descriptions
  [#4546](https://github.com/nextcloud/spreed/pull/4546)
- Allow pagination for main grid view
  [#4958](https://github.com/nextcloud/spreed/pull/4958)
- Allow to send messages again when they failed
  [#4975](https://github.com/nextcloud/spreed/pull/4975)
- You can now push to talk/mute with the space key
  [#4328](https://github.com/nextcloud/spreed/pull/4328)
- Added support for turns:// protocol
  [#5087](https://github.com/nextcloud/spreed/pull/5087)
- Add basic support for Opera
  [#4974](https://github.com/nextcloud/spreed/pull/4974)
- Allow resending email invitations
  [#5052](https://github.com/nextcloud/spreed/pull/5052)
- Allow to collapse the video strip to focus more on the promoted speaker or screenshare
  [#4363](https://github.com/nextcloud/spreed/pull/4363)
- Improve previews of images and allow animation of gifs
  [#4472](https://github.com/nextcloud/spreed/pull/4472)
- Allow to "Raise hand" in a call
  [#4569](https://github.com/nextcloud/spreed/pull/4569)
- Compatibility with Nextcloud 21

### Changed
- Bring up the conversation creation-dialog when clicking on a group to prevent accidentally spam
  [#5062](https://github.com/nextcloud/spreed/pull/5062)
- Use different border color when own message is quoted
  [#4940](https://github.com/nextcloud/spreed/pull/4940)
- Move matterbridge settings into the conversation settings dialog
  [#4907](https://github.com/nextcloud/spreed/pull/4907)
- Updated database structure so all tables have a primary key for database cluster support
  [#4735](https://github.com/nextcloud/spreed/pull/4735)

### Fixed
- For more details look at the changelog of the alphas and the rc

## 11.0.0-rc.1 – 2021-02-12
### Added
- Allow resending email invitations
  [#5052](https://github.com/nextcloud/spreed/pull/5052)
- Added support for turns:// protocol
  [#5087](https://github.com/nextcloud/spreed/pull/5087)

### Changed
- Bring up the conversation creation-dialog when clicking on a group to prevent accidentally spam
  [#5062](https://github.com/nextcloud/spreed/pull/5062)

### Fixed
- Fixed a bug that would prevent attachments going into the Talk/ folder
  [#5077](https://github.com/nextcloud/spreed/pull/5077)
- Split the turn test to report whether UDP and/or TCP work
  [#5104](https://github.com/nextcloud/spreed/pull/5104)
- Fix collaboration resource options not loading
  [#5140](https://github.com/nextcloud/spreed/pull/5140)
- Hide the upload option when the user has no quota assigned
  [#5036](https://github.com/nextcloud/spreed/pull/5036)
- Fix mentioning of users with subnames, e.g. "foo" and "foobar"
  [#5041](https://github.com/nextcloud/spreed/pull/5041)
- Fix capabilities check for image preview size
  [#5033](https://github.com/nextcloud/spreed/pull/5033)
- Prevent duplicated call summaries when multiple people leave a call on the HPB within a short time period
  [#5042](https://github.com/nextcloud/spreed/pull/5042)

## 11.0.0-alpha.4 – 2021-01-25
### Added
- Allow pagination for main grid view
  [#4958](https://github.com/nextcloud/spreed/pull/4958)
- Allow line breaks and links in the descriptions
  [#4960](https://github.com/nextcloud/spreed/pull/4960)
- Allow to send messages again when they failed
  [#4975](https://github.com/nextcloud/spreed/pull/4975)
- Add a button to directly disable the lobby
  [#4997](https://github.com/nextcloud/spreed/pull/4997)
- Add basic support for Opera
  [#4974](https://github.com/nextcloud/spreed/pull/4974)

### Changed
- The avatar-blurring in calls has been replaced with the average color to improve performance on Chrome, Chromium, Safari, Edge and Opera
  [#4985](https://github.com/nextcloud/spreed/pull/4985)
- User different border color when own message is quoted
  [#4940](https://github.com/nextcloud/spreed/pull/4940)
- Move matterbridge settings into the conversation settings dialog
  [#4907](https://github.com/nextcloud/spreed/pull/4907)

### Fixed
- Fix Javascript errors with Internet Explorer and Safari
  [#4829](https://github.com/nextcloud/spreed/pull/4829)
  [#4963](https://github.com/nextcloud/spreed/pull/4963)
  [#5008](https://github.com/nextcloud/spreed/pull/5008)
- Show unread one-to-one messages in dashboard widget
  [#4944](https://github.com/nextcloud/spreed/pull/4944)
- Allow to clear the lobby timer value again
  [#4990](https://github.com/nextcloud/spreed/pull/4990)
- Show single video in stripe if screen share in progress
  [#4941](https://github.com/nextcloud/spreed/pull/4941)
- Properly initialize descriptionText with description
  [#4942](https://github.com/nextcloud/spreed/pull/4942)
- Remove system message when a self-joined user leaves a conversation
  [#4933](https://github.com/nextcloud/spreed/pull/4933)
- Fix scrolling of the chat when messages arrive while in background
  [#4979](https://github.com/nextcloud/spreed/pull/4979)
- Fix a bug that prevented disabling sip
  [#4951](https://github.com/nextcloud/spreed/pull/4951)
- Allow SIP dial-in in non-public conversations
  [#4954](https://github.com/nextcloud/spreed/pull/4954)
- Correctly use the displayname for groups in admin settings
  [#4943](https://github.com/nextcloud/spreed/pull/4943)
- Delete messages from UI storage when a conversation is deleted
  [#4949](https://github.com/nextcloud/spreed/pull/4949)
- Add mapping between Nextcloud session id and signaling server session id, to allow linking call users with the rest of the UI
  [#4952](https://github.com/nextcloud/spreed/pull/4952)
- Fix missing display names with sip users
  [#4956](https://github.com/nextcloud/spreed/pull/4956)

## 11.0.0-alpha.3 – 2021-01-08
### Fixed
- Fix chat notifications not being sent when user is not active in a chat
  [#4825](https://github.com/nextcloud/spreed/pull/4825)
  [#4848](https://github.com/nextcloud/spreed/pull/4848)
- Fix CSP violation in Safari with worker-src from avatar blurring
  [#4822](https://github.com/nextcloud/spreed/pull/4822)
- Don't remove a chat when a self-joined user leaves
  [#4892](https://github.com/nextcloud/spreed/pull/4892)
- Avoid double quotes in bridge bot app password
  [#4905](https://github.com/nextcloud/spreed/pull/4905)
- Only scroll to conversations when clicking on search results
  [#4905](https://github.com/nextcloud/spreed/pull/4905)
- Do not load full room object for share queries to save resources on propfinds
  [#4856](https://github.com/nextcloud/spreed/pull/4856)

## 11.0.0-alpha.2 – 2020-12-18
### Added
- Implement read status for messages including a privacy setting
  [#4231](https://github.com/nextcloud/spreed/pull/4231)
- Implement multiple requirements to prepare for SIP dial-in
  [#4324](https://github.com/nextcloud/spreed/pull/4324)
  [#4469](https://github.com/nextcloud/spreed/pull/4496)
  [#4682](https://github.com/nextcloud/spreed/pull/4682)
  [#4689](https://github.com/nextcloud/spreed/pull/4689)
- Allow moderators to make conversations "listable" so users can join themselves
  [#4706](https://github.com/nextcloud/spreed/pull/4706)
- Add the possibility for conversation descriptions
  [#4546](https://github.com/nextcloud/spreed/pull/4546)
- You can now push to talk/mute with the space key
  [#4328](https://github.com/nextcloud/spreed/pull/4328)
- Conversations can now be locked in the moderator settings preventing further chat messages and calls
  [#4331](https://github.com/nextcloud/spreed/pull/4331)
- Allow to collapse the video strip to focus more on the promoted speaker or screenshare
  [#4363](https://github.com/nextcloud/spreed/pull/4363)
- Improve previews of images and allow animation of gifs
  [#4472](https://github.com/nextcloud/spreed/pull/4472)
- Allow to "Raise hand" in a call
  [#4569](https://github.com/nextcloud/spreed/pull/4569)
- Compatibility with Nextcloud 21

### Changed
- Improve setting initial audio and video status when the HPB is used
  [#4181](https://github.com/nextcloud/spreed/pull/4181)
- Remember the Grid view/Promoted speaker selection per conversation in the browser storage and when a screenshare is stopped
  [#4451](https://github.com/nextcloud/spreed/pull/4451)
- Use the new Vue settings modal for user and conversation settings
  [#4195](https://github.com/nextcloud/spreed/pull/4195)
- Updated database structure so all tables have a primary key for database cluster support
  [#4735](https://github.com/nextcloud/spreed/pull/4735)

### Fixed
- Diff against alpha.1: Revert update of @babel/preset-env which breaks the compiled JS
  [#4808](https://github.com/nextcloud/spreed/pull/4808)
- Stop sending the nick through data channels after some time
  [#4182](https://github.com/nextcloud/spreed/pull/4182)
- Don't query guest names for an empty list of guest sessions
  [#4190](https://github.com/nextcloud/spreed/pull/4190)
- Use date-based names for image content that is pasted into the chat
  [#4539](https://github.com/nextcloud/spreed/pull/4539)

