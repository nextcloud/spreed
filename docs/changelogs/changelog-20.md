<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 20.1.10 – 2025-09-18
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Support at-all in captions
  [#15746](https://github.com/nextcloud/spreed/issues/15746)
- fix(chat): Fix loading a completely empty conversation as a guest
  [#15551](https://github.com/nextcloud/spreed/issues/15551)
- fix(conversation): Fix joining and leaving conversations when errors occurred
  [#15796](https://github.com/nextcloud/spreed/issues/15796)

## 20.1.9 – 2025-07-17
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(settings): Allow changing call notification level for federated conversations
  [#15515](https://github.com/nextcloud/spreed/issues/15515)
- fix(settings): Fix class name of background job checking certificates
  [#15463](https://github.com/nextcloud/spreed/issues/15463)
- fix(polls): Fix deleting poll drafts
  [#15535](https://github.com/nextcloud/spreed/issues/15535)

## 20.1.8 – 2025-07-03
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Remove items from the shared items list when a message is deleted
  [#15222](https://github.com/nextcloud/spreed/issues/15222)
- fix(chat): Allow deleting shared call recordings
  [#15241](https://github.com/nextcloud/spreed/issues/15241)
- fix(federation): Fix sending invites from conversations without an owner
  [#15353](https://github.com/nextcloud/spreed/issues/15353)
- fix(settings): Do not break when settings has an incomplete server URL
  [#15452](https://github.com/nextcloud/spreed/issues/15452)

## 20.1.7 – 2025-05-22
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(call): Fix missing call notification when SIP dial-in is starting a call
  [#14991](https://github.com/nextcloud/spreed/issues/14991)
- fix(chat): Improve regex of todo-list handling also uppercase X
  [#14903](https://github.com/nextcloud/spreed/issues/14903)
- fix(one-to-one): Add the other participant when sharing a file in one-to-one
  [#14850](https://github.com/nextcloud/spreed/issues/14850)
- fix(performance): Fix unnecessary user_status requests from avatar component
  [#14934](https://github.com/nextcloud/spreed/issues/14934)

## 20.1.6 – 2025-04-10
### Changed
- Update translations
- Update dependencies

### Fixed
- fix: Improve performance of conversation list
  [#14810](https://github.com/nextcloud/spreed/issues/14810)
  [#14830](https://github.com/nextcloud/spreed/issues/14830)
  [#14834](https://github.com/nextcloud/spreed/issues/14834)
- fix: Improve performance when rendering system messages
  [#14816](https://github.com/nextcloud/spreed/issues/14816)
- fix(guests): Allow guests to reload the page without re-entering the password
  [#14785](https://github.com/nextcloud/spreed/issues/14785)
- fix(federation): Fix calls when federated server receive messages in wrong order
  [#14769](https://github.com/nextcloud/spreed/pull/14769)
- fix(calls): Fix call after resuming connection
  [#14736](https://github.com/nextcloud/spreed/pull/14736)
- fix(calls): Fix wrongly showing "Missed call" in one-to-one conversations
  [#14832](https://github.com/nextcloud/spreed/pull/14832)
- fix(workflows): Adjust workflow registration to new mechanism
  [#14823](https://github.com/nextcloud/spreed/pull/14823)
- fix(polls): Hide intermediate results from anonymous polls
  [#14723](https://github.com/nextcloud/spreed/issues/14723)

## 20.1.5 – 2025-03-12
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(calls): Improve call related system messages in one-to-one conversations
  [#14468](https://github.com/nextcloud/spreed/issues/14468)
- fix(search): Include caption messages in search results
  [#14552](https://github.com/nextcloud/spreed/issues/14552)
- fix(conversation): Stay in chat when removing a group or team the moderator is a member of
  [#14396](https://github.com/nextcloud/spreed/issues/14396)
- fix(chat): Correctly start loading the chat when the lobby is removed
  [#14518](https://github.com/nextcloud/spreed/issues/14518)
- fix(dashboard): Hide lobbied conversations from the dashboard
  [#14611](https://github.com/nextcloud/spreed/issues/14611)
- fix(calls): Further improve false positives when showing the connection warning
  [#14449](https://github.com/nextcloud/spreed/issues/14449)
- fix(calls): Fix guest displayname when exporting call participants
  [#14631](https://github.com/nextcloud/spreed/issues/14631)
- fix(reminder): Log when generating a reminder failed
  [#14617](https://github.com/nextcloud/spreed/issues/14617)

## 20.1.4 – 2025-02-13
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(bots): Allow users to edit messages of bots in one-to-one conversations
  [#14352](https://github.com/nextcloud/spreed/issues/14352)
- fix(calls): Address some false positives when showing the connection warning
  [#14251](https://github.com/nextcloud/spreed/issues/14251)
- fix(dashboard): Hide archived conversations from dashboard unless mentioned
  [#14298](https://github.com/nextcloud/spreed/issues/14298)
- fix(conversation): Don't suggest teams that are already added to the conversation
  [#14348](https://github.com/nextcloud/spreed/issues/14348)
- fix(chat): Fix missing "Copy code" button when syntax is used
  [#14309](https://github.com/nextcloud/spreed/issues/14309)

## 20.1.3 – 2025-01-17
### Changed
- Update translations
- Update dependencies
- Clarify the usage of the High-performance backend and warn when it's not configured
  [#14065](https://github.com/nextcloud/spreed/issues/14065)

### Fixed
- fix(moderation): Allow promoting self-joined users
  [#14082](https://github.com/nextcloud/spreed/issues/14082)
- fix(firstrun): Create default conversations when loading the dashboard
  [#14089](https://github.com/nextcloud/spreed/issues/14089)
- fix(archive): Don't add asterix to title for unread messages in archived conversations
  [#14102](https://github.com/nextcloud/spreed/issues/14102)

## 20.1.1 – 2024-12-19
### Changed
- Update translations
- Update dependencies
- perf(calls): Add endpoint for clients to specifically check if a call notification is still current
  [#13950](https://github.com/nextcloud/spreed/issues/13950)

### Fixed
- fix(chat): Add start and end of out-of-office to the note card in one-to-one conversations
  [#13926](https://github.com/nextcloud/spreed/issues/13926)
- fix(chat): Fix chats for offline participants when the chat history was reset
  [#13965](https://github.com/nextcloud/spreed/issues/13965)
- fix(calls): Remove "Share to chat" button from failure notifications of recording summaries
  [#13941](https://github.com/nextcloud/spreed/issues/13941)
- fix(calls): Hide option to start without enabled media when it can not be stored on the server
  [#13953](https://github.com/nextcloud/spreed/issues/13953)
- fix(calls): Retain names of guests when they disconnect from the High-performance backend
  [#13984](https://github.com/nextcloud/spreed/issues/13984)
- fix(calls): Fix missing "Speaking while muted" popup
  [#14027](https://github.com/nextcloud/spreed/issues/14027)
- fix(search): Implement pagination in conversation search results
  [#14034](https://github.com/nextcloud/spreed/issues/14034)
- fix(settings): Confirm server time of High-performance and recording backend in admin settings
  [#14016](https://github.com/nextcloud/spreed/issues/14016)

## 20.1.0 – 2024-12-03
### Added
- Introducing the Nextcloud Talk desktop client for Windows, macOS and Linux
  [Download](https://nextcloud.com/talk-desktop-install)
- feat(calls): Summarize call recordings automatically with AI when installed
  [#13429](https://github.com/nextcloud/spreed/issues/13429)
- feat(chat): Allow to summarize the chat history when there are many unread messages
  [#13430](https://github.com/nextcloud/spreed/issues/13430)
- feat(calls): Allow moderators to download a call participants list
  [#13453](https://github.com/nextcloud/spreed/issues/13453)
- feat(meetings): Allow importing email lists as attendees
  [#13882](https://github.com/nextcloud/spreed/issues/13882)
- feat(email-guests): Identify and recognize guests invited via email address
  [#6098](https://github.com/nextcloud/spreed/issues/6098)
- feat(email-guests): Allow to invite email guests when creating a conversation
  [#4937](https://github.com/nextcloud/spreed/issues/4937)
- feat(polls): Allow to draft, export and import polls
  [#13439](https://github.com/nextcloud/spreed/issues/13439)
- feat(conversations): Allow to archive conversations
  [#6140](https://github.com/nextcloud/spreed/issues/6140)
- feat(conversations): Add direct option to change notification settings to the conversation list again
  [#13870](https://github.com/nextcloud/spreed/issues/13870)
- feat(chat): Add option to directly download attachments
  [Desktop #824](https://github.com/nextcloud/talk-desktop/issues/824)
- feat(voice-messages): Auto play voice messages which are grouped together
  [#13199](https://github.com/nextcloud/spreed/issues/13199)
- feat(calls): Add an option to always disable devices by default
  [#13446](https://github.com/nextcloud/spreed/issues/13446)
- feat(calls): Add option to enable blurred background always by default
  [#13783](https://github.com/nextcloud/spreed/issues/13783)
- feat(conversations): Add settings to automatically lock rooms after days of inactivity
  [#13448](https://github.com/nextcloud/spreed/issues/13448)
- feat(calls): Allow to enforce a maximum call length
  [#13445](https://github.com/nextcloud/spreed/issues/13445)
- feat(chat): Highlight file and object shares with an icon in conversations list

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(conversations): Fix password validation when setting a password
  [#13890](https://github.com/nextcloud/spreed/issues/13890)
- fix(chat): Fix visibility of "Send message without notification" option being enabled
  [#13824](https://github.com/nextcloud/spreed/issues/13824)
- fix(matterbridge): Fix settings disappearing after configuring matterbridge in a conversation
  [#13786](https://github.com/nextcloud/spreed/issues/13786)
- fix(chat): Disable interactiveness of reference by default, to avoid input-focus stealing
  [#4937](https://github.com/nextcloud/spreed/issues/4937)
- fix(avatar): Use person icon for deleted accounts and guests without a name
  [#13754](https://github.com/nextcloud/spreed/issues/13754)
- fix(calls): Omit "with 0 guests" when a call is ended and only 1 logged-in participant joined
  [#13545](https://github.com/nextcloud/spreed/issues/13545)
- fix(polls): rename "Private poll" to "Anonymous poll"
- perf: Fix a performance issue when the page is not reloaded over multiple days

## 20.1.0-rc.3 – 2024-11-28
### Added
- feat(conversations): Add direct option to change notification settings to the conversation list again
  [#13870](https://github.com/nextcloud/spreed/issues/13870)
- feat(meetings): Allow importing email lists as attendees
  [#13882](https://github.com/nextcloud/spreed/issues/13882)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(conversation): Fix password validation when setting a password
  [#13890](https://github.com/nextcloud/spreed/issues/13890)
- fix(calls): Don't disable microphone and camera when it was enabled in the device check
  [#13893](https://github.com/nextcloud/spreed/issues/13893)
- fix(chat): Hide "Generate summary" in federated conversations for now
  [#13881](https://github.com/nextcloud/spreed/issues/13881)
- fix(chat): Fix mention suggestions referring to participants of the previous conversation
  [#13870](https://github.com/nextcloud/spreed/issues/13870)
- fix(chat): Links in markdown todo-lists are only clickable with edit permissions
  [#13865](https://github.com/nextcloud/spreed/issues/13865)

## 20.1.0-rc.2 – 2024-11-21
### Added
- feat(chat): Allow to summarize the chat history when there are many unread messages
  [#13430](https://github.com/nextcloud/spreed/issues/13430)
- feat(call): Summarize call recordings automatically with AI when installed
  [#13429](https://github.com/nextcloud/spreed/issues/13429)
- feat(call): Add option to enable blurred background always by default
  [#13783](https://github.com/nextcloud/spreed/issues/13783)
- feat(conversations): Allow to archive conversations
  [#6140](https://github.com/nextcloud/spreed/issues/6140)
- feat(conversations): Add settings to automatically lock rooms after days of inactivity
  [#13448](https://github.com/nextcloud/spreed/issues/13448)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Fix visibility of the silent send option being enabled
  [#13824](https://github.com/nextcloud/spreed/issues/13824)
- fix(matterbridge): Fix settings disappearing after configuring matterbridge in a conversation
  [#13786](https://github.com/nextcloud/spreed/issues/13786)

## 20.1.0-rc.1 – 2024-11-14
### Added
- feat(polls): Allow to draft, export and import polls
  [#13439](https://github.com/nextcloud/spreed/issues/13439)
- feat(voice-messages): Auto play voice messages which are grouped together
  [#13199](https://github.com/nextcloud/spreed/issues/13199)
- feat(calls): Allow moderators to download a call participants list
  [#13453](https://github.com/nextcloud/spreed/issues/13453)
- feat(calls): Allow to enforce a maximum call length
  [#13445](https://github.com/nextcloud/spreed/issues/13445)
- feat(chat): Add option to directly download attachments
  [Desktop #824](https://github.com/nextcloud/talk-desktop/issues/824)
- feat(calls): Add an option to always disable devices by default
  [#13446](https://github.com/nextcloud/spreed/issues/13446)
- feat(email-guests): Identify and recognize guests invited via email address
  [#6098](https://github.com/nextcloud/spreed/issues/6098)
- feat(email-guests): Allow to invite email guests when creating a conversation
  [#4937](https://github.com/nextcloud/spreed/issues/4937)
- feat(chat): Highlight file and object shares with an icon in conversations list

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Disable interactiveness of reference by default, to avoid focus stealing
  [#4937](https://github.com/nextcloud/spreed/issues/4937)
- fix(avatar): Use person icon for deleted accounts and guests without a name
  [#13754](https://github.com/nextcloud/spreed/issues/13754)
- fix(call): Omit "with 0 guests" when a call is ended and only 1 logged-in participant joined
  [#13545](https://github.com/nextcloud/spreed/issues/13545)
- fix(polls): rename "Private poll" to "Anonymous poll"
- perf: Fix a performance issue when the page is not reloaded over multiple days

## 20.0.2 – 2024-11-07
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(attachments): Fix a performance issue when opening the file picker in Talk
  [#13698](https://github.com/nextcloud/spreed/issues/13698)
- fix(meetings): Fix layout for guests on public conversations
  [#13552](https://github.com/nextcloud/spreed/issues/13552)

## 20.0.1 – 2024-10-10
### Added
- feat(call): Add wave and fire call reactions
  [#13349](https://github.com/nextcloud/spreed/issues/13349)

### Changed
- Update translations
- Update dependencies
- fix(chat): Reduce read-width of chat again
  [#13421](https://github.com/nextcloud/spreed/issues/13421)
- fix(signaling): Deprecate using multiple High-performance backends and the fake-clustering mode
  [#13420](https://github.com/nextcloud/spreed/issues/13420)

### Fixed
- fix(chat): Fix missing push notifications for chat messages
  [#13331](https://github.com/nextcloud/spreed/issues/13331)
- fix(chat): Correctly update "Today"-date separator when the day changes
  [#13434](https://github.com/nextcloud/spreed/issues/13434)
- fix(call): Correctly ignore media offers from users without permissions when internal signaling is used
  [#13495](https://github.com/nextcloud/spreed/issues/13495)
- fix(chat): Expire message cache when deleting the last message
  [#13393](https://github.com/nextcloud/spreed/issues/13393)
- fix(federation): Send the newest conversation properties when retrying OCM notifications
  [#13424](https://github.com/nextcloud/spreed/issues/13424)
- fix(avatar): Fix missing translations
  [#13409](https://github.com/nextcloud/spreed/issues/13409)
- fix(call): Fix missing call sounds in Safari when tab is moved to the background
  [#13353](https://github.com/nextcloud/spreed/issues/13353)
- fix: Fix several popups and menus in fullscreen mode
  [#13399](https://github.com/nextcloud/spreed/issues/13399)

## 20.0.0 – 2024-09-14
### Added
- Federated calls
- Add a task counter for to-do lists in "Note to self" messages
- Allow editing "Note to self" messages forever
- Show blurhash while loading attachments
- Show upcoming event information in conversation
- Banning users and guests
- Allow to prevent "@all" mentions for non-moderators
- Add button to show smart picker
- Dispatch events when enabling or disabling bots
- Show user status messages of other local users in a federated conversation

### Changed
- Requires Nextcloud 30
- High-performance backend with `federation` support is required (Version 2.0.0 or later)
- API performance improvements
- Dynamic order of tiles in a call to prioritize participants with audio or video
- Automatically lower raised hand when participant speaks
- Show confirmation dialog when removing a participant
- Show description when listing open conversations
- Outline the participant count when trying to mention everyone
- Show out-of-office replacement in the out-of-office message

### Known issues
- Federation requires the High-performance backend on both servers
- Federation requires Talk 20 on both servers

## 20.0.0-rc.5 – 2024-09-12
### Added
- Add setup checks for server configuration pitfalls
  [#13260](https://github.com/nextcloud/spreed/issues/13260)

### Fixed
- fix(chat): Fix "You deleted the message" when performed by federated user with same ID
  [#13251](https://github.com/nextcloud/spreed/issues/13251)
- fix(chat): Take attachment shares into account when marking a conversation unread
  [#13253](https://github.com/nextcloud/spreed/issues/13253)
- fix(calls): Temporarily disable call button after a moderator ended the call for everyone to avoid recalling
  [#13268](https://github.com/nextcloud/spreed/issues/13268)
- fix(avatar): Don't overwrite user avatar when selecting a square for a conversation
  [#13278](https://github.com/nextcloud/spreed/issues/13278)

## 20.0.0-rc.4 – 2024-09-03
### Changed
- Update several dependencies
- Add a task counter for to-do lists in "Note to self" messages
  [#13034](https://github.com/nextcloud/spreed/issues/13034)

### Fixed
- Fix accepting federation invites from notification
  [#13146](https://github.com/nextcloud/spreed/issues/13146)
- Show error when joining a call failed
  [#13077](https://github.com/nextcloud/spreed/issues/13077)
- Handle OS theme change without page reload
  [#10774](https://github.com/nextcloud/spreed/issues/10774)
- Various design fixes

## 20.0.0-rc.3 – 2024-08-22
### Changed
- Update several dependencies
- Allow editing "Note to self" messages forever
  [#13083](https://github.com/nextcloud/spreed/issues/13083)
  [#13089](https://github.com/nextcloud/spreed/issues/13089)
- Add blurhash to files so "previews" can be shown while loading
  [#13058](https://github.com/nextcloud/spreed/issues/13058)
  [#13075](https://github.com/nextcloud/spreed/issues/13075)

### Fixed
- fix(federation): Fix propagating permissions, recording consent, permissions and more
- Don't break when joining an open conversation
  [#13090](https://github.com/nextcloud/spreed/issues/13090)
- Fix signaling server check for Desktop Client so that Nextcloud 29 does not need the newest version
  [#13094](https://github.com/nextcloud/spreed/issues/13094)
- fix(settings): Hide unused settings in (former) one-to-one conversations
  [#13046](https://github.com/nextcloud/spreed/issues/13046)
- fix(sidebar): Fix row-style of attachments
  [#13044](https://github.com/nextcloud/spreed/issues/13044)
- fix(federation): fix system message when removed user has same userId as the moderator
  [#13055](https://github.com/nextcloud/spreed/issues/13055)
- fix(federation): correctly check list of allowed groups when federation is limited
  [#13067](https://github.com/nextcloud/spreed/issues/13067)

## 20.0.0-rc.2 – 2024-08-16
### Fixed
- Adjust conversation list density
  [#13013](https://github.com/nextcloud/spreed/issues/13013)

## 20.0.0-rc.1 – 2024-08-15
### Added
- Show upcoming event information (up to 1 month) in conversation top bar
  [#12984](https://github.com/nextcloud/spreed/issues/12984)

### Fixed
- Show user status messages of other local users in a federated conversation
  [#12982](https://github.com/nextcloud/spreed/issues/12982)
- Don't trigger notifications for system messages in federated conversations
  [#12985](https://github.com/nextcloud/spreed/issues/12985)
- Add a quick option to log-in when visiting a public conversation as a guest
  [#12988](https://github.com/nextcloud/spreed/issues/12988)
- Save displayname immediately when inviting a federated account
  [#12954](https://github.com/nextcloud/spreed/issues/12954)
- Show error messages when uploading a file failed
  [#12919](https://github.com/nextcloud/spreed/issues/12919)
- Add a hint how to add federated accounts
  [#12916](https://github.com/nextcloud/spreed/issues/12916)
- Retain order of attachments when uploading multiple
  [#12904](https://github.com/nextcloud/spreed/issues/12904)
- Don't allow to enable bots in former one-to-one conversations
  [#12893](https://github.com/nextcloud/spreed/issues/12893)
- Hide ban option for federated accounts as they are not supported for now
  [#12980](https://github.com/nextcloud/spreed/issues/12980)
- Various design fixes

## 20.0.0-beta.3 – 2024-08-06
### Fixed
- Disallow setting message expiration in former one-to-one conversations
  [#12882](https://github.com/nextcloud/spreed/issues/12882)
- Show avatar thumbnail for former one-to-one conversations
  [#12886](https://github.com/nextcloud/spreed/issues/12886)
- Hide Ban section in "Note to self" conversation settings
  [#12889](https://github.com/nextcloud/spreed/pull/12889)
- Disallow banned user to be added to the conversation
  [#12793](https://github.com/nextcloud/spreed/issues/12793)
- Disable call button in a federated chat when its settings was changed
  [#12864](https://github.com/nextcloud/spreed/issues/12864)
- More UI changes according to design review
  [#12800](https://github.com/nextcloud/spreed/issues/12800)

## 20.0.0-beta.2 – 2024-08-01
### Fixed
- fix(calls): Add notifications for federated calls
  [#12845](https://github.com/nextcloud/spreed/issues/12845)
  [#12856](https://github.com/nextcloud/spreed/issues/12856)
  [#12874](https://github.com/nextcloud/spreed/issues/12874)
- fix(calls): Fix broken avatar of remote users in calls
  [#12863](https://github.com/nextcloud/spreed/issues/12863)
- fix(videoverification): Fix design
  [#12853](https://github.com/nextcloud/spreed/issues/12853)
- fix(chat): Prevent leave call dialog when canceling quoting
  [#12824](https://github.com/nextcloud/spreed/issues/12824)
- fix(UI): More design adjustments for nextcloud/vue changes
  [#12810](https://github.com/nextcloud/spreed/issues/12810)

## 20.0.0-beta.1 – 2024-07-26
### Added
- Banning users and guests
  [#12291](https://github.com/nextcloud/spreed/issues/12291)
- Allow to prevent "@all" mentions for non-moderators
  [#9074](https://github.com/nextcloud/spreed/issues/9074)
- Add button to show smart picker
  [#12250](https://github.com/nextcloud/spreed/issues/12250)
- Dispatch events when enabling or disabling bots
  [#12551](https://github.com/nextcloud/spreed/pull/12551)
- Preview: Federated calls
  [#11232](https://github.com/nextcloud/spreed/issues/11232)

### Changed
- Requires Nextcloud 30
- External signaling requires [federation support](https://github.com/strukturag/nextcloud-spreed-signaling/pull/776)
- API performance improvements
- Dynamic order of tiles in a call
  [#11393](https://github.com/nextcloud/spreed/issues/11393)
- Automatically lower raised hand when participant speaks
  [#12399](https://github.com/nextcloud/spreed/issues/12399)
- Show confirmation dialog when removing a participant
  [#12543](https://github.com/nextcloud/spreed/pull/12543)
- Show description when listing open conversations
  [#12209](https://github.com/nextcloud/spreed/issues/12209)
- Outline the participant count when trying to mention everyone
  [#12782](https://github.com/nextcloud/spreed/pull/12782)
- Show out-of-office replacement in the out-of-office message
  [#12510](https://github.com/nextcloud/spreed/pull/12510)

### Known issues
- Design: not fully adjusted to changes in Nextcloud 30
- Federated calls: broken participant avatars and missing call notifications

