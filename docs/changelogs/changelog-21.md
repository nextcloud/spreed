<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 21.1.9 – 2026-02-12
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(mobile-clients): Fix error message for Talk iOS when end-to-end encryption for calls is enabled
  [#17002](https://github.com/nextcloud/spreed/pull/17002)
- fix(breakout-rooms): Fix managing existing breakout rooms in conversation settings
  [#16967](https://github.com/nextcloud/spreed/pull/16967)
- fix(chat): Fix system messages with email-invited guests
  [#16869](https://github.com/nextcloud/spreed/pull/16869)
- fix(federation): Abort requests early when federation is disabled
  [#16962](https://github.com/nextcloud/spreed/pull/16962)
- fix(signaling): Unify request validation for HPB, recording and other services
  [#17073](https://github.com/nextcloud/spreed/pull/17073)

## 21.1.8 – 2026-01-15
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Allow getting a single message
  [#16732](https://github.com/nextcloud/spreed/pull/16732)
- fix(call): Allow selecting a media device after an error occurred
  [#16700](https://github.com/nextcloud/spreed/pull/16700)
- fix(call): Still block mobile clients when call end-to-end encryption is enabled
  [#16673](https://github.com/nextcloud/spreed/pull/16673)

## 21.1.7 – 2025-12-15
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Correctly expire shared items in sidebar
  [#16573](https://github.com/nextcloud/spreed/pull/16573)
- fix(call): Show video streams of other attendees for guests
  [#16546](https://github.com/nextcloud/spreed/pull/16546)

## 21.1.6 – 2025-12-11
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(call): Fix low frame rate in the grid layout
  [#16389](https://github.com/nextcloud/spreed/pull/16389)
- fix(call): Keep media disabled when reassigning permissions
  [#16521](https://github.com/nextcloud/spreed/pull/16521)
- fix(chat): Fix resetting the cursor to the end of the message when editing
  [#16300](https://github.com/nextcloud/spreed/pull/16300)
- fix(chat): Don't show typing indicator when editing a message
  [#16140](https://github.com/nextcloud/spreed/pull/16140)
- fix(search): Fix short date style in message search
  [#16232](https://github.com/nextcloud/spreed/pull/16232)
- fix(settings): Hide message expiration when not supported by the server
  [#16317](https://github.com/nextcloud/spreed/pull/16317)
- fix(settings): Don't transfer ownership of sample conversations
  [#16177](https://github.com/nextcloud/spreed/pull/16177)
- fix(settings): Fix a missing check when configuring Matterbridge
  [#16523](https://github.com/nextcloud/spreed/pull/16523)

## 21.1.5 – 2025-09-18
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Validate file name when creating from template instead of failing afterwards
  [#15919](https://github.com/nextcloud/spreed/issues/15919)
- fix(conversation): Fix spacing between items in conversation list when forwarding
  [#15812](https://github.com/nextcloud/spreed/issues/15812)
- fix(conversation): Fix joining and leaving conversations when errors occurred
  [#15797](https://github.com/nextcloud/spreed/issues/15797)

## 21.1.4 – 2025-08-28
### Added
- feat(sip): Allow to send the direct dial-in number of users on out-going calls
  [#15701](https://github.com/nextcloud/spreed/issues/15701)
- feat(settings): Add a config for the unread message threshold for the AI summary
  [#15734](https://github.com/nextcloud/spreed/issues/15734)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(dashboard): Fix events without an end date
  [#15732](https://github.com/nextcloud/spreed/issues/15732)
- fix(chat): Suggest mentioning yourself, it's useful with bots and todos
  [#15656](https://github.com/nextcloud/spreed/issues/15656)
- fix(chat): Fix search interaction when scrolling away and clicking on a result again
  [#15706](https://github.com/nextcloud/spreed/issues/15706)
- fix(chat): Fix support for at-all in captions when sharing a file
  [#15744](https://github.com/nextcloud/spreed/issues/15744)

## 21.1.3 – 2025-08-06
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(meetings): invitation emails for Talk created events not sent
  [#15634](https://github.com/nextcloud/spreed/issues/15634)
- fix(dashboard): don't show cancelled events in upcoming events
  [#15544](https://github.com/nextcloud/spreed/issues/15544)
- fix(dashboard): prevent accidentally forwarding guests to dashboard via Esc key
  [#15560](https://github.com/nextcloud/spreed/issues/15560)
- fix(chat): close Download menu after interaction
  [#15587](https://github.com/nextcloud/spreed/issues/15587)
- fix(chat): empty content for guests after chat is cleared
  [#15550](https://github.com/nextcloud/spreed/issues/15550)
- fix(chat): fix search result scroll when clicking twice
  [#15612](https://github.com/nextcloud/spreed/issues/15612)

## 21.1.2 – 2025-07-17
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(federation): Make sure some capabilities are correctly used from the remote host
  [#15507](https://github.com/nextcloud/spreed/issues/15507)
- fix(settings): Allow changing call notification level for federated conversations
  [#15516](https://github.com/nextcloud/spreed/issues/15516)
- fix(settings): Fix class name of background job checking certificates
  [#15464](https://github.com/nextcloud/spreed/issues/15464)
- fix(settings): Fix false-negative error being shown on certificate check if another check failed an SSL call
  [#15525](https://github.com/nextcloud/spreed/issues/15525)
- fix(polls): Fix deleting poll drafts
  [#15536](https://github.com/nextcloud/spreed/issues/15536)

## 21.1.1 – 2025-07-03
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Always render code blocks left-to-right
  [#15364](https://github.com/nextcloud/spreed/issues/15364)
- fix(call): Allow end-to-end encrypted calls in integrations like files and sharing
  [#15415](https://github.com/nextcloud/spreed/issues/15415)
- fix(sip): Add log message when direct dial-in had no match to help configuring phone numbers
  [#15358](https://github.com/nextcloud/spreed/issues/15358)
- fix(federation): Fix sending invites from conversations without an owner
  [#15354](https://github.com/nextcloud/spreed/issues/15354)
- fix(settings): Show errors when the websocket connection could not be opened
  [#15368](https://github.com/nextcloud/spreed/issues/15368)
- fix(settings): Validate that signaling private and public key match
  [#15357](https://github.com/nextcloud/spreed/issues/15357)
- fix(settings): Do not break when settings has an incomplete server URL
  [#15453](https://github.com/nextcloud/spreed/issues/15453)

## 21.1.0 – 2025-06-05
### Added
- 📅 Improve handling of event conversations: filtered until close to the meeting, default expiration, tracking of event name and description
- 👥 Allow adding participants to one-to-one calls creating a new conversation
- 🍱 Add a Talk Dashboard
- 📇 Enhance right sidebar in one-to-one conversations with information about the participant
- 📲 Allow SIP direct dial-in to start a new conversation with an existing Nextcloud account
- 🖌️ Allow to provide custom images for virtual backgrounds for branding or corporate appearance
- 🔈 Allow selecting the output device in the media settings
- ☎️ Administration setting to enable SIP dial-in by default for new conversations
- 📅 Allow creating instant meetings
- 🔏 Add sensitive conversations which don't show chat messages in sublines and notifications
- 📲 Add option to mark a conversation as important to still receive notifications during "Do not disturb"

### Changed
- Update translations
- Update dependencies

## 21.1.0-rc.4 – 2025-05-29
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(dashboard): Add attachment icon and link to the events on the dashboard
  [#15238](https://github.com/nextcloud/spreed/issues/15238)
- fix(meetings): Don't show duplicates for the same event in the upcoming meetings
  [#15252](https://github.com/nextcloud/spreed/issues/15252)
- fix(meetings): Lock conversation when meeting is cancelled
  [#15253](https://github.com/nextcloud/spreed/issues/15253)
- fix(sip): Allow guests to use direct-dial-in even when starting calls is restricted
  [#15234](https://github.com/nextcloud/spreed/issues/15234)

## 21.1.0-rc.3 – 2025-05-22
### Added
- 📲 🙊 Add option to mark a conversation as sensitive or important
  [#15175](https://github.com/nextcloud/spreed/issues/15175)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Fix missing push notifications for chat messages in important conversations
  [#15180](https://github.com/nextcloud/spreed/issues/15180)
- fix(dashboard): Make sure all events of the upcoming week are considered
  [#15177](https://github.com/nextcloud/spreed/issues/15177)
- fix(conversations): Make the "Local time" reactive in one-to-one conversations
  [#15130](https://github.com/nextcloud/spreed/issues/15130)
- fix(conversations): Only show events in one-to-one conversations when upcoming recurrences contain both participants
  [#15163](https://github.com/nextcloud/spreed/issues/15163)
- fix(conversations): Fix background job for conversation retention
  [#15185](https://github.com/nextcloud/spreed/issues/15185)
- fix(calls): Don't break the session when the user accidentally navigated to the same conversation
  [#15170](https://github.com/nextcloud/spreed/issues/15170)
- fix(calls): Make output device selection work on Safari
  [#15142](https://github.com/nextcloud/spreed/issues/15142)

## 21.1.0-rc.2 – 2025-05-15
### Added
- 🍱 Add a Talk Dashboard
  [#15094](https://github.com/nextcloud/spreed/issues/15094)
- 📅 Allow creating instant meetings
  [#15073](https://github.com/nextcloud/spreed/issues/15073)
- 🤝 Show mutual events in the sidebar of one-to-one
  [#15097](https://github.com/nextcloud/spreed/issues/15097)
- 🙊 Add sensitive conversations which don't show a chat messages in sublines and notifications
  [#15098](https://github.com/nextcloud/spreed/issues/15098)

### Changed
- Update translations
- Update dependencies

### Fixed
- Send call notification to newly added participants
  [#15090](https://github.com/nextcloud/spreed/issues/15090)
- Fix issues with calendar event integration
  [#15078](https://github.com/nextcloud/spreed/issues/15078)
  [#15080](https://github.com/nextcloud/spreed/issues/15080)

## 21.1.0-rc.1 – 2025-05-09
### Added
- 📅 Improve handling of event conversations: filtered until close to the meeting, default expiration, tracking of event name and description
  [#14401](https://github.com/nextcloud/spreed/issues/14401)
- 👥 Allow adding participants to one-to-one calls creating a new conversation
  [#14398](https://github.com/nextcloud/spreed/issues/14398)
- 📇 Enhance right sidebar in one-to-one conversations with information about the participant
  [#14411](https://github.com/nextcloud/spreed/issues/14411)
- 📲 Allow SIP direct dial-in to start a new conversation with an existing Nextcloud account
  [#14992](https://github.com/nextcloud/spreed/issues/14992)
- 🖌️ Allow to provide custom images for virtual backgrounds for branding or corporate appearance
  [#14987](https://github.com/nextcloud/spreed/issues/14987)
- 🔈 Allow selecting the output device in the media settings
  [#15037](https://github.com/nextcloud/spreed/issues/15037)
- ☎️ Administration setting to allow to enable SIP dial-in by default for new conversations
  [#14940](https://github.com/nextcloud/spreed/issues/14940)

### Changed
- Update translations
- Update dependencies

## 21.0.4 – 2025-04-28
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(schedule-meeting): Make sure the other user is in one-to-one when scheduling
  [#14973](https://github.com/nextcloud/spreed/issues/14973)
  [#14967](https://github.com/nextcloud/spreed/issues/14967)
- fix(schedule-meeting): Improve dialog when scheduling in one-to-one
  [#14923](https://github.com/nextcloud/spreed/issues/14923)
- fix(schedule-meeting): Hide schedule meeting from former one-to-one
  [#14923](https://github.com/nextcloud/spreed/issues/14923)
- fix(performance): Fix unnecessary user_status requests from avatar component
  [#14932](https://github.com/nextcloud/spreed/issues/14932)

## 21.0.3 – 2025-04-17
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(avatar): Fix regression from library requesting too many status updates
  [#14894](https://github.com/nextcloud/spreed/issues/14894)
- fix(chat): Improve regex of todo-list handling also uppercase X
  [#14904](https://github.com/nextcloud/spreed/issues/14904)
- fix(federation): Use correct capability to show call-notification setting in federated conversations
  [#14907](https://github.com/nextcloud/spreed/issues/14907)

## 21.0.2 – 2025-04-10
### Changed
- Update translations
- Update dependencies

### Fixed
- fix: Improve performance of conversation list
  [#14811](https://github.com/nextcloud/spreed/issues/14811)
  [#14779](https://github.com/nextcloud/spreed/issues/14779)
  [#14777](https://github.com/nextcloud/spreed/issues/14777)
  [#14775](https://github.com/nextcloud/spreed/issues/14775)
  [#14774](https://github.com/nextcloud/spreed/issues/14774)
  [#14831](https://github.com/nextcloud/spreed/issues/14831)
- fix: Improve performance when rendering system messages
  [#14817](https://github.com/nextcloud/spreed/issues/14817)
- fix: Improve performance when searching for conversations
  [#14734](https://github.com/nextcloud/spreed/issues/14734)
- fix(chat): Fix missing reactions on own messages while posting
  [#14695](https://github.com/nextcloud/spreed/pull/14695)
- fix(guests): Allow guests to reload the page without re-entering the password
  [#14786](https://github.com/nextcloud/spreed/issues/14786)
- fix(federation): Fix calls when federated server receive messages in wrong order
  [#14770](https://github.com/nextcloud/spreed/pull/14770)
- fix(calls): Fix call after resuming connection
  [#14737](https://github.com/nextcloud/spreed/pull/14737)
- fix(calls): Fix wrongly showing "Missed call" in one-to-one conversations
  [#14833](https://github.com/nextcloud/spreed/pull/14833)
- fix(calls): Fix videos in the last row being cut off
  [#14692](https://github.com/nextcloud/spreed/pull/14692)
- fix(calls): Prevent screen from turning off during calls
  [#14733](https://github.com/nextcloud/spreed/pull/14733)
- fix(settings): Fix initial state of end-to-end-encrypted calls setting
  [#14693](https://github.com/nextcloud/spreed/pull/14693)
- fix(events): Automatically confirm the calendar event for the organizer
  [#14762](https://github.com/nextcloud/spreed/pull/14762)
- fix(workflows): Adjust workflow registration to new mechanism
  [#14823](https://github.com/nextcloud/spreed/pull/14823)
- fix(polls): Hide intermediate results from anonymous polls
  [#14724](https://github.com/nextcloud/spreed/issues/14724)

## 21.0.1 – 2025-03-12
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(calls): Improve call related system messages in one-to-one conversations
  [#14495](https://github.com/nextcloud/spreed/issues/14495)
- fix(search): Include caption messages in search results
  [#14553](https://github.com/nextcloud/spreed/issues/14553)
- fix(chat): Show loading spinner when requesting AI chat summary
  [#14625](https://github.com/nextcloud/spreed/issues/14625)
- fix(chat): Correctly start loading the chat when the lobby is removed
  [#14517](https://github.com/nextcloud/spreed/issues/14517)
- fix(dashboard): Hide lobbied conversations from the dashboard
  [#14612](https://github.com/nextcloud/spreed/issues/14612)
- fix(federation): Fix broken participant avatar when federated instance is down
  [#14573](https://github.com/nextcloud/spreed/issues/14573)
- fix(conversation): Fix participant list change when the menu for a participant is open
  [#14564](https://github.com/nextcloud/spreed/issues/14564)
- fix(calls): Fix guest displayname when exporting call participants
  [#14630](https://github.com/nextcloud/spreed/issues/14630)
- fix(reminder): Log when generating a reminder failed
  [#14618](https://github.com/nextcloud/spreed/issues/14618)

## 21.0.0 – 2025-02-25
### Added
- feat(meetings): Schedule a meeting directly from within the conversation
- feat(chat): Support mentioning teams in the chat
- feat(chat): Add message search to the right sidebar
- feat(bots): Allow bots to get invoked for reactions
- feat(bots): Let bots know the original message in case a message was a reply
- feat(bots): Allow event based bots that don't require HTTP requests
- feat(calls): Add end-to-end encryption for calls with the High-performance backend
- feat(calls): Allow to zoom and pan screenshares in a call
- feat(conversations): Add sample conversation mechanism

### Changed
- Update translations
- Update dependencies
- Require Nextcloud 31 / Hub 10

### Fixed
- fix(UI): Fix various issues for right-to-left languages

## 21.0.0-rc.5 – 2025-02-21
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Mark mentions of teams you are a part of primary
  [#14455](https://github.com/nextcloud/spreed/issues/14455)
- fix(federation): Fix "remote server was updated" shown too frequently
  [#14389](https://github.com/nextcloud/spreed/issues/14389)

## 21.0.0-rc.4 – 2025-02-13
### Added
- feat(bots): Allow bots to get invoked for reactions
  [#14336](https://github.com/nextcloud/spreed/issues/14336)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(bots): Allow users to edit messages of bots in one-to-one conversations
  [#14353](https://github.com/nextcloud/spreed/issues/14353)
- fix(conversation): Correctly update team names after being edited
  [#14358](https://github.com/nextcloud/spreed/issues/14358)
- fix(conversation): Don't suggest teams that are already added to the conversation
  [#14349](https://github.com/nextcloud/spreed/issues/14349)
- fix(meetings): Fix calendar integration for Desktop client
  [#14379](https://github.com/nextcloud/spreed/issues/14379)
- fix(calls): Fix issues with presenter overlay
  [#14330](https://github.com/nextcloud/spreed/issues/14330)
  [#14371](https://github.com/nextcloud/spreed/issues/14371)

## 21.0.0-rc.3 – 2025-02-07
### Added
- feat(bots): Let bots know when a message was a reply
  [#14310](https://github.com/nextcloud/spreed/issues/14310)

### Changed
- Update translations
- Update dependencies
- fix(calls): Adjust double-click behaviour when zooming screenshares
  [#14284](https://github.com/nextcloud/spreed/issues/14284)

### Fixed
- fix(chat): Fix double scroll bar
  [#14265](https://github.com/nextcloud/spreed/issues/14265)
- fix(chat): Keep chat position at the bottom when the chat list height expends
  [#14268](https://github.com/nextcloud/spreed/issues/14268)
- fix(chat): Fix missing "Copy code" in some cases
  [#14308](https://github.com/nextcloud/spreed/issues/14308)
- fix(archive): Hide archived conversations from dashboard unless mentioned
  [#14299](https://github.com/nextcloud/spreed/issues/14299)
- fix(chat): Add mention-id to simplify editing messages with mentions
  [#14311](https://github.com/nextcloud/spreed/issues/14311)

## 21.0.0-rc.2 – 2025-01-30
### Added
- feat(chat): Support mentioning teams
  [#14259](https://github.com/nextcloud/spreed/issues/14259)
  [#14260](https://github.com/nextcloud/spreed/issues/14260)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(UI): Fix multiple right-to-left issues
  [#14235](https://github.com/nextcloud/spreed/issues/14235)
  [#14256](https://github.com/nextcloud/spreed/issues/14256)
- fix(meetings): Allow creating conversations when password policy app is enabled
  [#14227](https://github.com/nextcloud/spreed/issues/14227)
- fix(calls): Fix multiple false-positives when showing the connection warning
  [#14252](https://github.com/nextcloud/spreed/issues/14252)
- fix(bots): Fix installing PHP event bots via an event
  [#14231](https://github.com/nextcloud/spreed/issues/14231)

## 21.0.0-rc.1 – 2025-01-23
### Added
- feat(calls): Allow to zoom and pan screenshares in a call
  [#14028](https://github.com/nextcloud/spreed/issues/14028)
- feat(bots): Allow event based bots that don't require HTTP requests
  [#14160](https://github.com/nextcloud/spreed/issues/14160)

### Changed
- Update translations
- Update dependencies

### Fixed
- docs: Add quick install documentation for the High-performance backend
  [#14165](https://github.com/nextcloud/spreed/issues/14165)

## 21.0.0-beta.2 – 2025-01-17
### Added
- feat(search): Add message search to the right sidebar
  [#14125](https://github.com/nextcloud/spreed/issues/14125)
- feat(conversations): Add sample conversation mechanism
  [#14124](https://github.com/nextcloud/spreed/issues/14124)
- feat(calls): Add end-to-end encryption for calls with the High-performance backend
  [#14005](https://github.com/nextcloud/spreed/issues/14005)

### Changed
- Update translations
- Update dependencies

### Fixed
- fix(federation): Fix federation from Nextcloud 30 to 31 with https
  [#14141](https://github.com/nextcloud/spreed/issues/14141)
- fix(conversations): Make compact list more compact and avatar bigger
  [#14118](https://github.com/nextcloud/spreed/issues/14118)
- fix(signaling): Test actual websocket connection in admin settings
  [#13973](https://github.com/nextcloud/spreed/issues/13973)
- fix(archive): Don't add asterix to title for unread messages in archived conversations
  [#14101](https://github.com/nextcloud/spreed/issues/14101)

## 21.0.0-beta.1 – 2025-01-10
### Added
- Schedule a meeting directly from within the conversation
  [#6292](https://github.com/nextcloud/spreed/issues/6292)

### Changed
- Update translations
- Update dependencies
- Require Nextcloud 31 / Hub 10

