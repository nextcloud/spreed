<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 16.0.11 – 2024-02-29
### Changed
- Update translations

### Fixed
- fix(webrtc): Ignore label of data channel when processing received messages for Janus 1.x compatibility
  [#11669](https://github.com/nextcloud/spreed/issues/11669)
- fix(notifications): Fix notification action label length with utf8 languages
  [#11619](https://github.com/nextcloud/spreed/issues/11619)
- fix(chat): Fix forwarding messages from conversations in the right sidebar
  [#11611](https://github.com/nextcloud/spreed/issues/11611)

## 16.0.10 – 2024-01-25
### Fixed
- fix(attachments): Don't allow selecting shared folders as attachment folder
  [#11431](https://github.com/nextcloud/spreed/issues/11431)

## 16.0.9 – 2023-12-19
### Fixed
- fix(occ): Fix verification of STUN server details
  [#11195](https://github.com/nextcloud/spreed/issues/11195)
- fix(hosted-hpb): Correctly handle API response codes of hosted High-performance backend when the account expired
  [#11045](https://github.com/nextcloud/spreed/issues/11045)

## 16.0.8 – 2023-11-23
### Fixed
- fix(settings): Remove non-working notification settings for guests
  [#10976](https://github.com/nextcloud/spreed/issues/10976)
- fix(settings): Fix option to request an HPB trial
  [#10967](https://github.com/nextcloud/spreed/issues/10967)
- fix(settings): Fail recording server test when an HPB was given as recording backend
  [#10950](https://github.com/nextcloud/spreed/issues/10950)
- fix(chat): Hide delete option for guests
  [#10807](https://github.com/nextcloud/spreed/issues/10807)

## 16.0.7 – 2023-10-27
### Changed
- Update dependencies

### Fixed
- fix(call): Fix "silent" parameter not sent again when reconnecting
  [#10777](https://github.com/nextcloud/spreed/issues/10777)
- fix(chat): Fix message grouping for all locales
  [#10696](https://github.com/nextcloud/spreed/issues/10696)
- fix(RightSidebar) update active tab on mount and conversation change
  [#10564](https://github.com/nextcloud/spreed/issues/10564)
- fix(sip): Fix saving the secret of the SIP bridge in the admin UI
  [#10719](https://github.com/nextcloud/spreed/issues/10719)

## 16.0.6 – 2023-09-21
### Changed
- Update dependencies

### Fixed
- fix(chat): Fix responding with "X-Chat-Last-Common-Read" when requested by the client
  [#10340](https://github.com/nextcloud/spreed/issues/10340)
- fix(call): Add an option to disable background blur in call
  [#10473](https://github.com/nextcloud/spreed/issues/10473)
- fix(desktop): fix disabling avatar menu for desktop
  [#10183](https://github.com/nextcloud/spreed/issues/10183)
- fix(page): Decouple the index controller from the executing method
  [#10547](https://github.com/nextcloud/spreed/issues/10547)
- Fix using signaling settings while being refetched
  [#10259](https://github.com/nextcloud/spreed/issues/10259)
- fix(chat): clean conversation history for participants in call
  [#10303](https://github.com/nextcloud/spreed/issues/10303)

## 16.0.5 – 2023-07-20
### Changed
- Close sidebar on mobile resolution after changing the route
  [#9764](https://github.com/nextcloud/spreed/issues/9764)

### Fixed
- Make chat stay scrolling when the last message receives its first reaction
  [#9957](https://github.com/nextcloud/spreed/issues/9957)
- Improve call view video size calculation
  [#9836](https://github.com/nextcloud/spreed/issues/9836)
- Update group displayname when a group is renamed
  [#9840](https://github.com/nextcloud/spreed/issues/9840)
- Don't make the conversation list scroll when the selected conversation is already visible
  [#9785](https://github.com/nextcloud/spreed/issues/9785)
- Make conversation name and description selectable
  [#9781](https://github.com/nextcloud/spreed/issues/9781)

## 16.0.4 – 2023-05-25
### Added
- Allow to mark conversations unread in the sidebar
  [#9366](https://github.com/nextcloud/spreed/issues/9366)

### Changed
- Make self-joined users persistent members when assigning permissions
  [#9434](https://github.com/nextcloud/spreed/issues/9434)
- Update dependencies

### Fixed
- Special characters are HTML encoded when selecting an Emoji from the picker
  [#9545](https://github.com/nextcloud/spreed/issues/9545)
- Fix missing "New message" form on share and files app integration
  [#9532](https://github.com/nextcloud/spreed/issues/9532)
- Fix diverging user status between top bar and conversation list
  [#9419](https://github.com/nextcloud/spreed/issues/9419)
- Fix call summary when a user has a full numeric user ID
  [#9502](https://github.com/nextcloud/spreed/issues/9502)
- Fix mentions of groups with spaces in the ID and guests
  [#9420](https://github.com/nextcloud/spreed/issues/9420)
- Prevent sending empty chat messages
  [#9515](https://github.com/nextcloud/spreed/issues/9515)

## 16.0.3 – 2023-04-20
### Added
- feat: Add missing "New in Talk 16" section
  [#9205](https://github.com/nextcloud/spreed/pull/9205)

### Changed
- Update several dependencies

### Fixed
- fix(chat): Fix missing popups and modals in fullscreen mode
  [#9323](https://github.com/nextcloud/spreed/pull/9323)
- fix(chat): Fix squeezed mention suggestions after library update
  [#9302](https://github.com/nextcloud/spreed/pull/9302)
- fix(conversation): Change redirect when the conversation is left or deleted
  [#9242](https://github.com/nextcloud/spreed/pull/9242)
  [#9058](https://github.com/nextcloud/spreed/pull/9058)
- fix(sidebar): Improve handling of the sidebar
  [#9212](https://github.com/nextcloud/spreed/pull/9212)
- fix(settings): Fix admin settings page when upload limit is infinite
  [#9247](https://github.com/nextcloud/spreed/pull/9247)

## 16.0.2 – 2023-03-28
### Added
- feat: Allow Chromium-based browser Brave
  [#9166](https://github.com/nextcloud/spreed/pull/9166)
- feat(smart-picker): Add conversation search to the smart-picker integration
  [#9105](https://github.com/nextcloud/spreed/pull/9105)

### Changed
- Update several dependencies

### Fixed
- fix(chat): Fix lost message text when autocomplete triggers after pasting text inside the @nextcloud/vue library
  [#9191](https://github.com/nextcloud/spreed/pull/9191)
- fix(chat): Fix visual regression with links inside the @nextcloud/vue library
  [#9191](https://github.com/nextcloud/spreed/pull/9191)
- fix(reactions): Don't update last message when someone reacted
  [#9186](https://github.com/nextcloud/spreed/pull/9186)
- fix(recordings): Set a dedicated user-agent for the recording backend
  [#9184](https://github.com/nextcloud/spreed/pull/9184)
  [#9194](https://github.com/nextcloud/spreed/pull/9194)
- fix(desktop): Hide some features inside the desktop client
  [#9171](https://github.com/nextcloud/spreed/pull/9171)

## 16.0.1 – 2023-03-24
### Added
- feat(chat): Allow to receive messages without marking notifications as unread
  [#9103](https://github.com/nextcloud/spreed/pull/9103)

### Changed
- Update several dependencies

### Fixed
- fix(chat): Fix multiple issues with emoji and mention autocompletion
- fix(chat): Fix pasting HTML and XML content into the chat input
  [#9104](https://github.com/nextcloud/spreed/pull/9104)
- fix(calls): Fix RemoteVideoBlocker still active after removing its associated model
  [#9131](https://github.com/nextcloud/spreed/pull/9131)
- fix(breakout-rooms): Fix breakout-room option shown for public conversations
  [#9135](https://github.com/nextcloud/spreed/pull/9135)
- fix(UI): Fix conditions when a reload of the UI is necessary
  [#9123](https://github.com/nextcloud/spreed/pull/9123)
- fix(recordings): Fix default quality of call recordings
  [#9121](https://github.com/nextcloud/spreed/pull/9121)
- fix(chat): Don't focus the chat input on mobile devices
  [#8898](https://github.com/nextcloud/spreed/pull/8898)

## 16.0.0 – 2023-03-21
### Added for users
- Breakout rooms can be used to split a group call into temporary working groups (Requires the High-performance backend)
  [#8337](https://github.com/nextcloud/spreed/pull/8337)
- Calls can now be recorded (Requires the High-performance backend)
  [#8324](https://github.com/nextcloud/spreed/pull/8324)
- The top bar now shows useful information like participant count, call duration and title while in a call.
  [#8341](https://github.com/nextcloud/spreed/pull/8341)
- Chat input now allows to autocomplete emojis
  [#4333](https://github.com/nextcloud/spreed/pull/4333)

### Added for administrators
- Administrators can now define the default conversation permissions via the `default_permissions` app config
  [#8457](https://github.com/nextcloud/spreed/pull/8457)
- Administrators can now define the default name of the Talk/ attachments folder via the `default_attachment_folder` app config
  [#8465](https://github.com/nextcloud/spreed/pull/8465)
- OCC command to transfer-ownership of conversations was added allowing to hand over conversations during off-boarding
  [#8479](https://github.com/nextcloud/spreed/pull/8479)
- All available app configurations have been documented in the [settings documentation](https://nextcloud-talk.readthedocs.io/en/latest/settings/#app-configuration)

### Added for developers
- Chat API now allows to get the context (older and newer messages) for a message
  [#8717](https://github.com/nextcloud/spreed/pull/8717)
- Conversation list is now being instantly updated with information from notifications
  [#8723](https://github.com/nextcloud/spreed/pull/8723)
- Conversations API now supports a "modified since" parameter to only get changed conversations
  [#8726](https://github.com/nextcloud/spreed/pull/8726)
- Chats are opened now without a page reload when interacting with notifications
  [#8713](https://github.com/nextcloud/spreed/pull/8713)
- Introduced a new conversation type to indicate that a conversation was a one-to-one conversation
  [#8600](https://github.com/nextcloud/spreed/pull/8600)

### Changed
- Version 1.1.0 of the signaling server of the High-performance backend is now required
- Update several dependencies


## 16.0.0-rc.4 – 2023-03-20
### Fixed
- Fix flickering when dragging a file over the window with Safari on MacOS
  [#9076](https://github.com/nextcloud/spreed/pull/9076)
- Fix flickering with message buttons bar of the last message
  [#9043](https://github.com/nextcloud/spreed/pull/9043)
- Fix conditions for showing "Reply" and "Reply privately"
  [#9052](https://github.com/nextcloud/spreed/pull/9052)

## 16.0.0-rc.3 – 2023-03-09
### Fixed
- Correctly handle `<` and `>` in chat messages
  [#8977](https://github.com/nextcloud/spreed/pull/8977)
- Improve the position of the message button bar for single line and very long messages
  [#9009](https://github.com/nextcloud/spreed/pull/9009)
- Remove space on call-time button
  [#8979](https://github.com/nextcloud/spreed/pull/8979)
- Fix displaying of restricted and full permissions selection when manually configuring them
  [#8982](https://github.com/nextcloud/spreed/pull/8982)
- Fix dashboard widget API returning breakout rooms
  [#8976](https://github.com/nextcloud/spreed/pull/8976)
- Improve breakout room API documentation
  [#8994](https://github.com/nextcloud/spreed/pull/8994)
- Also remove polls when purging the chat history
  [#8991](https://github.com/nextcloud/spreed/pull/8991)
- Fix mention and emoji autocomplete when broadcasting to breakout rooms
  [#8999](https://github.com/nextcloud/spreed/pull/8999)
- Notify the moderator when uploading a recording failed
  [#9000](https://github.com/nextcloud/spreed/pull/9000)
- Add a warning in the admin settings when the file upload limits are lower than 512 MB
  [#9002](https://github.com/nextcloud/spreed/pull/9002)
- Fix unread message count improving when receiving own messages
  [#9011](https://github.com/nextcloud/spreed/pull/9011)
- Fix duplicate attachment upload with Safari and Chrome on MacOS
  [#9012](https://github.com/nextcloud/spreed/pull/9012)

## 16.0.0-rc.2 – 2023-03-06
### Changed
- Update several dependencies
- Migrate RichText component usage to NcRichText
  [#8959](https://github.com/nextcloud/spreed/pull/8959)

### Fixed
- Design review changes for breakout rooms handling
  [#8962](https://github.com/nextcloud/spreed/pull/8962)
- Add documentation for OCC commands
  [#8907](https://github.com/nextcloud/spreed/pull/8907)

## 16.0.0-rc.1 – 2023-03-02
### Changed
- Update several dependencies

### Fixed
- Design review changes for breakout rooms handling
  [#8905](https://github.com/nextcloud/spreed/pull/8905)
  [#8910](https://github.com/nextcloud/spreed/pull/8910)
  [#8919](https://github.com/nextcloud/spreed/pull/8919)
  [#8920](https://github.com/nextcloud/spreed/pull/8920)
  [#8921](https://github.com/nextcloud/spreed/pull/8921)
  [#8922](https://github.com/nextcloud/spreed/pull/8922)
- Always expose the breakout room names when being a member of the parent
  [#8925](https://github.com/nextcloud/spreed/pull/8925)
- Hide breakout rooms from the dashboard widget
  [#8918](https://github.com/nextcloud/spreed/pull/8918)
- Fix chat scrolling to the end and the quick access button for it
  [#8895](https://github.com/nextcloud/spreed/pull/8895)
- Breakout rooms can not be configured in full screen mode
  [#8897](https://github.com/nextcloud/spreed/pull/8897)
- Button to reopen the chat sidebar while being in a call can disappear
  [#8923](https://github.com/nextcloud/spreed/pull/8923)
- Error when reacting to a message when the author left the conversation
  [#8883](https://github.com/nextcloud/spreed/pull/8883)
- File upload modal is positioned outside the chat
  [#8906](https://github.com/nextcloud/spreed/pull/8906)

## 16.0.0-beta.2 – 2023-02-27
### Changed
- Update several dependencies

### Fixed
- Don't show breakout room options in one-to-one and public conversations
  [#8875](https://github.com/nextcloud/spreed/pull/8875)
- Don't show recording options when no recording servers are configured
  [#8874](https://github.com/nextcloud/spreed/pull/8874)
- Focus conversation name field when creating conversation
  [#8873](https://github.com/nextcloud/spreed/pull/8873)
- Allow to abort emoji-autocomplete with ESC
  [#8870](https://github.com/nextcloud/spreed/pull/8870)
- Focus chat input when replying to a message
  [#8864](https://github.com/nextcloud/spreed/pull/8864)
- Fix message type of attachments uploaded via mobile apps
  [#8861](https://github.com/nextcloud/spreed/pull/8861)
- Hide the bottom video stripe in recordings
  [#8844](https://github.com/nextcloud/spreed/pull/8844)
- Don't allow to change certain settings directly inside breakout rooms
  [#8841](https://github.com/nextcloud/spreed/pull/8841)
- Fix detection of the recording state
  [#8840](https://github.com/nextcloud/spreed/pull/8840)
- Improve notification subject and message for recording uploads
  [#8837](https://github.com/nextcloud/spreed/pull/8837)

## 16.0.0-beta.1 – 2023-02-23
### Added for users
- Breakout rooms can be used to split a group call into temporary working groups (Requires the High-performance backend)
  [#8337](https://github.com/nextcloud/spreed/pull/8337)
- Calls can now be recorded (Requires the High-performance backend)
  [#8324](https://github.com/nextcloud/spreed/pull/8324)
- The top bar now shows useful information like participant count, call duration and title while in a call.
  [#8341](https://github.com/nextcloud/spreed/pull/8341)
- Chat input now allows to autocomplete emojis
  [#4333](https://github.com/nextcloud/spreed/pull/4333)

### Added for administrators
- Administrators can now define the default conversation permissions via the `default_permissions` app config
  [#8457](https://github.com/nextcloud/spreed/pull/8457)
- Administrators can now define the default name of the Talk/ attachments folder via the `default_attachment_folder` app config
  [#8465](https://github.com/nextcloud/spreed/pull/8465)
- OCC command to transfer-ownership of conversations was added allowing to hand over conversations during off-boarding
  [#8479](https://github.com/nextcloud/spreed/pull/8479)
- All available app configurations have been documented in the [settings documentation](https://nextcloud-talk.readthedocs.io/en/latest/settings/#app-configuration)

### Added for developers
- Chat API now allows to get the context (older and newer messages) for a message
  [#8717](https://github.com/nextcloud/spreed/pull/8717)
- Conversation list is now being instantly updated with information from notifications
  [#8723](https://github.com/nextcloud/spreed/pull/8723)
- Conversations API now supports a "modified since" parameter to only get changed conversations
  [#8726](https://github.com/nextcloud/spreed/pull/8726)
- Chats are opened now without a page reload when interacting with notifications
  [#8713](https://github.com/nextcloud/spreed/pull/8713)
- Introduced a new conversation type to indicate that a conversation was a one-to-one conversation
  [#8600](https://github.com/nextcloud/spreed/pull/8600)

### Changed
- Version 1.1.0 of the signaling server of the High-performance backend is now required
- Update several dependencies

