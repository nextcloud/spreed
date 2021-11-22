# Changelog
All notable changes to this project will be documented in this file.

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

## 10.1.7 – 2021-09-17
### Fixed
- Fix logged-in users are unable to join a password protected public conversation
  [#6232](https://github.com/nextcloud/spreed/pull/6232)
- Fix infinite loop when the media constraints can not be decreased
  [#6240](https://github.com/nextcloud/spreed/pull/6240)

## 10.0.10 – 2021-09-17
### Fixed
- Fix logged-in users are unable to join a password protected public conversation
  [#6233](https://github.com/nextcloud/spreed/pull/6233)
- Fix infinite loop when the media constraints can not be decreased
  [#6128](https://github.com/nextcloud/spreed/pull/6128)

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

## 10.1.6 – 2021-07-15
### Fixed
- Fix connection quality stats not reset when setting a new peer connection
  [#5770](https://github.com/nextcloud/spreed/pull/5770)

## 10.0.9 – 2021-07-15
### Fixed
- Fix connection quality stats not reset when setting a new peer connection
  [#5769](https://github.com/nextcloud/spreed/pull/5769)

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

## 10.1.5 – 2021-06-04
### Fixed
- Regenerate session id after entering conversation password
  [#5640](https://github.com/nextcloud/spreed/pull/5640)
- Fix quality warning appearing again in certain conditions
  [#5553](https://github.com/nextcloud/spreed/pull/5553)
- Fix camera quality starting bad in some cases
  [#5557](https://github.com/nextcloud/spreed/pull/5557)

## 10.0.8 – 2021-06-04
### Fixed
- Regenerate session id after entering conversation password
  [#5641](https://github.com/nextcloud/spreed/pull/5641)
- Fix quality warning appearing again in certain conditions
  [#5555](https://github.com/nextcloud/spreed/pull/5555)
- Fix camera quality starting bad in some cases
  [#5559](https://github.com/nextcloud/spreed/pull/5559)

## 9.0.10 – 2021-06-04
### Fixed
- Regenerate session id after entering conversation password
  [#5642](https://github.com/nextcloud/spreed/pull/5642)

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

## 10.1.4 – 2021-04-12
### Fixed
- Only use the local file as preview for some types when uploading
  [#5425](https://github.com/nextcloud/spreed/pull/5425)
- Fix an issue with the migration to the new attendees table
  [#5245](https://github.com/nextcloud/spreed/pull/5245)
  [#5429](https://github.com/nextcloud/spreed/pull/5429)

## 10.0.7 – 2021-04-12
### Fixed
- Only use the local file as preview for some types when uploading
  [#5426](https://github.com/nextcloud/spreed/pull/5426)

## 11.1.1 – 2021-03-04
### Fixed
- Fixed a bug in the migration that could prevent copying all participants to the attendee table
  [#5244](https://github.com/nextcloud/spreed/pull/5244)

## 10.1.3 – 2021-03-04
### Fixed
- Fixed a bug in the migration that could prevent copying all participants to the attendee table
  [#5245](https://github.com/nextcloud/spreed/pull/5245)

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

## 10.1.2 – 2021-02-22
### Added
- Added pagination to the gridview in case there are too many participants
  [#4991](https://github.com/nextcloud/spreed/pull/4991)

### Changed
- Replace blur with average color in video backgrounds to improve performance on Chrome based browsers
  [#5011](https://github.com/nextcloud/spreed/pull/5011)
- Allow SIP dial-in in non-public conversations too
  [#4955](https://github.com/nextcloud/spreed/pull/4955)

### Fixed
- Drop guest moderators when changing a conversation to group conversation
  [#5231](https://github.com/nextcloud/spreed/pull/5231)
- Fix collaboration resource options not loading
  [#5141](https://github.com/nextcloud/spreed/pull/5141)
- Fixed a bug that would prevent attachments going into the Talk/ folder
  [#5078](https://github.com/nextcloud/spreed/pull/5078)
- Fix mentioning of users with subnames, e.g. "foo" and "foobar"
  [#5050](https://github.com/nextcloud/spreed/pull/5050)
- Add upload editor in files sidebar mode
  [#5113](https://github.com/nextcloud/spreed/pull/5113)

## 10.0.6 – 2021-02-22
### Changed
- Replace blur with average color in video backgrounds to improve performance on Chrome based browsers
  [#5012](https://github.com/nextcloud/spreed/pull/5012)

### Fixed
- Drop guest moderators when changing a conversation to group conversation
  [#5228](https://github.com/nextcloud/spreed/pull/5228)
- Fix collaboration resource options not loading
  [#5142](https://github.com/nextcloud/spreed/pull/5142)
- Fixed a bug that would prevent attachments going into the Talk/ folder
  [#5079](https://github.com/nextcloud/spreed/pull/5079)
- Fix mentioning of users with subnames, e.g. "foo" and "foobar"
  [#5051](https://github.com/nextcloud/spreed/pull/5051)
- Add upload editor in files sidebar mode
  [#5111](https://github.com/nextcloud/spreed/pull/5111)

## 9.0.9 – 2021-02-22
### Fixed
- Fix collaboration resource options not loading
  [#5143](https://github.com/nextcloud/spreed/pull/5143)
- Fixed a bug that would prevent attachments going into the Talk/ folder
  [#5080](https://github.com/nextcloud/spreed/pull/5080)

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

## 10.1.1 – 2021-01-08
### Fixed
- Fix chat notifications not being sent when user is not active in a chat
  [#4869](https://github.com/nextcloud/spreed/pull/4869)
  [#4847](https://github.com/nextcloud/spreed/pull/4847)
- Fix CSP violation in Safari with worker-src from avatar blurring
  [#4899](https://github.com/nextcloud/spreed/pull/4899)
- Don't remove a chat when a self-joined user leaves
  [#4893](https://github.com/nextcloud/spreed/pull/4893)
- Use proc_open to run matterbridge for better compatibility
  [#4775](https://github.com/nextcloud/spreed/pull/4775)
- Make the bridge bot password more complex
  [#4909](https://github.com/nextcloud/spreed/pull/4909)

## 10.0.5 – 2021-01-08
### Fixed
- Fix CSP violation in Safari with worker-src from avatar blurring
  [#4900](https://github.com/nextcloud/spreed/pull/4900)
- Don't remove a chat when a self-joined user leaves
  [#4894](https://github.com/nextcloud/spreed/pull/4894)
- Make the bridge bot password more complex
  [#4910](https://github.com/nextcloud/spreed/pull/4910)

## 9.0.8 – 2021-01-08
### Fixed
- Don't remove a chat when a self-joined user leaves
  [#4903](https://github.com/nextcloud/spreed/pull/4903)

## 8.0.15 – 2021-01-08
### Fixed
- Don't remove a chat when a self-joined user leaves
  [#4904](https://github.com/nextcloud/spreed/pull/4904)

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

## 10.1.0 – 2020-12-18
### Added
- Implement multiple requirements to prepare for SIP dial-in
  [#4612](https://github.com/nextcloud/spreed/pull/4612)
### Fixed
- Fix potentially multiple guests joining in a password request conversation
  [#4801](https://github.com/nextcloud/spreed/pull/4801)
- Prevent issues with UTF8 multibyte chars in the changelog conversation
  [#4733](https://github.com/nextcloud/spreed/pull/4733)
- Fix Chromium performance hit in calls due to blur filter
  [#4781](https://github.com/nextcloud/spreed/pull/4781)
- Stop sending the nick through data channels after some time
  [#4726](https://github.com/nextcloud/spreed/pull/4726)
- Fix "Copy link" not clickable when waiting alone in a call
  [#4687](https://github.com/nextcloud/spreed/pull/4687)
- Fix some Matterbridge integrations
  [#4729](https://github.com/nextcloud/spreed/pull/4729)
  [#4799](https://github.com/nextcloud/spreed/pull/4799)
- Use proc_open to run system commands in bridge manager
  [#4775](https://github.com/nextcloud/spreed/pull/4775)
- Only show password request button when the share actually has Talk Verification enabled
  [#4794](https://github.com/nextcloud/spreed/pull/4794)

## 10.0.4 – 2020-12-18
### Fixed
- Fix potentially multiple guests joining in a password request conversation
  [#4798](https://github.com/nextcloud/spreed/pull/4798)
- Prevent issues with UTF8 multibyte chars in the changelog conversation
  [#4734](https://github.com/nextcloud/spreed/pull/4734)
- Fix Chromium performance hit in calls due to blur filter
  [#4780](https://github.com/nextcloud/spreed/pull/4780)
- Stop sending the nick through data channels after some time
  [#4649](https://github.com/nextcloud/spreed/pull/4649)
- Fix "Copy link" not clickable when waiting alone in a call
  [#4687](https://github.com/nextcloud/spreed/pull/4687)
- Fix some Matterbridge integrations
  [#4728](https://github.com/nextcloud/spreed/pull/4728)
  [#4800](https://github.com/nextcloud/spreed/pull/4800)
- Use proc_open to run system commands in bridge manager
  [#4774](https://github.com/nextcloud/spreed/pull/4774)
- Only show password request button when the share actually has Talk Verification enabled
  [#4795](https://github.com/nextcloud/spreed/pull/4795)

## 9.0.7 – 2020-12-18
### Fixed
- Fix potentially multiple guests joining in a password request conversation
  [#4797](https://github.com/nextcloud/spreed/pull/4797)

## 8.0.14 – 2020-12-18
### Fixed
- Fix potentially multiple guests joining in a password request conversation
  [#4796](https://github.com/nextcloud/spreed/pull/4796)

## 10.0.3 – 2020-11-18
### Fixed
- Fix conversation URL change detection
  [#4642](https://github.com/nextcloud/spreed/pull/4642)
- Fix missing call icon in participant list
  [#4637](https://github.com/nextcloud/spreed/pull/4637)

## 10.0.2 – 2020-11-17
### Fixed
- Reduce performance impact caused by the existence of the emoji picker
  [#4514](https://github.com/nextcloud/spreed/pull/4514)
- Reduce the load when mounting many shares
  [#4509](https://github.com/nextcloud/spreed/pull/4509)
- Fix handling of unavailable commands
  [#4577](https://github.com/nextcloud/spreed/pull/4577)
- Don't leave conversation on URL hash change e.g. from search
  [#4596](https://github.com/nextcloud/spreed/pull/4596)
- Correctly delete a conversation when the last moderator leaves
  [#4498](https://github.com/nextcloud/spreed/pull/4498)

## 9.0.6 – 2020-11-17
### Fixed
- Reduce the load when mounting many shares
  [#4510](https://github.com/nextcloud/spreed/pull/4510)
- Fix handling of unavailable commands
  [#4578](https://github.com/nextcloud/spreed/pull/4578)
- Correctly delete a conversation when the last moderator leaves
  [#4499](https://github.com/nextcloud/spreed/pull/4499)

## 8.0.13 – 2020-11-17
### Fixed
- Reduce the load when mounting many shares
  [#4511](https://github.com/nextcloud/spreed/pull/4511)
- Fix handling of unavailable commands
  [#4579](https://github.com/nextcloud/spreed/pull/4579)
- Correctly delete a conversation when the last moderator leaves
  [#4611](https://github.com/nextcloud/spreed/pull/4611)

## 10.0.1 – 2020-10-23
### Fixed
- Fix automated scrolling behaviour which sometimes jumped into the middle of the message list
  [#4417](https://github.com/nextcloud/spreed/pull/4417)
- Remove pulse animation from call button to reduce CPU load in chrome-based browsers
  [#4301](https://github.com/nextcloud/spreed/pull/4301)
- Only show the "Session conflict" dialog when in a call
  [#4442](https://github.com/nextcloud/spreed/pull/4442)
- Fix minimum length calculation of the call token
  [#4369](https://github.com/nextcloud/spreed/pull/4369)
- Fix duplicate session issue in files sidebar
  [#4426](https://github.com/nextcloud/spreed/pull/4426)
- Lobby date not shown in the moderator menu
  [#4322](https://github.com/nextcloud/spreed/pull/4322)
- Don't load the session information in rooms with more than 100 participants
  [#4278](https://github.com/nextcloud/spreed/pull/4278)
- Improve setting the initial status of audio/video when the high-performance backend is used
  [#4375](https://github.com/nextcloud/spreed/pull/4375)
- Fix syntax to check for matterbridge processes to work on more systems
  [#4415](https://github.com/nextcloud/spreed/pull/4415)
- Don't render an additional video when selecting the already promoted video
  [#4419](https://github.com/nextcloud/spreed/pull/4419)

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

## 8.0.12 – 2020-10-23
### Fixed
- Remove pulse animation from call button to reduce CPU load in chrome-based browsers
  [#4303](https://github.com/nextcloud/spreed/pull/4303)
- Fix minimum length calculation of the call token
  [#4371](https://github.com/nextcloud/spreed/pull/4371)
- Fix duplicate session issue in files sidebar
  [#4428](https://github.com/nextcloud/spreed/pull/4428)

## 10.0.0 – 2020-10-02
### Added
- Implement unified search for messages
  [#4017](https://github.com/nextcloud/spreed/pull/4017)
- Support for user status
  [#3993](https://github.com/nextcloud/spreed/pull/3993)
- Moderators can now mute all participants with one click in the moderator menu
  [#4052](https://github.com/nextcloud/spreed/pull/4052)
- Allow changing the camera and microphone during a call
  [#4023](https://github.com/nextcloud/spreed/pull/4023)
- Show upload progress and a preview when sharing a file into the chat
  [#3988](https://github.com/nextcloud/spreed/pull/3988)
- Add a dashboard widget with unread mentions and active calls
  [#3890](https://github.com/nextcloud/spreed/pull/3890)
- Add an emoji picker to the chat
  [#3994](https://github.com/nextcloud/spreed/pull/3994)
- Hosted high-performance backend trial option in the admin settings
  [#3620](https://github.com/nextcloud/spreed/pull/3620)
- Remember the selected camera and microphone for future visits
  [#4224](https://github.com/nextcloud/spreed/pull/4224)
- Keyboard navigation for conversation search
  [#3955](https://github.com/nextcloud/spreed/pull/3955)
- Show keyboard shortcuts in the settings
  [#4089](https://github.com/nextcloud/spreed/pull/4089)
- 🚧 TechPreview: Matterbridge integration
  [#4010](https://github.com/nextcloud/spreed/pull/4010)
- Compatibility with Nextcloud 20

### Changed
- Online users are now sorted above offline moderators in the participant list, because we think it's more important what you do than who you are
  [#4211](https://github.com/nextcloud/spreed/pull/4211)
- Allow to select your own video in the speaker view
  [#3814](https://github.com/nextcloud/spreed/pull/3814)

### Fixed
- "Talk to …" button in avatar only works on first use
  [#4194](https://github.com/nextcloud/spreed/pull/4194)
- Reduce the load of various requests
  [#4205](https://github.com/nextcloud/spreed/pull/4205)
  [#4204](https://github.com/nextcloud/spreed/pull/4204)
  [#4201](https://github.com/nextcloud/spreed/pull/4201)
  [#4152](https://github.com/nextcloud/spreed/pull/4152)
- Fix clientside memory leaks due to missing unsubscribe of events when destroying interface components
  [#4139](https://github.com/nextcloud/spreed/pull/4139)
  [#4140](https://github.com/nextcloud/spreed/pull/4140)
  [#4154](https://github.com/nextcloud/spreed/pull/4154)
  [#4155](https://github.com/nextcloud/spreed/pull/4155)
- Installation on Oracle fails
  [#4127](https://github.com/nextcloud/spreed/pull/4127)
- Try to be more safe again errors when trying to get the Talk folder for attachments
  [#4165](https://github.com/nextcloud/spreed/pull/4165)
- Remove old "hark" data channel
  [#4068](https://github.com/nextcloud/spreed/pull/4068)
- Show other participants video when they share their screen
  [#4082](https://github.com/nextcloud/spreed/pull/4082)
- Scroll to the original message when clicking on a quota
  [#4037](https://github.com/nextcloud/spreed/pull/4037)
- Fix transparency issue with the avatar menu in the participant list
  [#3958](https://github.com/nextcloud/spreed/pull/3958)
- Prevent infinite loop in datachannel open re-transmission
  [#3882](https://github.com/nextcloud/spreed/pull/3882)

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


## 5.0.4 – 2019-06-06
### Fixed
- Fix message list not reloaded after switching tabs in the sidebar
  [#1867](https://github.com/nextcloud/spreed/pull/1867)
- Fix multiple issues related to screensharing
  [#1762](https://github.com/nextcloud/spreed/pull/1762)
  [#1754](https://github.com/nextcloud/spreed/pull/1754)
  [#1746](https://github.com/nextcloud/spreed/pull/1746)

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
- Administrators can now define commands which can be used in the chat. See [commands.md](https://github.com/nextcloud/spreed/blob/master/docs/commands.md) for more information. You can install some sample commands via the console.
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

## 4.0.4 – 2019-04-11
### Fixed
- Enable "Plan B" for chrome/chromium for better MCU support
  [#1614](https://github.com/nextcloud/spreed/pull/1614)
- Delay signaling messages when the socket is not yet opened
  [#1552](https://github.com/nextcloud/spreed/pull/1552)

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

## 3.2.8 – 2019-01-30
### Fixed
- Fix mentions when adding multiple directly after each other
  [#1394](https://github.com/nextcloud/spreed/pull/1394)
- Load more messages after loading the first batch when entering a room
  [#1403](https://github.com/nextcloud/spreed/pull/1403)

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

## 4.0.2 – 2018-12-11
### Fixed
- Fix screensharing in Chrome 71+ and other Chrome based browsers
  [#1369](https://github.com/nextcloud/spreed/pull/1369)
- Fix broken chat when a file that was shared into a room is deleted
  [#1352](https://github.com/nextcloud/spreed/pull/1352)
- Send initial screensharing stream to participants that don't publish video
  [#1372](https://github.com/nextcloud/spreed/pull/1372)

## 3.2.7 – 2018-12-11
### Fixed
- Fix screensharing in Chrome 71+ and other Chrome based browsers
  [#1369](https://github.com/nextcloud/spreed/pull/1369)
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

## 3.2.6 – 2018-09-20
### Fixed
- Fix turn credential generation
  [#1203](https://github.com/nextcloud/spreed/pull/1203)
- Fix several inconsistencies with the internal api
  [#1202](https://github.com/nextcloud/spreed/pull/1202)
  [#1201](https://github.com/nextcloud/spreed/pull/1201)
  [#1200](https://github.com/nextcloud/spreed/pull/1200)

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

## 3.2.5 – 2018-07-23
### Fixed
- Fix handling of malicious usernames while autocompleting in chat

## 3.2.4 – 2018-07-12
### Added
- Allow external signaling servers to integrate a MCU
  [#398](https://github.com/nextcloud/spreed/pull/398)

### Fixed
- Support chat with a standalone signaling servers
  [#890](https://github.com/nextcloud/spreed/pull/890)
  [#887](https://github.com/nextcloud/spreed/pull/887)

## 3.2.3 – 2018-07-11
### Changed
- Only paste the content of HTML into the chat input without the actual HTML
  [#1018](https://github.com/nextcloud/spreed/pull/1018)

### Fixed
- Fixes for standalone signaling server
  [#910](https://github.com/nextcloud/spreed/pull/910)
- Name not shown for participants without audio and video
  [#982](https://github.com/nextcloud/spreed/pull/982)
- Correctly timeout users when they are chatting/calling and got disconnected
  [#935](https://github.com/nextcloud/spreed/pull/935)
- Multiple layout fixes

## 3.2.2 – 2018-06-06
### Added
- Add toggle to show and hide video from other participants
  [#937](https://github.com/nextcloud/spreed/pull/937)

### Changed
- Activities and Notifications text (Calls->Conversations)
  [#919](https://github.com/nextcloud/spreed/pull/919)

### Fixed
- Send call notifications to every room participant that is not in the call
  [#926](https://github.com/nextcloud/spreed/pull/926)
- Mark messages directly as read when waiting for new messages
  [#936](https://github.com/nextcloud/spreed/pull/936)
- Fix tab header icons not shown
  [#929](https://github.com/nextcloud/spreed/pull/929)
- Fix room and participants menu buttons
  [#934](https://github.com/nextcloud/spreed/pull/934)
  [#941](https://github.com/nextcloud/spreed/pull/941)
- Fix local audio and video not disabled when not available
  [#938](https://github.com/nextcloud/spreed/pull/938)
- Fix "Add participant" shown to normal participants
  [#939](https://github.com/nextcloud/spreed/pull/939)
- Fix adding the same participant several times in a row
  [#940](https://github.com/nextcloud/spreed/pull/940)


## 3.2.1 – 2018-05-11
### Added
- Standalone signaling server now supports the 3.2 changes
  [#864](https://github.com/nextcloud/spreed/pull/864)
  [#869](https://github.com/nextcloud/spreed/pull/869)

### Fixed
- Only join the room after media permission request was answered
  [#854](https://github.com/nextcloud/spreed/pull/854)
- Do not reload the participant everytime a guest sends a chat message
  [#866](https://github.com/nextcloud/spreed/pull/866)
- Make sure the web UI still works after you left the current conversation or call
  [#871](https://github.com/nextcloud/spreed/pull/871)
  [#872](https://github.com/nextcloud/spreed/pull/872)
  [#874](https://github.com/nextcloud/spreed/pull/874)
- Allow to scroll on long participant lists again
  [#896](https://github.com/nextcloud/spreed/pull/896)
- Do not throw an error when starting a call in a conversation without any chat message
  [#861](https://github.com/nextcloud/spreed/pull/861)
- Enable media controls when media is approved on a second request
  [#861](https://github.com/nextcloud/spreed/pull/861)
- Limit the unread message counter to 99+
  [#845](https://github.com/nextcloud/spreed/pull/845)


## 3.2.0 – 2018-05-03
### Added
- Shortcuts have been added when a call is active: (m)ute, (v)ideo, (f)ullscreen, (c)hat and (p)articipant list
  [#730](https://github.com/nextcloud/spreed/pull/730)
  [#750](https://github.com/nextcloud/spreed/pull/750)
- Allow users to chat in multiple tabs in multiple chats at the same time
  [#748](https://github.com/nextcloud/spreed/pull/748)
- Guest names are now handled better in chat and the participant list
  [#733](https://github.com/nextcloud/spreed/pull/733)
- Users which are participanting in a call now have a video icon in the participant list
  [#777](https://github.com/nextcloud/spreed/pull/777)
- Unread chat message count is now displayed in the room list
  [#806](https://github.com/nextcloud/spreed/pull/806)
  [#824](https://github.com/nextcloud/spreed/pull/824)

### Changed
- It is now possible to join a call without camera and/or microphone
  [#758](https://github.com/nextcloud/spreed/pull/758)
- Chat does now not require Media permissions anymore
  [#711](https://github.com/nextcloud/spreed/pull/711)
- Leaving a call will free up the Media permissions
  [#735](https://github.com/nextcloud/spreed/pull/735)
- Participants can now be `@mentioned` in the chat by starting to type `@` followed by the name of the user
  [#805](https://github.com/nextcloud/spreed/pull/805)
  [#812](https://github.com/nextcloud/spreed/pull/812)
  [#813](https://github.com/nextcloud/spreed/pull/813)

### Fixed
- Correctly catch the input on the chat in firefox (instead of writing to the placeholder)
  [#737](https://github.com/nextcloud/spreed/pull/737)
- Keep scrolling position when switching from chat to call or back
  [#838](https://github.com/nextcloud/spreed/pull/838)
- Delete rooms when the last logged in user leaves
  [#727](https://github.com/nextcloud/spreed/pull/727)
- Correctly update chat UI when leaving current room
  [#743](https://github.com/nextcloud/spreed/pull/743)
- Various layout fixes with videos and screensharing
  [#702](https://github.com/nextcloud/spreed/pull/702)
  [#712](https://github.com/nextcloud/spreed/pull/712)
  [#713](https://github.com/nextcloud/spreed/pull/713)
- Fix issues with users that have a numerical name or id
  [#694](https://github.com/nextcloud/spreed/pull/694)
- Fix contacts menu entry when no user was found
  [#686](https://github.com/nextcloud/spreed/pull/686)


## 3.1.0 – 2018-02-14
### Added
- Finish support for go-based external signaling backend
  [#492](https://github.com/nextcloud/spreed/pull/492)

### Changed
- Make capabilities and signaling settings available for guests
  [#644](https://github.com/nextcloud/spreed/pull/644) [#654](https://github.com/nextcloud/spreed/pull/654)
- Use the search name as room name when creating a new room
  [#592](https://github.com/nextcloud/spreed/pull/592)
- Make links in chat clickable
  [#579](https://github.com/nextcloud/spreed/pull/579)

### Fixed
- Fix screensharing layout for guests
  [#611](https://github.com/nextcloud/spreed/pull/611)
- Correctly remember guest names when a guest is rejoining an existing call
  [#593](https://github.com/nextcloud/spreed/pull/593)
- Better date time divider in chat view
  [#591](https://github.com/nextcloud/spreed/pull/591)

## 3.0.1 – 2018-01-12
### Added
- Added capabilities so the mobile files apps can link to the mobile talk apps
  [#585](https://github.com/nextcloud/spreed/pull/585)

### Fixed
- Fixed issues when updating with Postgres and versions before 2.0.0
  [#584](https://github.com/nextcloud/spreed/pull/584)

## 3.0.0 – 2018-01-10
### Added
 - Added simple text chat
  [#429](https://github.com/nextcloud/spreed/pull/429)
 - Added activities for calls: "You had a call with ABC (Duration: 15:20)"
  [#438](https://github.com/nextcloud/spreed/pull/438)
 - Introduced different participant permission levels: owner, moderator and user
  [#353](https://github.com/nextcloud/spreed/pull/353)
 - Added support for room passwords on public shared rooms
  [#402](https://github.com/nextcloud/spreed/pull/402)
 - Added option to run an external signaling backend
  [#366](https://github.com/nextcloud/spreed/pull/366)

### Changed
 - Rename the app to "Talk" since it now contains chat, voice and video calls
  [#444](https://github.com/nextcloud/spreed/pull/444)
 - Moved admin settings to separate category and allowed to configure multiple STUN and TURN servers
  [#427](https://github.com/nextcloud/spreed/pull/427)
 - Moved signaling from EventSource to long polling for compatibility with HTTP2
  [#363](https://github.com/nextcloud/spreed/pull/363)
 - Moved room API to OCS so apps and 3rd party tools can use it
  [#342](https://github.com/nextcloud/spreed/pull/342)

### Fixed
 - Fixed compatibility with Postgres
  [#537](https://github.com/nextcloud/spreed/pull/537)
 - Fixed compatibility with Oracle
  [#371](https://github.com/nextcloud/spreed/pull/371)
 - Compatibility with Nextcloud 13


## 2.0.2 – 2017-11-28
### Fixed
 - Re-send data channels messages when they could not be sent.
  [#335](https://github.com/nextcloud/spreed/pull/335)

## 2.0.1 – 2017-05-22
### Added
 - Display the connection state in the interface and try to reconnect in case of an issue
  [#317](https://github.com/nextcloud/spreed/pull/317)

### Changed
 - Is now more tolerant towards server ping issues
  [#320](https://github.com/nextcloud/spreed/pull/320)

### Fixed
 - Fix several issues that caused missing avatars
  [#312](https://github.com/nextcloud/spreed/pull/312)
  [#313](https://github.com/nextcloud/spreed/pull/313)
 - Fix visibility of guest names on light themes
  [#321](https://github.com/nextcloud/spreed/pull/321)

## 2.0.0 – 2017-05-02
### Added
 - Screensharing is now supported in Chrome 42+ (requires an extension) and Firefox 52+
  [#227](https://github.com/nextcloud/spreed/pull/227)
 - Integration in the new Nextcloud 12 contacts menu
  [#300](https://github.com/nextcloud/spreed/pull/300)

### Changed
 - URLs are now short random strings instead of iteratible numbers
  [#258](https://github.com/nextcloud/spreed/pull/258)
 - Logged-in users can now join public rooms
  [#296](https://github.com/nextcloud/spreed/pull/296)

### Fixed
 - Fix error when response of TURN and STUN server arrive in the wrong order
  [#295](https://github.com/nextcloud/spreed/pull/295)


## 1.2.0 – 2017-01-18
### Added
 - Translations for multiple languages now available
  [#177](https://github.com/nextcloud/spreed/pull/177)
 - Open call-search when user has no calls
  [#111](https://github.com/nextcloud/spreed/pull/111)
 - Disable video by default, when more then 5 people are in a room

### Changed
 - TURN settings can only be changed by admins now
  [#185](https://github.com/nextcloud/spreed/pull/185)
 - App can not be restricted to groups anymore to prevent issues with public rooms
  [#201](https://github.com/nextcloud/spreed/pull/201)

### Fixed
 - Allow to connect via Firefox without a camera
  [#160](https://github.com/nextcloud/spreed/pull/160)
 - Leaving the current room does not refresh the full page anymore
  [#163](https://github.com/nextcloud/spreed/pull/163)
 - "Undefined index" log entry when visiting admin page
  [#151](https://github.com/nextcloud/spreed/pull/151)
  [#57](https://github.com/nextcloud/spreed/pull/57)
