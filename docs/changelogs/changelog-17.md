<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 17.1.10 – 2024-06-27
### Fixed
- fix(bots): Fix bots with self-signed certificates
  [#12469](https://github.com/nextcloud/spreed/pull/12469)
- fix(shareIntegration): Fix handle to close and open the right sidebar on publish share links
  [#12495](https://github.com/nextcloud/spreed/pull/12495)

## 17.1.9 – 2024-05-23
### Fixed
- fix(polls): Remove actor info from system message
  [#12342](https://github.com/nextcloud/spreed/pull/12342)
- fix(recording): Stop broken recording backend
  [#12401](https://github.com/nextcloud/spreed/pull/12401)

## 17.1.8 – 2024-04-12
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(conversation): Fix error when adding participants while creating a conversation
  [#12059](https://github.com/nextcloud/spreed/issues/12059)
- fix(conversation): Fix missing icon in conversation settings for file conversations
  [#12052](https://github.com/nextcloud/spreed/issues/12052)

## 17.1.7 – 2024-04-04
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(conversation): skip unread marker increasing from notification
  [#11735](https://github.com/nextcloud/spreed/issues/11735)
- fix(modal): mount nested modals inside global modals
  [#11891](https://github.com/nextcloud/spreed/issues/11891)

## 17.1.6 – 2024-02-29
### Changed
- Update translations

### Fixed
- fix(webrtc): Ignore label of data channel when processing received messages for Janus 1.x compatibility
  [#11668](https://github.com/nextcloud/spreed/issues/11668)
- fix(notifications): Fix notification action label length with utf8 languages
  [#11620](https://github.com/nextcloud/spreed/issues/11620)
- fix(chat): Fix forwarding messages from conversations in the right sidebar
  [#11609](https://github.com/nextcloud/spreed/issues/11609)

## 17.1.5 – 2024-01-25
### Fixed
- fix(attachments): Don't allow selecting shared folders as attachment folder
  [#11430](https://github.com/nextcloud/spreed/issues/11430)

## 17.1.4 – 2023-12-19
### Fixed
- fix(UI): Allow joining a call while editing a document
  [#11260](https://github.com/nextcloud/spreed/issues/11260)
- fix(chat): Fix grouping of chat messages when they include the readmarker
  [#11068](https://github.com/nextcloud/spreed/issues/11068)
- fix(call): Fix lost audio tracks in Safari after being muted a longer time
  [#11145](https://github.com/nextcloud/spreed/issues/11145)
- fix(occ): Fix verification of STUN server details
  [#11194](https://github.com/nextcloud/spreed/issues/11194)
- fix(hosted-hpb): Correctly handle API response codes of hosted High-performance backend when the account expired
  [#11045](https://github.com/nextcloud/spreed/issues/11045)

## 17.1.3 – 2023-11-23
### Fixed
- fix(settings): Remove non-working notification settings for guests
  [#10974](https://github.com/nextcloud/spreed/issues/10974)
- fix(chat): Fix uploading files after some time of being online
  [#10891](https://github.com/nextcloud/spreed/issues/10891)
- fix(participants): Update participants list more regularly
  [#10843](https://github.com/nextcloud/spreed/issues/10843)
- fix(settings): Fix option to request an HPB trial
  [#10965](https://github.com/nextcloud/spreed/issues/10965)
- fix(settings): Fail recording server test when an HPB was given as recording backend
  [#10948](https://github.com/nextcloud/spreed/issues/10948)
- fix(chat): Hide delete option for guests
  [#10806](https://github.com/nextcloud/spreed/issues/10806)
- fix(chat): Fix sorting of system messages
  [#10964](https://github.com/nextcloud/spreed/issues/10964)
- fix(chat): Fix not breaking the JSON response when removing the last reaction of a message
  [#10949](https://github.com/nextcloud/spreed/issues/10949)
- fix(call): Log error message when starting a screenshot fails
  [#10827](https://github.com/nextcloud/spreed/issues/10827)

## 17.1.2 – 2023-10-27
### Changed
- Update dependencies

### Fixed
- fix(chat): Allow joining a conversation via search when a filter is active
  [#10781](https://github.com/nextcloud/spreed/issues/10781)
- fix(chat): Fix re-rendering of conversation data when scrolling (hover, user status, more)
  [#10779](https://github.com/nextcloud/spreed/issues/10779)
- fix(chat): Clear deleted messages from replies
  [#10713](https://github.com/nextcloud/spreed/issues/10713)
- fix(chat): Fix mentions when forwarding messages
  [#10673](https://github.com/nextcloud/spreed/issues/10673)
- fix(call): Increase the avatar size in calls when the video is disabled
  [#10628](https://github.com/nextcloud/spreed/issues/10628)
- fix(call): Fix "silent" parameter not sent again when reconnecting
  [#10776](https://github.com/nextcloud/spreed/issues/10776)
- fix(chat): Fix message grouping for all locales
  [#10695](https://github.com/nextcloud/spreed/issues/10695)
- fix(RightSidebar) update active tab on mount and conversation change
  [#10564](https://github.com/nextcloud/spreed/issues/10564)
- fix(sip): Fix saving the secret of the SIP bridge in the admin UI
  [#10718](https://github.com/nextcloud/spreed/issues/10718)

## 17.1.1 – 2023-09-21
### Added
- feat(chat): Add copy function to code blocks
  [#10533](https://github.com/nextcloud/spreed/issues/10533)

### Changed
- Update dependencies

### Fixed
- fix(attachments): Allow to navigate between attachments in the viewer
  [#10549](https://github.com/nextcloud/spreed/issues/10549)
- fix(bots): Fix notifications of bot messages and reactions
  [#10530](https://github.com/nextcloud/spreed/issues/10530)
- fix(conversations): Keep the current conversation in filtered list
  [#10527](https://github.com/nextcloud/spreed/issues/10527)
- fix(page): Decouple the index controller from the executing method
  [#10546](https://github.com/nextcloud/spreed/issues/10546)
- fix(API): Reuse participant objects already created to reduce number of database queries
  [#10536](https://github.com/nextcloud/spreed/issues/10536)

## 17.1.0 – 2023-09-16
### Added
- Add support for bots via webhooks
  [#10139](https://github.com/nextcloud/spreed/issues/10139)
  [#10151](https://github.com/nextcloud/spreed/issues/10151)
- Add Markdown support for chat messages
  [#10089](https://github.com/nextcloud/spreed/issues/10089)
  [#10090](https://github.com/nextcloud/spreed/issues/10090)
- Allow to filter the conversation list for unread mentions and messages
  [#10093](https://github.com/nextcloud/spreed/issues/10093)
- Provide an overview list of open conversations
  [#10095](https://github.com/nextcloud/spreed/issues/10095)
- Set a reminder to get notified about a chat messages at a later time
  [#10104](https://github.com/nextcloud/spreed/issues/10104)
  [#10152](https://github.com/nextcloud/spreed/issues/10152)
  [#10155](https://github.com/nextcloud/spreed/issues/10155)
- Show a hint when the call is running since one hour
  [#10101](https://github.com/nextcloud/spreed/issues/10101)
- Show the talking time of participants in the right sidebar
  [#10145](https://github.com/nextcloud/spreed/issues/10145)

### Changed
- System messages of the same actions are now grouped
  [#10143](https://github.com/nextcloud/spreed/issues/10143)
- Use virtual scrolling for the conversation list to improve the performance
  [#10297](https://github.com/nextcloud/spreed/issues/10297)
- Cache the conversation list in the browser storage for better loading experience
  [#10273](https://github.com/nextcloud/spreed/issues/10273)
- Update dependencies

## 17.1.0-rc.4 – 2023-08-31
### Changed
- chore(packaging): Ship dependencies lock files
  [#10426](https://github.com/nextcloud/spreed/issues/10426)
- Update dependencies

### Fixed
- fix(bots): Fix several problems with bots
  [#10425](https://github.com/nextcloud/spreed/issues/10425)
- feat(conversations): Persist the filter status after reload
  [#10407](https://github.com/nextcloud/spreed/issues/10407)
- fix(chat): Adjust parsing of NcRichContenteditable output before sending
  [#10440](https://github.com/nextcloud/spreed/issues/10440)
- fix(conversations): Fix arrow-key navigation in left sidebar
  [#10418](https://github.com/nextcloud/spreed/issues/10418)
- fix(deck): Show conversation name and highlight link in deck integration
  [#10394](https://github.com/nextcloud/spreed/issues/10394)
- fix(chat): Fix combined system message text with duplicated messages from yourself
  [#10439](https://github.com/nextcloud/spreed/issues/10439)

## 17.1.0-rc.3 – 2023-08-25
### Added
- feat(capability): Add a capability for messages being markdown
  [#10367](https://github.com/nextcloud/spreed/issues/10367)
- feat(bots)!: Ensure bot uniqueness and allow removing by URL
  [#10371](https://github.com/nextcloud/spreed/issues/10371)

### Changed
- Update dependencies

### Fixed
- fix(LeftSidebar): wrong user status after scrolling
  [#10369](https://github.com/nextcloud/spreed/issues/10369)
- fix(changelog): Prevent duplicated changelog message by parallel requests
  [#10366](https://github.com/nextcloud/spreed/issues/10366)
- fix(RoomSelector): Align text vertically for open conversation list
  [#10363](https://github.com/nextcloud/spreed/issues/10363)
- fix(chat): Fix primary color selection on quotes
  [#10363](https://github.com/nextcloud/spreed/issues/10363)
- fix(LeftSidebar): adjust conversation padding and size
  [#10359](https://github.com/nextcloud/spreed/issues/10359)

## 17.1.0-rc.2 – 2023-08-24
### Added
- Avatars of open conversations are now shown without being a participant
  [#10229](https://github.com/nextcloud/spreed/issues/10229)
- Added an option to only remove a user from private conversations
  [#10310](https://github.com/nextcloud/spreed/issues/10310)
- Added an option to copy the original message content
  [#10335](https://github.com/nextcloud/spreed/issues/10335)

### Changed
- Use virtual scrolling for the conversation list to improve the performance
  [#10297](https://github.com/nextcloud/spreed/issues/10297)
- Cache the conversation list in the browser storage for better loading experience
  [#10273](https://github.com/nextcloud/spreed/issues/10273)
- Update dependencies

### Fixed
- Allow replying to messages of bots
  [#10219](https://github.com/nextcloud/spreed/issues/10219)
- Fix sending system messages to bots
  [#10271](https://github.com/nextcloud/spreed/issues/10271)
- Fix style of Markdown code blocks, headlines and quotes
  [#10221](https://github.com/nextcloud/spreed/issues/10221)
  [#10238](https://github.com/nextcloud/spreed/issues/10238)
  [#10255](https://github.com/nextcloud/spreed/issues/10255)
- Fix recording option shown for non moderators
  [#10246](https://github.com/nextcloud/spreed/issues/10246)
- Apply selected call background only once confirmed
  [#10267](https://github.com/nextcloud/spreed/issues/10267)
- Clear chat history for other participants that are live when the moderator performs the action
  [#10302](https://github.com/nextcloud/spreed/issues/10302)
- Add missing capability that indicates bots are supported
  [#10314](https://github.com/nextcloud/spreed/issues/10314)
- Don't add parent messages of quotes to the message list which could create gaps in the history
  [#10318](https://github.com/nextcloud/spreed/issues/10318)
- Fix missing `X-Chat-Last-Common-Read` header on chat requests
  [#10337](https://github.com/nextcloud/spreed/issues/10337)

## 17.1.0-rc.1 – 2023-08-11
### Added
- Add support for bots via webhooks
  [#10139](https://github.com/nextcloud/spreed/issues/10139)
  [#10151](https://github.com/nextcloud/spreed/issues/10151)
- Add Markdown support for chat messages
  [#10089](https://github.com/nextcloud/spreed/issues/10089)
  [#10090](https://github.com/nextcloud/spreed/issues/10090)
- Allow to filter the conversation list for unread mentions and messages
  [#10093](https://github.com/nextcloud/spreed/issues/10093)
- Provide an overview list of open conversations
  [#10095](https://github.com/nextcloud/spreed/issues/10095)
- Set a reminder to get notified about a chat messages at a later time
  [#10104](https://github.com/nextcloud/spreed/issues/10104)
  [#10152](https://github.com/nextcloud/spreed/issues/10152)
  [#10155](https://github.com/nextcloud/spreed/issues/10155)
- Show a hint when the call is running since one hour
  [#10101](https://github.com/nextcloud/spreed/issues/10101)
- Show the talking time of participants in the right sidebar
  [#10145](https://github.com/nextcloud/spreed/issues/10145)

### Changed
- System messages of the same actions are now grouped
  [#10143](https://github.com/nextcloud/spreed/issues/10143)
- Update dependencies

### Fixed
- Fix resetting the bruteforce protection when joining a conversation
  [#10165](https://github.com/nextcloud/spreed/issues/10165)

## 17.0.3 – 2023-07-28
### Changed
- Update dependencies

### Fixed
- Fix duplicate messages and improve performance
  [#10070](https://github.com/nextcloud/spreed/issues/10070)
- fix(SIP): Show SIP info also when enabled without user PIN
  [#10064](https://github.com/nextcloud/spreed/issues/10064)
- fix(settings): Hide description and status from 1-1 conversation settings
  [#10057](https://github.com/nextcloud/spreed/issues/10057)

## 17.0.2 – 2023-07-20
### Changed
- Automatic transcription of call recordings is now opt-in
  [#9971](https://github.com/nextcloud/spreed/issues/9971)
- Update dependencies

### Fixed
- Fix position of "Scroll to bottom" button in the chat view
  [#9871](https://github.com/nextcloud/spreed/issues/9871)
  [#9858](https://github.com/nextcloud/spreed/issues/9858)
- Improve accessibility of conversation creation dialog
  [#9954](https://github.com/nextcloud/spreed/issues/9954)
- Make chat stay scrolling when the last message receives its first reaction
  [#9956](https://github.com/nextcloud/spreed/issues/9956)
- Don't emit event when the user status did not change on refetching
  [#10005](https://github.com/nextcloud/spreed/issues/10005)
- Attempt to further improve the performance of conversation list updates with hundreds of conversations 
  [#10016](https://github.com/nextcloud/spreed/issues/10016)

## 17.0.1 – 2023-06-23
### Changed
- Include display name in participant update signaling message
  [#9822](https://github.com/nextcloud/spreed/issues/9822)
- Update dependencies

### Fixed
- Improve frontend responsiveness with many conversations
  [#9841](https://github.com/nextcloud/spreed/issues/9841)
- Don't make the conversation list scroll when the selected conversation is already visible
  [#9782](https://github.com/nextcloud/spreed/issues/9782)
  [#9796](https://github.com/nextcloud/spreed/issues/9796)
- Fix creating files from the "Blank" template option
  [#9818](https://github.com/nextcloud/spreed/issues/9818)
- Make conversation description and name selectable
  [#9780](https://github.com/nextcloud/spreed/issues/9780)
- Hide poll voting details when not filled
  [#9821](https://github.com/nextcloud/spreed/issues/9821)
  [#9819](https://github.com/nextcloud/spreed/issues/9819)
- Fix visibility issue in the create conversation dialog on small screens
  [#9794](https://github.com/nextcloud/spreed/issues/9794)
  [#9843](https://github.com/nextcloud/spreed/issues/9843)
- Include display name in participant update signaling message
  [#9822](https://github.com/nextcloud/spreed/issues/9822)
- Update group displayname cache when the group was renamed
  [#9839](https://github.com/nextcloud/spreed/issues/9839)

## 17.0.0 – 2023-06-12
### Added
- Conversations can now have an avatar or emoji as icon
  [#927](https://github.com/nextcloud/spreed/issues/927)
- Virtual backgrounds are now available in addition to the blurred background in video calls
  [#9251](https://github.com/nextcloud/spreed/issues/9251)
- Reactions are now available during calls
  [#9249](https://github.com/nextcloud/spreed/issues/9249)
- Typing indicators show which users are currently typing a message
  [#9248](https://github.com/nextcloud/spreed/issues/9248)
- Groups can now be mentioned in chats
  [#6339](https://github.com/nextcloud/spreed/issues/6339)
- Call recordings are automatically transcribed if a transcription provider app is registered
  [#9274](https://github.com/nextcloud/spreed/issues/9274)
- Chat messages can be translated if a translation provider app is registered
  [#9273](https://github.com/nextcloud/spreed/issues/9273)

## 17.0.0-rc.4 – 2023-06-09
### Fixed

- fix(chat): Fix dark/light theme in messages loading placeholder
  [#9720](https://github.com/nextcloud/spreed/issues/9720)
- fix(TypingIndicator): Signaling messages wrong when conversation is switched
  [#9615](https://github.com/nextcloud/spreed/issues/9615)
- fix(TypingIndicator): Typing indicator does not "expire"
  [#9604](https://github.com/nextcloud/spreed/issues/9604)
- fix(TypingIndicator): Typing indicator distorted on small sidebar during call when 3 or more people are typing
  [#9589](https://github.com/nextcloud/spreed/issues/9589)
- fix(mediasettings): Aria label keywords of virtual backgrounds are not translatable
  [#9610](https://github.com/nextcloud/spreed/issues/9610)
- fix(mediasettings): Conversation picture picker does not work well on mobile
  [#9565](https://github.com/nextcloud/spreed/issues/9565)
- fix(conversations): Do not scroll to conversations that are already visible in the conversations list
  [#9582](https://github.com/nextcloud/spreed/issues/9582)
- fix(conversations): Fix error when creating a conversation from search results
  [#9709](https://github.com/nextcloud/spreed/pull/9709)

## 17.0.0-rc.3 – 2023-06-02
### Fixed

- fix(MediaSettings): Fix guests being prompted with login window when blurring background
  [#9620](https://github.com/nextcloud/spreed/issues/9620)
- fix(TypingIndicator): Actors are only unique by type and id
  [#9625](https://github.com/nextcloud/spreed/pull/9625)

## 17.0.0-rc.2 – 2023-05-25
### Fixed
- fix(reactions): Fix own call reactions when the high-performance backend is used
  [#9586](https://github.com/nextcloud/spreed/issues/9586)
- fix(mediasettings): Hide virtual background options when not supported
  [#9611](https://github.com/nextcloud/spreed/issues/9611)
- fix(mediasettings): Fix broken aria-label
  [#9617](https://github.com/nextcloud/spreed/issues/9617)
- fix(CallView): Fix Fullscreen mode support in ViewerOverlay
  [#9613](https://github.com/nextcloud/spreed/issues/9613)
  [#9618](https://github.com/nextcloud/spreed/issues/9618)
- fix(CallView): fix no local video on empty call in not grid mode
  [#9584](https://github.com/nextcloud/spreed/issues/9584)
- fix(chat): Update own read marker before triggering events when posting
  [#9612](https://github.com/nextcloud/spreed/issues/9612)
- fix(chat): Don't send startTyping signaling message for each keystroke
  [#9614](https://github.com/nextcloud/spreed/issues/9614)

## 17.0.0-rc.1 – 2023-05-17
### Changed
- Update dependencies

### Fixed
- Fix virtual background image being stretched instead of cropped
  [#9549](https://github.com/nextcloud/spreed/issues/9549)

## 17.0.0-beta.3 – 2023-05-12
### Added
- Allow translating chat messages
  [#9512](https://github.com/nextcloud/spreed/issues/9512)

### Changed
- Update several dependencies

### Fixed
- Media settings or not respected
  [#9513](https://github.com/nextcloud/spreed/issues/9513)
- Fix clearing guests over and over again on MySQL and Oracle
  [#9517](https://github.com/nextcloud/spreed/issues/9517)
- Prevent empty chat messages
  [#9509](https://github.com/nextcloud/spreed/issues/9509)

## 17.0.0-beta.2 – 2023-05-09
### Added
- Typing indicators frontend and settings
  [#9455](https://github.com/nextcloud/spreed/issues/9455)
  [#9431](https://github.com/nextcloud/spreed/issues/9431)

### Changed
- Update several dependencies

### Fixed
- Show empty call view on viewer overlay
  [#9460](https://github.com/nextcloud/spreed/issues/9460)
- Fix avatar handling when interacting with non-supporting backends
  [#9492](https://github.com/nextcloud/spreed/issues/9492)

## 17.0.0-beta.1 – 2023-05-04
### Added
- Conversations can now have an avatar or emoji as icon
  [#927](https://github.com/nextcloud/spreed/issues/927)
- Virtual backgrounds are now available in addition to the blurred background in video calls
  [#9251](https://github.com/nextcloud/spreed/issues/9251)
- Reactions are now available during calls
  [#9249](https://github.com/nextcloud/spreed/issues/9249)
- Typing indicators show which users are currently typing a message
  [#9248](https://github.com/nextcloud/spreed/issues/9248)
- Groups can now be mentioned in chats
  [#6339](https://github.com/nextcloud/spreed/issues/6339)
- Call recordings are automatically transcribed if a transcription provider app is registered
  [#9274](https://github.com/nextcloud/spreed/issues/9274)
- Chat messages can be translated if a translation provider app is registered
  [#9273](https://github.com/nextcloud/spreed/issues/9273)

### Changed
- Update several dependencies

