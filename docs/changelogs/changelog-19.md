<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 19.0.15 – 2025-04-04
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(calls): Do not reset previous connected users after resuming in a call
  [#14735](https://github.com/nextcloud/spreed/issues/14735)
- fix(sidebar): Show tooltips when Talk is in the sidebar
  [#14697](https://github.com/nextcloud/spreed/issues/14697)
- fix(guests): Fix style and labels on public share page as a guest
  [#14720](https://github.com/nextcloud/spreed/issues/14720)
  [#14726](https://github.com/nextcloud/spreed/issues/14726)
- fix(calls): Skip password verification for guests that are reconnecting to the call
  [#14787](https://github.com/nextcloud/spreed/pull/14787)
- fix(calls): Fix leaving call if a signaling message is received while reconnecting
  [#14788](https://github.com/nextcloud/spreed/pull/14788)

## 19.0.14 – 2025-03-12
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(search): Include caption messages in search results
  [#14551](https://github.com/nextcloud/spreed/issues/14551)
- fix(conversation): Stay in chat when removing a group or team the moderator is a member of
  [#14395](https://github.com/nextcloud/spreed/issues/14395)
- fix(dashboard): Hide lobbied conversations from the dashboard
  [#14610](https://github.com/nextcloud/spreed/issues/14610)
- fix(calls): Further improve false positives when showing the connection warning
  [#14448](https://github.com/nextcloud/spreed/issues/14448)
- fix(reminder): Log when generating a reminder failed
  [#14616](https://github.com/nextcloud/spreed/issues/14616)

## 19.0.13 – 2025-02-13
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(bots): Allow users to edit messages of bots in one-to-one conversations
  [#14360](https://github.com/nextcloud/spreed/issues/14360)
- fix(calls): Address some false positives when showing the connection warning
  [#14250](https://github.com/nextcloud/spreed/issues/14250)
- fix(conversation): Don't suggest teams that are already added to the conversation
  [#14347](https://github.com/nextcloud/spreed/issues/14347)

## 19.0.12 – 2025-01-16
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(calls): Retain names of guests when they disconnect from the High-performance backend
  [#13983](https://github.com/nextcloud/spreed/issues/13983)
- fix(search): Add pagination support to the conversation search in unified search
  [#14033](https://github.com/nextcloud/spreed/issues/14033)
- fix(setupcheck): Check server times of Webserver nodes and High-performance backend to be in sync
  [#14015](https://github.com/nextcloud/spreed/issues/14015)
- fix(moderation): Allow promoting self-joined users
  [#14081](https://github.com/nextcloud/spreed/issues/14081)
- fix(calls): Fix "Talk while muted" toast
  [#14026](https://github.com/nextcloud/spreed/issues/14026)
- fix(firstrun): Create default conversations when loading the dashboard
  [#14090](https://github.com/nextcloud/spreed/issues/14090)

## 19.0.11 – 2024-11-07
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Fix layout for guests on public conversations
  [#13620](https://github.com/nextcloud/spreed/issues/13620)
- fix(UI): Improve handling of sidebar on mobile view
  [#12693](https://github.com/nextcloud/spreed/issues/12693)
- fix(calls): Fix background blur performance if Server was not upgraded
  [#13603](https://github.com/nextcloud/spreed/issues/13603)

## 19.0.10 – 2024-10-10
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(performance): Fade out local blur-filter option for Nextcloud wide setting
  [#13100](https://github.com/nextcloud/spreed/issues/13100)
- fix(avatar): Fix missing translations
  [#13410](https://github.com/nextcloud/spreed/issues/13410)
- fix(chat): Expire message cache when deleting the last message
  [#13392](https://github.com/nextcloud/spreed/issues/13392)
- fix(call): Correctly ignore media offers from users without permissions when internal signaling is used
  [#13494](https://github.com/nextcloud/spreed/issues/13494)
- fix(call): Fix missing call sounds in Safari when tab is moved to the background
  [#13352](https://github.com/nextcloud/spreed/issues/13352)

## 19.0.9 – 2024-09-12
### Fixed
- fix(federation): Fix federation invites accepting from the notification
  [#13153](https://github.com/nextcloud/spreed/issues/13153)
- fix(chat): Fix "You deleted the message" when performed by federated user with same ID
  [#13250](https://github.com/nextcloud/spreed/issues/13250)
- fix(files): Keep order of attachments when sharing multiple
  [#13099](https://github.com/nextcloud/spreed/issues/13099)
- fix(avatar): Don't overwrite user avatar when selecting a square for a conversation
  [#13277](https://github.com/nextcloud/spreed/issues/13277)

## 19.0.8 – 2024-08-22
### Changed
- Update several dependencies

### Fixed
- fix(settings): hide secrets in password fields
  [#12842](https://github.com/nextcloud/spreed/issues/12842)
- fix(conversation): Fix adding and removing permissions
  [#13081](https://github.com/nextcloud/spreed/issues/13081)
- fix(session): Fix generating session id again if duplicated
  [#12745](https://github.com/nextcloud/spreed/issues/12745)
- fix(sidebar): hide sidebar button in lobby
  [#13070](https://github.com/nextcloud/spreed/issues/13070)
- fix(call): prevent navigating away when clicking on a quote while being in a call
  [#12841](https://github.com/nextcloud/spreed/issues/12841)
- fix(federation): fix system message when removed user has same userId as the moderator
  [#13054](https://github.com/nextcloud/spreed/issues/13054)
- fix(federation): correctly check list of allowed groups when federation is limited
  [#13069](https://github.com/nextcloud/spreed/issues/13069)
- fix(federation): show lobby in federated conversations
  [#12789](https://github.com/nextcloud/spreed/issues/12789)
- fix(federation): don't create system messages inside remote conversations
  [#12788](https://github.com/nextcloud/spreed/issues/12788)
- fix(federation): ignore outdated sessions when generating notifications
  [#12742](https://github.com/nextcloud/spreed/issues/12742)

## 19.0.7 – 2024-07-15
### Fixed
- fix(federation): Fix missing notifications in https-federated conversations (Nextcloud Server 29.0.4 or later - Part 3)
  [#12724](https://github.com/nextcloud/spreed/pull/12724)
- fix(chat): Fix chat not loading new messages anymore in new conversation when switching quickly after writing a message
  [#12721](https://github.com/nextcloud/spreed/pull/12721)
- fix(chat): Fix missing parent message when a chained child message gets edited or deleted
  [#12719](https://github.com/nextcloud/spreed/pull/12719)

## 19.0.6 – 2024-07-12
### Fixed
- fix(chat): Fix broken widgets by updating nextcloud/vue library
  [#12610](https://github.com/nextcloud/spreed/pull/12610)
- fix(chat): Fix sidebar opening and closing
  [#12610](https://github.com/nextcloud/spreed/pull/12610)
- fix(federation): Allow sessions to mark themselves as inactive and block notifications when session is active
  [#12689](https://github.com/nextcloud/spreed/pull/12689)
- fix(federation): Correctly handle federation with Nextcloud Server 29.0.4 or later - Part 2
  [#12687](https://github.com/nextcloud/spreed/pull/12687)

## 19.0.5 – 2024-07-11
### Fixed
- fix(federation): Correctly handle federation with Nextcloud Server 29.0.4 or later
  [#12663](https://github.com/nextcloud/spreed/pull/12663)
- fix(chat): Fix scrolling to a quoted message in the desktop client
  [#12653](https://github.com/nextcloud/spreed/pull/12653)
- fix(conversations): Improve sharing data between different tabs
  [#12637](https://github.com/nextcloud/spreed/pull/12637)
- fix(chat): Improve update of messages list in the sidebar and the chat
  [#12636](https://github.com/nextcloud/spreed/pull/12636)
- fix(chat): Show loading spinner when submitting an edited message
  [#12674](https://github.com/nextcloud/spreed/pull/12674)
- fix(chat): Workaround rendering issue in Safari when unread marker is removed
  [#12669](https://github.com/nextcloud/spreed/pull/12669)
- fix(chat): Fix missing reference data when linking to a federated chat message
  [#12665](https://github.com/nextcloud/spreed/pull/12665)
- fix(chat): Fix contrast of unread counter when being mentioned in the chat during a call
  [#12605](https://github.com/nextcloud/spreed/pull/12605)
- fix(chat): Don't group certain system messages when the actor is different
  [#12642](https://github.com/nextcloud/spreed/pull/12642)
- fix(sharing): Improve performance when loading chat messages with file shares
  [#12554](https://github.com/nextcloud/spreed/pull/12554)
- fix(sharing): Fix share detection within object stores
  [#12629](https://github.com/nextcloud/spreed/pull/12629)
- fix(dashboard): Fix missing dashboard icon
  [#12673](https://github.com/nextcloud/spreed/pull/12673)

## 19.0.4 – 2024-06-27
### Changed
- feat(call): Add option to mirror video preview
  [#12593](https://github.com/nextcloud/spreed/pull/12593)
- feat(call): Add 'Participants' tab to 1-1 calls
  [#12594](https://github.com/nextcloud/spreed/pull/12594)

### Fixed
- fix(federation): Update federation invites counter
  [#12544](https://github.com/nextcloud/spreed/pull/12544)
- fix(chat): Hide reply button for participants without chat permissions
  [#12577](https://github.com/nextcloud/spreed/pull/12577)
- fix(bots): Show error messages during bots setup 
  [#12572](https://github.com/nextcloud/spreed/pull/12572)
- fix(sidebar): Restore missing tab after switching a conversation
  [#12587](https://github.com/nextcloud/spreed/pull/12587)
- fix(chat): Adjust toast message on message deletion 
  [#12589](https://github.com/nextcloud/spreed/pull/12589)
- fix(chat): Fix marking conversation as read for hidden messages
  [#12592](https://github.com/nextcloud/spreed/pull/12592)

## 19.0.3 – 2024-06-18
### Fixed
- fix(chat): visual alignment of typing indicator for wide screens
  [#12521](https://github.com/nextcloud/spreed/pull/12521)
- fix(call): remove sound interference in Safari after audio disconnecting
  [#12534](https://github.com/nextcloud/spreed/pull/12534)

## 19.0.2 – 2024-06-13
### Fixed
- fix(call): Fix audio issue in Safari when a user unmutes after a longer time while the tab is inactive
  [#12511](https://github.com/nextcloud/spreed/pull/12511)
- fix(bots): Fix bots with self-signed certificates
  [#12471](https://github.com/nextcloud/spreed/pull/12471)
- fix(chat): Improve scrolling behaviour when reopening a conversation
  [#12199](https://github.com/nextcloud/spreed/pull/12199)
- fix(chat): Better handling of captioned messages in federated conversations
  [#12375](https://github.com/nextcloud/spreed/pull/12375)
- fix(attachments): Fix creating new files from templates
  [#12488](https://github.com/nextcloud/spreed/pull/12488)
- fix(call): Directly sync the preferences with the device selection
  [#12493](https://github.com/nextcloud/spreed/pull/12493)
- fix(call): Give feedback when trying to "Ring" a participant that has DND enabled
  [#12377](https://github.com/nextcloud/spreed/pull/12377)
- fix(breakoutrooms): Don't allow to enable guests in breakout rooms until it's supported
  [#12457](https://github.com/nextcloud/spreed/pull/12457)
- fix(UI): Improve button in mobile UI to use less space
  [#12473](https://github.com/nextcloud/spreed/pull/12473)

## 19.0.1 – 2024-05-23
### Changed
- fix(editing): restore default behaviour of keyboard hotkeys Ctrl+Up
  [#12254](https://github.com/nextcloud/spreed/pull/12254)
- fix: replace emoji-mart-vue-fast lib usage with nextcloud/vue function
  [#12306](https://github.com/nextcloud/spreed/pull/12306)
- feat(capabilities): Expose which capabilities should be considered local vs federated
  [#12316](https://github.com/nextcloud/spreed/pull/12316)

### Fixed
- fix(call): open chat sidebar in call by click on toast
  [#12196](https://github.com/nextcloud/spreed/pull/12196)
- fix(LeftSidebar): small glitch on sidebar scroll
  [#12286](https://github.com/nextcloud/spreed/pull/12286)
- fix(MessagesList): clean up expired, removed messages from the chat
  [#12287](https://github.com/nextcloud/spreed/pull/12287)
- fix(chat): focus submit on upload attachments without caption
  [#12296](https://github.com/nextcloud/spreed/pull/12296)
- fix(dashboard): Fix dashboard when the last message of a chat expired
  [#12309](https://github.com/nextcloud/spreed/pull/12309)
- fix(notifications): Preparse call notifications for improved performance
  [#12320](https://github.com/nextcloud/spreed/pull/12320)
- fix(polls): Remove actor info from system message
  [#12344](https://github.com/nextcloud/spreed/pull/12344)
- fix(federation): Don't send notifications for most system messages in federation
  [#12371](https://github.com/nextcloud/spreed/pull/12371)
- fix(recording): Stop broken recording backend
  [#12403](https://github.com/nextcloud/spreed/pull/12403)
- fix(recording): Handle the problem gracefully when the recording can not be uploaded
  [#12404](https://github.com/nextcloud/spreed/pull/12404)

## 19.0.0 – 2024-04-24
### Added
- Messages can now be edited by logged-in authors and moderators for one day
  [#1836](https://github.com/nextcloud/spreed/issues/1836)
- Allow todo lists in chat messages to be interactive
  [#12065](https://github.com/nextcloud/spreed/issues/12065)
- Added a "In conversation" search filter
  [#11456](https://github.com/nextcloud/spreed/issues/11456)
- Save unsent messages in browser storage so they survive a page reload or browser restart
  [#3055](https://github.com/nextcloud/spreed/issues/3055)
- Allow to accept individual users when the lobby is enabled
  [#8601](https://github.com/nextcloud/spreed/issues/8601)
- Flavored Markdown in messages
  [#10066](https://github.com/nextcloud/spreed/issues/10066)
- Allow to see all reactions
  [#11508](https://github.com/nextcloud/spreed/issues/11508)
- Preview: Federated chatting
  [#11231](https://github.com/nextcloud/spreed/issues/11231)

### Changed
- Update translations
- Update several dependencies
- Added support for Janus 1.x
- Prepare frontend code for a migration to Vue3
- Migrated various icons to Material Design icons
- Deleting messages is now possible without a time limitation (was 6 hours)
  [#11408](https://github.com/nextcloud/spreed/issues/11408)
- Guests are now rate-limited on mentioning users
  [#11072](https://github.com/nextcloud/spreed/issues/11072)
- Make polls more visible in the chat when they are posted during a call
  [#11372](https://github.com/nextcloud/spreed/issues/11372)
- Bots can now be installed by apps with limited feature flags
  [#11630](https://github.com/nextcloud/spreed/issues/11630)
- Save all previously picked devices to improve the call experience when switching between different working situations
  [#12067](https://github.com/nextcloud/spreed/issues/12067)

### Deprecation
- Commands: This will be the last major version that supports commands. Please migrate your commands to webhook based bots instead.

## 19.0.0-rc.6 – 2024-04-22
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(chat): restrict checkbox editing in one-to-one conversations
  [#12160](https://github.com/nextcloud/spreed/pull/12160)
  [#12176](https://github.com/nextcloud/spreed/pull/12176)
- fix(chat): Fix clearing the input field after file upload
  [#12061](https://github.com/nextcloud/spreed/issues/12061)
- fix(chat): Fix setting known chat messages borders after leaving the conversation
  [#12183](https://github.com/nextcloud/spreed/pull/12183)
- fix(dashboard): Dashboard does not show mentions from federated conversations
  [#12163](https://github.com/nextcloud/spreed/pull/12163)

## 19.0.0-rc.5 – 2024-04-18
### Changed
- Update translations

### Fixed
- fix(lobby): Show the timezone and relative time when enabling the lobby
  [#12135](https://github.com/nextcloud/spreed/issues/12135)
- fix(shareIntegration): Fix handle to close and open the right sidebar on publish share links
  [#12134](https://github.com/nextcloud/spreed/issues/12134)
- fix(chat): Fix collapsing grouped system message
  [#12139](https://github.com/nextcloud/spreed/issues/12139)
- fix(attachments): Fix missing icons when creating a file in a chat
  [#12138](https://github.com/nextcloud/spreed/issues/12138)
- fix(media): Fix initial selection of devices
  [#12152](https://github.com/nextcloud/spreed/pull/12152)
  [#12146](https://github.com/nextcloud/spreed/pull/12146)

## 19.0.0-rc.4 – 2024-04-16
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(calls): Correctly pick the device when showing the device picker after a page reload
  [#12124](https://github.com/nextcloud/spreed/issues/12124)
- fix(conversations): Correctly update conversations when the read marker changes via another device or window
  [#9590](https://github.com/nextcloud/spreed/issues/9590)
- fix(chat): Make "silent send" state more obvious for follow up messages
  [#12118](https://github.com/nextcloud/spreed/issues/12118)
- fix(dashboard): Correctly handle 1-1 conversations with unread system messages
  [#12073](https://github.com/nextcloud/spreed/issues/12073)

## 19.0.0-rc.3 – 2024-04-11
### Added
- feat(chat): Allow todo lists to be interactive
  [#12065](https://github.com/nextcloud/spreed/issues/12065)
- feat(devicechecker): Save all previously picked devices instead of the last one
  [#12067](https://github.com/nextcloud/spreed/issues/12067)
- feat(OCM): Register TalkV1 as OCM resource
  [#12045](https://github.com/nextcloud/spreed/issues/12045)
- feat(dashboard): Implement Dashboard Widget APIv2
  [#12035](https://github.com/nextcloud/spreed/issues/12035)

### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(chat): Mentions in todo lists are not rendered
  [#12009](https://github.com/nextcloud/spreed/issues/12009)
- fix(one2one): Allow the desktop client to handle one-to-one links without a reload
  [#12047](https://github.com/nextcloud/spreed/issues/12047)
- fix(attachments): Ensure all rich object parameters are strings
  [#12043](https://github.com/nextcloud/spreed/issues/12043)
- fix(openapi): Ensure operation IDs are unique
  [#12030](https://github.com/nextcloud/spreed/issues/12030)
- fix(openapi): Object inheritance for chat and proxy messages
  [#12056](https://github.com/nextcloud/spreed/issues/12056)
- fix(chat): Don't close emoji picker when clicking on the search input or a category
  [#12062](https://github.com/nextcloud/spreed/issues/12062)

## 19.0.0-rc.2 – 2024-04-04
### Added
- feat(desktop): Prepare to support screensharing in the desktop client
  [#12003](https://github.com/nextcloud/spreed/issues/12003)

### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(federation): Fix posting federated messages with oracle database
  [#11999](https://github.com/nextcloud/spreed/issues/11999)

## 19.0.0-rc.1 – 2024-03-28
### Added
- Add a header to the room list indicating pending invites
  [#11944](https://github.com/nextcloud/spreed/issues/11944)

### Changed
- Update translations
- Update several dependencies

### Fixed
- Don't close the modal on outside click when the user started to fill them
  [#11941](https://github.com/nextcloud/spreed/issues/11941)
- Fix user confusion when federating with a user that has the same user ID
  [#11942](https://github.com/nextcloud/spreed/issues/11942)
- Don't transfer ownership federated memberships as it doesn't work
  [#11950](https://github.com/nextcloud/spreed/issues/11950)
- Provide the correct container for the file picker
  [#11939](https://github.com/nextcloud/spreed/issues/11939)
- Adjust the cursor to be a grabbing hand when dragging the presenter view
  [#11903](https://github.com/nextcloud/spreed/issues/11903)
- Fix file names overflowing the box when chat is in the right sidebar
  [#11937](https://github.com/nextcloud/spreed/issues/11937)

## 19.0.0-beta.5 – 2024-03-26
### Changed
- Update translations
- Update several dependencies

### Fixed
- Fix handling of cloud ID when provided in wrong casing
  [#11922](https://github.com/nextcloud/spreed/issues/11922)
- Fix flow notifications triggered by own actions and in note-to-self conversations
  [#11918](https://github.com/nextcloud/spreed/issues/11918)
- Hide call related user settings in federated conversations
  [#11892](https://github.com/nextcloud/spreed/issues/11892)

## 19.0.0-beta.4 – 2024-03-21
### Changed
- Update translations
- Update several dependencies

### Fixed
- Adjust read handling in received federated conversations to match normal conversations
  [#11861](https://github.com/nextcloud/spreed/issues/11861)
- Allow inviting federated users while creating a conversation
  [#11862](https://github.com/nextcloud/spreed/issues/11862)
- Fix duplicate messages when sharing recordings or transcripts
  [#11863](https://github.com/nextcloud/spreed/issues/11863)
- Prevent manipulating receiving federated conversations via OCC
  [#11855](https://github.com/nextcloud/spreed/issues/11855)

## 19.0.0-beta.3 – 2024-03-19
### Added
- Preview: Federated chatting - Implemented reminders
  [#11814](https://github.com/nextcloud/spreed/issues/11814)

### Changed
- Update translations
- Update several dependencies
- Provide better OpenAPI data for message parameters
  [#11807](https://github.com/nextcloud/spreed/issues/11807)

### Fixed
- Fix editing and deleting not reflected correctly in left sidebar for federated conversations
  [#11839](https://github.com/nextcloud/spreed/issues/11839)
- Fix missing expiration of cached messages in federated conversations
  [#11816](https://github.com/nextcloud/spreed/issues/11816)
- Make conversation avatars dark mode again
  [#11840](https://github.com/nextcloud/spreed/issues/11840)
- Reduce the cache time of avatars when the remote was not reachable
  [#11842](https://github.com/nextcloud/spreed/issues/11842)
- Don't notify user about own messages when replying or mentioning themselves
  [#11815](https://github.com/nextcloud/spreed/issues/11815)
- Fix read marker and unread behaviour in federated conversations again
  [#11810](https://github.com/nextcloud/spreed/issues/11810)
- Hide federated conversations from various integrations
  [#11809](https://github.com/nextcloud/spreed/issues/11809)

## 19.0.0-beta.2 – 2024-03-14
### Added
- Preview: Federated chatting - Implemented reactions
  [#11772](https://github.com/nextcloud/spreed/issues/11772)
- Preview: Federated chatting - Implemented polls
  [#11653](https://github.com/nextcloud/spreed/issues/11653)

### Changed
- Update translations
- Update several dependencies
- Mark federated users as such in the participant list
  [#11771](https://github.com/nextcloud/spreed/issues/11771)

### Fixed
- Fix retry behaviour when the host or federated instance was not reachable
  [#11780](https://github.com/nextcloud/spreed/issues/11780)
- Fix UI spaming chat requests when memory cache was cleared
  [#11788](https://github.com/nextcloud/spreed/issues/11788)
- Fix showing federated users as options when providing a cloudId
  [#11794](https://github.com/nextcloud/spreed/issues/11794)
- Fix read marker and unread behaviour in federated conversations
  [#11792](https://github.com/nextcloud/spreed/issues/11792)
- Notify federated servers when a hosted conversation is deleted
  [#11790](https://github.com/nextcloud/spreed/issues/11790)
- Proxy federation requests with the users language
  [#11801](https://github.com/nextcloud/spreed/issues/11801)
- Fix cursor resetting to the beginning of the input field after having typed a "lower than" or "greater than"
  [#11803](https://github.com/nextcloud/spreed/issues/11803)
- Directly update the conversation data when marking a conversation read or unread
  [#11678](https://github.com/nextcloud/spreed/issues/11678)
- Silent message setting is not remembered with good user experience
  [#11591](https://github.com/nextcloud/spreed/issues/11591)
- Silent call setting is not remembered with good user experience
  [#8323](https://github.com/nextcloud/spreed/issues/8323)

### Known issues
- Federated chatting: Various features are still visible but not functional

## 19.0.0-beta.1 – 2024-03-08
### Added
- Messages can now be edited by logged-in authors and moderators for one day
  [#1836](https://github.com/nextcloud/spreed/issues/1836)
- Added a "In conversation" search filter
  [#11456](https://github.com/nextcloud/spreed/issues/11456)
- Save unsent messages in browser storage so they survive a page reload or browser restart
  [#3055](https://github.com/nextcloud/spreed/issues/3055)
- Allow to accept individual users when the lobby is enabled
  [#8601](https://github.com/nextcloud/spreed/issues/8601)
- Flavored Markdown in messages
  [#10066](https://github.com/nextcloud/spreed/issues/10066)
- Allow to see all reactions
  [#11508](https://github.com/nextcloud/spreed/issues/11508)
- Preview: Federated chatting
  [#11231](https://github.com/nextcloud/spreed/issues/11231)

### Changed
- Update translations
- Update several dependencies
- Added support for Janus 1.x
- Prepare frontend code for a migration to Vue3
- Migrated various icons to Material Design icons
- Deleting messages is now possible without a time limitation (was 6 hours)
  [#11408](https://github.com/nextcloud/spreed/issues/11408)
- Guests are now rate-limited on mentioning users
  [#11072](https://github.com/nextcloud/spreed/issues/11072)
- Make polls more visible in the chat when they are posted during a call
  [#11372](https://github.com/nextcloud/spreed/issues/11372)
- Bots can now be installed by apps with limited feature flags
  [#11630](https://github.com/nextcloud/spreed/issues/11630)

### Deprecation
- Commands: This will be the last major version that supports commands. Please migrate your commands to webhook based bots instead.

### Known issues
- Federated chatting: Various features are still visible but not functional

