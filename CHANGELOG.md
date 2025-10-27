<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 22.0.2 – 2025-10-27
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Fix relative date header showing `.042` after DST change
  [#16217](https://github.com/nextcloud/spreed/pull/16217)
- fix(participants): Correctly show guests as online and offline
  [#16218](https://github.com/nextcloud/spreed/pull/16218)

## 22.0.1 – 2025-10-24
### Added
- feat(bots): Add create command for inbound-only bots
  [#16111](https://github.com/nextcloud/spreed/pull/16111)

### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Don't trigger typing indicator when editing a message
  [#16139](https://github.com/nextcloud/spreed/pull/16139)
- fix(meeting): Fix issue not showing name of lobbied gusts to moderators
  [#16147](https://github.com/nextcloud/spreed/pull/16147)
- fix(meeting): Fix issue not showed 2 guests instead of 1
  [#16154](https://github.com/nextcloud/spreed/pull/16154)
- fix(threads): Fix notifications for federated users
  [#16064](https://github.com/nextcloud/spreed/pull/16064)
- fix(threads): Fix link to thread when clicking on notification while on Talk
  [#16056](https://github.com/nextcloud/spreed/pull/16056)
- fix(threads): Fix Oracle compatibility with threads
  [#16150](https://github.com/nextcloud/spreed/pull/16150)
- fix(conversations): Don't transfer sample conversations when transferring ownership
  [#16176](https://github.com/nextcloud/spreed/pull/16176)

## 22.0.0 – 2025-09-27
### Added
- Implement threads feature
- Implement "Busy" status in calls
- Implement live-transcriptions when the ExApp is available
- Update call interface design

### Changed
- Migrate app to Vue3
- Update dependencies
- Update translations
- Require Nextcloud 32 / Hub 25 Autumn

### Fixed
- Improve chat history browsing performance

## 22.0.0-rc.4 – 2025-09-25
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(threads): Support object shares, polls and file uploads
  [#15990](https://github.com/nextcloud/spreed/pull/15990)
  [#16001](https://github.com/nextcloud/spreed/pull/16001)
- fix(call): Release media devices and memory resources after the call end
  [#15992](https://github.com/nextcloud/spreed/issues/15992)
- fix(call): Adjust call recording layout
  [#16016](https://github.com/nextcloud/spreed/pull/16016)

## 22.0.0-rc.3 – 2025-09-18
### Fixed
- fix(call): Correctly sync media devices with OS sound settings
  [#15900](https://github.com/nextcloud/spreed/pull/15900)
  [#15972](https://github.com/nextcloud/spreed/pull/15972)
- fix(chat): Validate file name when creating from template instead of failing afterwards
  [#15920](https://github.com/nextcloud/spreed/issues/15920)
- fix(threads): Post shared files in the opened thread
  [#15938](https://github.com/nextcloud/spreed/pull/15938)
- fix(threads): Count shared files as thread replies and last message
  [#15946](https://github.com/nextcloud/spreed/pull/15946)

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

## 22.0.0-rc.2 – 2025-09-11
### Fixed
- fix(chat): send messages with reduced conversation data available
  [#15836](https://github.com/nextcloud/spreed/pull/15836)
- fix(call): restore participant videoframe after sharing a screen
  [#15853](https://github.com/nextcloud/spreed/pull/15853)
- fix(threads): navigate to thread messages from search results
  [#15858](https://github.com/nextcloud/spreed/pull/15858)
- fix(chat): forward thread messages to other conversations
  [#15866](https://github.com/nextcloud/spreed/pull/15866)

## 22.0.0-rc.1 – 2025-09-04
### Added
- feat(hosted-hpb): Support setting the TURN and STUN server if included
  [#15700](https://github.com/nextcloud/spreed/issues/15700)
- feat(assistant): Use new assistant theming
  [#15773](https://github.com/nextcloud/spreed/issues/15773)

### Changed
- Update dependencies
- Update translations

### Fixed
- fix(threads): Allow renaming a thread
  [#15779](https://github.com/nextcloud/spreed/issues/15779)
- fix(threads): Hide ignored threads from "Followed threads" list
  [#15781](https://github.com/nextcloud/spreed/issues/15781)
- fix(threads): Don't create threads without a title
  [#15790](https://github.com/nextcloud/spreed/issues/15790)
- fix(threads): Show temporary message as a thread already
  [#15800](https://github.com/nextcloud/spreed/issues/15800)
- fix(calls): Scroll chat to bottom when joining a call
  [#15774](https://github.com/nextcloud/spreed/issues/15774)

## 22.0.0-beta.2 – 2025-08-29
### Added
- feat(calls): Implement live-transcriptions when the ExApp is available
  [#15696](https://github.com/nextcloud/spreed/issues/15696)
- feat(dashboard): Improve empty content view of the dashboard sections
  [#15697](https://github.com/nextcloud/spreed/issues/15697)

### Changed
- Update dependencies
- Update translations

### Fixed
- fix(UI): Further design adjustments to outlined icons
- fix(chat): Many improvements for threads
  [#15616](https://github.com/nextcloud/spreed/issues/15616)
  [#15703](https://github.com/nextcloud/spreed/issues/15703)
  [#15704](https://github.com/nextcloud/spreed/issues/15704)
  [#15722](https://github.com/nextcloud/spreed/issues/15722)
  [#15735](https://github.com/nextcloud/spreed/issues/15735)

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

## 22.0.0-beta.1 – 2025-08-21
### Added
- Implement threads feature
  [#15313](https://github.com/nextcloud/spreed/issues/15313)
- Implement 'Busy' status in calls
  [#15465](https://github.com/nextcloud/spreed/issues/15465)
- Update call interface design
  [#15025](https://github.com/nextcloud/spreed/issues/15025)

### Changed
- Migrate app to Vue3
  [#9448](https://github.com/nextcloud/spreed/issues/9448)
- Update dependencies
- Update translations
- Require Nextcloud 32 / Hub 25 Autumn

### Fixed
- Improve chat history browsing performance
  [#6046](https://github.com/nextcloud/spreed/issues/6046)

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

## 18.0.14 – 2025-01-16
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(calls): Retain names of guests when they disconnect from the High-performance backend
  [#13982](https://github.com/nextcloud/spreed/issues/13982)
- fix(search): Add pagination support to the conversation search in unified search
  [#14032](https://github.com/nextcloud/spreed/issues/14032)
- fix(setupcheck): Check server times of Webserver nodes and High-performance backend to be in sync
  [#14014](https://github.com/nextcloud/spreed/issues/14014)
- fix(moderation): Allow promoting self-joined users
  [#14080](https://github.com/nextcloud/spreed/issues/14080)

## 21.0.0-beta.1 – 2025-01-10
### Added
- Schedule a meeting directly from within the conversation
  [#6292](https://github.com/nextcloud/spreed/issues/6292)

### Changed
- Update translations
- Update dependencies
- Require Nextcloud 31 / Hub 10

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

## 18.0.13 – 2024-11-07
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Fix layout for guests on public conversations
  [#13620](https://github.com/nextcloud/spreed/issues/13620)
- fix(UI): Improve handling of sidebar on mobile view
  [#12693](https://github.com/nextcloud/spreed/issues/12693)

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

## 18.0.12 – 2024-10-10
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(avatar): Fix missing translations
  [#13411](https://github.com/nextcloud/spreed/issues/13411)
- fix(chat): Expire message cache when deleting the last message
  [#13391](https://github.com/nextcloud/spreed/issues/13391)
- fix(call): Correctly ignore media offers from users without permissions when internal signaling is used
  [#13493](https://github.com/nextcloud/spreed/issues/13493)
- fix(call): Fix missing call sounds in Safari when tab is moved to the background
  [#13351](https://github.com/nextcloud/spreed/issues/13351)
- fix(avatar): Don't overwrite user avatar when selecting a square for a conversation
  [#13287](https://github.com/nextcloud/spreed/issues/13287)

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

## 18.0.11 – 2024-08-22
### Changed
- Update several dependencies

### Fixed
- fix(settings): hide secrets in password fields
  [#12843](https://github.com/nextcloud/spreed/issues/12843)
- fix(conversation): Fix adding and removing permissions
  [#13080](https://github.com/nextcloud/spreed/issues/13080)
- fix(session): Fix generating session id again if duplicated
  [#12744](https://github.com/nextcloud/spreed/issues/12744)

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

## 18.0.10 – 2024-07-11
### Fixed
- fix(sharing): Fix share detection within object stores
  [#12628](https://github.com/nextcloud/spreed/pull/12628)

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

## 18.0.9 – 2024-06-27
### Fixed
- fix(bots): Fix bots with self-signed certificates
  [#12470](https://github.com/nextcloud/spreed/pull/12470)
- fix(attachments): Fix creating new files from templates
  [#12487](https://github.com/nextcloud/spreed/pull/12487)
- fix(shareIntegration): Fix handle to close and open the right sidebar on publish share links
  [#12494](https://github.com/nextcloud/spreed/pull/12494)
- fix(bots): Show error messages during bots setup
  [#12573](https://github.com/nextcloud/spreed/pull/12573)
- fix(chat): Adjust toast message on message deletion
  [#12588](https://github.com/nextcloud/spreed/pull/12588)

## 17.1.10 – 2024-06-27
### Fixed
- fix(bots): Fix bots with self-signed certificates
  [#12469](https://github.com/nextcloud/spreed/pull/12469)
- fix(shareIntegration): Fix handle to close and open the right sidebar on publish share links
  [#12495](https://github.com/nextcloud/spreed/pull/12495)

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

## 18.0.8 – 2024-05-23
### Fixed
- fix(polls): Remove actor info from system message
  [#12343](https://github.com/nextcloud/spreed/pull/12343)
- fix(recording): Stop broken recording backend
  [#12402](https://github.com/nextcloud/spreed/pull/12402)

## 17.1.9 – 2024-05-23
### Fixed
- fix(polls): Remove actor info from system message
  [#12342](https://github.com/nextcloud/spreed/pull/12342)
- fix(recording): Stop broken recording backend
  [#12401](https://github.com/nextcloud/spreed/pull/12401)

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

## 18.0.7 – 2024-04-12
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(conversation): Fix error when adding participants while creating a conversation
  [#12057](https://github.com/nextcloud/spreed/issues/12057)
- fix(conversation): Fix missing icon in conversation settings for file conversations
  [#12051](https://github.com/nextcloud/spreed/issues/12051)

## 17.1.8 – 2024-04-12
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(conversation): Fix error when adding participants while creating a conversation
  [#12059](https://github.com/nextcloud/spreed/issues/12059)
- fix(conversation): Fix missing icon in conversation settings for file conversations
  [#12052](https://github.com/nextcloud/spreed/issues/12052)

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

## 18.0.6 – 2024-04-04
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(FilePicker): Provide correct container for FilePicker
  [#11940](https://github.com/nextcloud/spreed/issues/11940)
- fix(flow): Fix flow notifications in note-to-self and on own actions
  [#11919](https://github.com/nextcloud/spreed/issues/11919)
- fix(call): Keep the talk time information when changing active tab
  [#11797](https://github.com/nextcloud/spreed/issues/11797)

## 17.1.7 – 2024-04-04
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(conversation): skip unread marker increasing from notification
  [#11735](https://github.com/nextcloud/spreed/issues/11735)
- fix(modal): mount nested modals inside global modals
  [#11891](https://github.com/nextcloud/spreed/issues/11891)

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

## 18.0.5 – 2024-03-08
### Changed
- Update translations

### Fixed
- fix(call): Fix missing screenshare button after stopping a screenshare
  [#11721](https://github.com/nextcloud/spreed/issues/11721)
- fix(call): Correctly focus the screenshare after selecting in the grid view
  [#11755](https://github.com/nextcloud/spreed/issues/11755)
- fix(chat): Fix jumping unread counter when entering a conversation after receiving a notification
  [#11736](https://github.com/nextcloud/spreed/issues/11736)

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

## 18.0.4 – 2024-02-29
### Added
- feat(desktop): Allow using the avatar menu in the desktop client
  [#11679](https://github.com/nextcloud/spreed/issues/11679)

### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(webrtc): Ignore label of data channel when processing received messages for Janus 1.x compatibility
  [#11667](https://github.com/nextcloud/spreed/issues/11667)
- fix(notifications): Fix notification action label length with utf8 languages
  [#11621](https://github.com/nextcloud/spreed/issues/11621)
- fix(chat): Fix forwarding messages from conversations in the right sidebar
  [#11606](https://github.com/nextcloud/spreed/issues/11606)
- fix(search): Hide search providers when not allowed to use Talk
  [#11623](https://github.com/nextcloud/spreed/issues/11623)
- fix(UI): Fix nesting of modals
  [#11580](https://github.com/nextcloud/spreed/issues/11580)
- fix(participants): Add a key to the elements force re-rendering on changes
  [#11558](https://github.com/nextcloud/spreed/issues/11558)

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

## 18.0.3 – 2024-01-31
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(chat): Fix scrolling behaviour when loading older messages
  [#11481](https://github.com/nextcloud/spreed/issues/11481)
- fix(chat): Fix showing mention and emoji suggestions when writing a caption
  [#11458](https://github.com/nextcloud/spreed/issues/11458)
- fix(chat): Show mention chips when inserting a suggested mention
  [#11493](https://github.com/nextcloud/spreed/issues/11493)

## 18.0.2 – 2024-01-25
### Fixed
- fix(calls): Device preview not visible when editing, uploading or viewing a file
  [#11222](https://github.com/nextcloud/spreed/issues/11222)
- fix(conversation): Make description input multi line when creating a conversation
  [#11376](https://github.com/nextcloud/spreed/issues/11376)
- fix(bot): Don't allow empty chat messages from bots
  [#11353](https://github.com/nextcloud/spreed/issues/11353)
- fix(breakout): Stop breakout rooms when they are started while deleting them
  [#11409](https://github.com/nextcloud/spreed/issues/11409)
- fix(attachments): Allow to retry failed uploads
  [#11256](https://github.com/nextcloud/spreed/issues/11256)
- fix(attachments): Fix uploading from local device
  [#11331](https://github.com/nextcloud/spreed/issues/11331)
- fix(attachments): Don't allow selecting shared folders as attachment folder
  [#11427](https://github.com/nextcloud/spreed/issues/11427)

## 17.1.5 – 2024-01-25
### Fixed
- fix(attachments): Don't allow selecting shared folders as attachment folder
  [#11430](https://github.com/nextcloud/spreed/issues/11430)

## 16.0.10 – 2024-01-25
### Fixed
- fix(attachments): Don't allow selecting shared folders as attachment folder
  [#11431](https://github.com/nextcloud/spreed/issues/11431)

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

## 16.0.9 – 2023-12-19
### Fixed
- fix(occ): Fix verification of STUN server details
  [#11195](https://github.com/nextcloud/spreed/issues/11195)
- fix(hosted-hpb): Correctly handle API response codes of hosted High-performance backend when the account expired
  [#11045](https://github.com/nextcloud/spreed/issues/11045)

## 18.0.1 – 2023-12-15
### Changed
- Update translations

### Fixed
- fix(shares): Fix notifications for captions with mentions or as a reply
  [#11242](https://github.com/nextcloud/spreed/issues/11242)
- fix(shares): Fix replying to message with attachments
  [#11242](https://github.com/nextcloud/spreed/issues/11242)
- fix(shares): Reserve space for file previews while loading
  [#11196](https://github.com/nextcloud/spreed/issues/11196)
- fix(chat): Don't trim the quote when it is not an image share with caption
  [#11237](https://github.com/nextcloud/spreed/issues/11237)
- fix(call): Reset "Start recording" checkbox on "Media settings" close
  [#11227](https://github.com/nextcloud/spreed/issues/11227)
- fix(call): Fix uploading files as image for the call background
  [#11214](https://github.com/nextcloud/spreed/issues/11214)
- fix(notifications): Fix the order of event listeners to improve responsiveness when starting calls
  [#11238](https://github.com/nextcloud/spreed/issues/11238)

## 18.0.0 – 2023-12-12
### Added
- 🗒️ Note to self
  [#2196](https://github.com/nextcloud/spreed/issues/2196)
- 🎙️ Show speaker while screensharing
  [#4478](https://github.com/nextcloud/spreed/issues/4478)
- 🏷️ Add a caption to your file before sharing it into the chat
  [#5354](https://github.com/nextcloud/spreed/issues/5354)
- 👤 Ask Guest to enter a name when connecting
  [#855](https://github.com/nextcloud/spreed/issues/855)
- 🤩 Animated call reactions
  [#10561](https://github.com/nextcloud/spreed/issues/10561)
- 🖋️ Optionally require consent before joining a recorded call
  [#10348](https://github.com/nextcloud/spreed/issues/10348)
- 📲 Allow calling phone numbers from within Talk using SIP dialout
  [#10346](https://github.com/nextcloud/spreed/issues/10346)
- 🔎 Add support for "person" and "modified" filter options of the new search
  [#10909](https://github.com/nextcloud/spreed/issues/10909)
- 🌴 Show the "Out of office" message in one-to-one conversations
  [#11049](https://github.com/nextcloud/spreed/issues/11049)

### Changed
- Requires Nextcloud 28
- Update translations
- Update several dependencies
- Require compatible clients (Talk Android 18.0.0 or later, Talk iOS 18.0.0 or later, Talk Desktop 0.16.0 or later) when recording consent is enabled
  [#10969](https://github.com/nextcloud/spreed/issues/10969)

## 18.0.0-rc.3 – 2023-12-07
### Added
- feat(call): Add screensharing support to the viewer-overlay
  [#11033](https://github.com/nextcloud/spreed/issues/11033)
- feat(conversations): Always show create conversation button
  [#11104](https://github.com/nextcloud/spreed/issues/11104)
- fix(chat): Add metadata to file parameters on API level so clients can calculate aspect ratio of previews
  [#11131](https://github.com/nextcloud/spreed/issues/11131)

### Changed
- Update several dependencies
- Update translations

### Fixed
- fix(chat): Fix various cases of handling (in)active sessions while chatting and calling
  [#11125](https://github.com/nextcloud/spreed/issues/11125)
  [#11140](https://github.com/nextcloud/spreed/issues/11140)
- fix(chat): Only load current absence not future ones
  [#11124](https://github.com/nextcloud/spreed/issues/11124)
- fix(chat): Fix file share with caption quote reply
  [#11128](https://github.com/nextcloud/spreed/issues/11128)
- fix(poll): Reorganize component structure and hide "End poll" from first view
  [#11109](https://github.com/nextcloud/spreed/issues/11109)
- fix(call): Cancel scheduled participant request when requesting new one
  [#11097](https://github.com/nextcloud/spreed/issues/11097)
- fix(chat): Expand system messages group if visual unread marker is set on it
  [#11067](https://github.com/nextcloud/spreed/issues/11067)
- fix(chat): Handle silent sending and input paste correctly in media captions
  [#11123](https://github.com/nextcloud/spreed/issues/11123)
- fix(conversations): Remove label from talk search input
  [#11071](https://github.com/nextcloud/spreed/issues/11071)
- fix(video-verification): Remove unneeded settings from video-verification screen
  [#11138](https://github.com/nextcloud/spreed/issues/11138)

## 18.0.0-rc.2 – 2023-11-30
### Added
- feat(chat): Show the "Out of office" message in one-to-one conversations
  [#11049](https://github.com/nextcloud/spreed/issues/11049)

### Changed
- Update several dependencies
- Update translations

### Fixed
- fix(call): Try to fix Safari unmute after being muted for a longer time
  [#11032](https://github.com/nextcloud/spreed/issues/11032)
- fix(call): Reduce participant list update speed when you are not joining a call
  [#11047](https://github.com/nextcloud/spreed/issues/11047)
- fix(call): Remove O(n) queries when ending a call for everyone
  [#11020](https://github.com/nextcloud/spreed/issues/11020)
- fix(UI): Fix several missing headlines after nextcloud/vue update
  [#11031](https://github.com/nextcloud/spreed/issues/11031)
- fix(chat): Allow submitting the caption upload form without a file
  [#11013](https://github.com/nextcloud/spreed/issues/11013)
- fix(call): Fix undefined variable $participant when calling a conversation with lobby
  [#11027](https://github.com/nextcloud/spreed/issues/11027)
- fix(chat): Hide "Messages in current conversation" search filter when not in a chat
  [#11054](https://github.com/nextcloud/spreed/issues/11054)

## 18.0.0-rc.1 – 2023-11-23
### Changed
- Update several dependencies
- Improve documentation by adding magic strings and values to parameters
  [#10857](https://github.com/nextcloud/spreed/issues/10857)
- Require compatible clients when recording consent is enabled
  [#10969](https://github.com/nextcloud/spreed/issues/10969)
- Revert: Try to fix Safari unmute after being muted for a longer time
  [#10954](https://github.com/nextcloud/spreed/issues/10954)
- Move away from deprecated constants and functions
  [#10975](https://github.com/nextcloud/spreed/issues/10975)

### Fixed
- fix(settings): Remove non-working notification settings for guests
  [#10960](https://github.com/nextcloud/spreed/issues/10960)
- fix(settings): Fix option to request an HPB trial
  [#10962](https://github.com/nextcloud/spreed/issues/10962)
  [#10970](https://github.com/nextcloud/spreed/issues/10970)
- fix(chat): Fix sorting of system messages
  [#10963](https://github.com/nextcloud/spreed/issues/10963)
- fix(settings): Fix style in the admin settings after vue library update
  [#10984](https://github.com/nextcloud/spreed/issues/10984)

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

## 18.0.0-beta.3 – 2023-11-16
### Added
- Allow drag'n'drop of files onto the caption dialog
  [#10898](https://github.com/nextcloud/spreed/issues/10898)
- Add support for "person" and "modified" filter options of the global search
  [#10909](https://github.com/nextcloud/spreed/issues/10909)

### Changed
- Update several dependencies

### Fixed
- Fix Safari browser not receiving any stream in a call
  [#10912](https://github.com/nextcloud/spreed/issues/10912)
- Try to fix Safari unmute after being muted for a longer time
  [#10913](https://github.com/nextcloud/spreed/issues/10913)
- Mark notifications about read chat messages as resolved
  [#10889](https://github.com/nextcloud/spreed/issues/10889)
- Fix uploading files after multiple hours without a page reload
  [#10877](https://github.com/nextcloud/spreed/issues/10877)
- Don't throw an unhandled exception when mentioning `at-all` in "Note to self"
  [#10881](https://github.com/nextcloud/spreed/issues/10881)
- Fix SIP dialout not working after resolving license issue
  [#10914](https://github.com/nextcloud/spreed/issues/10914)
- Fix issues with the session active state
  [#10876](https://github.com/nextcloud/spreed/issues/10876)
- Clarify that "Note to self" and "Talk Updates" are system generated
  [#10884](https://github.com/nextcloud/spreed/issues/10884)

## 18.0.0-beta.2 – 2023-11-09
### Changed
- Replace various confirmation screens with the NcDialog component
  [#10812](https://github.com/nextcloud/spreed/issues/10812)
- Update several dependencies

### Fixed
- Fix mentions at the beginning of the text in captions
  [#10831](https://github.com/nextcloud/spreed/issues/10831)
- Fix not breaking the JSON response when removing the last reaction of a message
  [#10832](https://github.com/nextcloud/spreed/issues/10832)
- Remove previous style adjustments from left sidebar
  [#10818](https://github.com/nextcloud/spreed/issues/10818)
- Migrate the last set of event listeners to be service listeners

## 18.0.0-beta.1 – 2023-11-02
### Added
- 🗒️ Note to self
  [#2196](https://github.com/nextcloud/spreed/issues/2196)
- 🎙️ Show speaker while screensharing
  [#4478](https://github.com/nextcloud/spreed/issues/4478)
- 🏷️ Add a caption to your file before sharing it into the chat
  [#5354](https://github.com/nextcloud/spreed/issues/5354)
- 👤 Ask Guest to enter a name when connecting
  [#855](https://github.com/nextcloud/spreed/issues/855)
- 🤩 Animated call reactions
  [#10561](https://github.com/nextcloud/spreed/issues/10561)
- 🖋️ Optionally require consent before joining a recorded call
  [#10348](https://github.com/nextcloud/spreed/issues/10348)
- 📲 Allow calling phone numbers from within Talk using SIP dialout
  [#10346](https://github.com/nextcloud/spreed/issues/10346)

### Changed
- Requires Nextcloud 28
- Update several dependencies

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

## 15.0.8 – 2023-09-21
### Changed
- Update dependencies

### Fixed
- fix(call): Add an option to disable background blur in call
  [#10474](https://github.com/nextcloud/spreed/issues/10474)
- fix(page): Decouple the index controller from the executing method
  [#10548](https://github.com/nextcloud/spreed/issues/10548)
- Fix using signaling settings while being refetched
  [#10257](https://github.com/nextcloud/spreed/issues/10257)
- fix(chat): clean conversation history for participants in call
  [#10304](https://github.com/nextcloud/spreed/issues/10304)

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

## 15.0.7 – 2023-07-20
### Fixed
- Make conversation name and description selectable
  [#9784](https://github.com/nextcloud/spreed/issues/9784)

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

## 15.0.6 – 2023-05-25
### Changed
- Allow Brave browser without unsupported warning
  [#9167](https://github.com/nextcloud/spreed/issues/9167)
- Update dependencies

### Fixed
- Fix call summary when a user has a full numeric user ID
  [#9504](https://github.com/nextcloud/spreed/issues/9504)

## 14.0.11 – 2023-05-25
### Changed
- Allow Brave browser without unsupported warning
  [#9172](https://github.com/nextcloud/spreed/issues/9172)
- Update dependencies

### Fixed
- Fix call summary when a user has a full numeric user ID
  [#9503](https://github.com/nextcloud/spreed/issues/9503)

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

## 15.0.5 – 2023-03-24
### Fixed
- fix(calls): Fix RemoteVideoBlocker still active after removing its associated model
  [#9132](https://github.com/nextcloud/spreed/pull/9132)
- fix(polls): Remove polls also when deleting the chat history
  [#8992](https://github.com/nextcloud/spreed/pull/8992)
- fix(reactions): Fix reacting to people that left
  [#8886](https://github.com/nextcloud/spreed/pull/8886)

## 14.0.10 – 2023-03-24
### Fixed
- fix(calls): Fix RemoteVideoBlocker still active after removing its associated model
  [#9133](https://github.com/nextcloud/spreed/pull/9133)
- fix(reactions): Fix reacting to people that left
  [#8887](https://github.com/nextcloud/spreed/pull/8887)

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

## 15.0.4 – 2023-02-23
### Added
- Make "End call for everyone" available for moderators all the time
  [#8767](https://github.com/nextcloud/spreed/pull/8767)

### Changed
- Update some dependencies

### Fixed
- Show tooltip for conversations with a long title
  [#8659](https://github.com/nextcloud/spreed/pull/8659)
- Don't break the maps app with the Talk Files sidebar integration
  [#8590](https://github.com/nextcloud/spreed/pull/8590)
- Only register the maps integration when the user is allowed to use Talk
  [#8591](https://github.com/nextcloud/spreed/pull/8591)
- Fix missing "Unread mentions" floating button since Nextcloud 25 theming update
  [#8603](https://github.com/nextcloud/spreed/pull/8603)
- Fix button style while being in a call
  [#8671](https://github.com/nextcloud/spreed/pull/8671)
- Only filter mentions for participants of the conversation
  [#8665](https://github.com/nextcloud/spreed/pull/8665)
- Fix interaction of self-joined users with multiple sessions when navigating away
  [#8729](https://github.com/nextcloud/spreed/pull/8729)

## 14.0.9 – 2023-02-23
### Changed
- Update some dependencies

### Fixed
- Only filter mentions for participants of the conversation
  [#8666](https://github.com/nextcloud/spreed/pull/8666)
- Fix interaction of self-joined users with multiple sessions when navigating away
  [#8730](https://github.com/nextcloud/spreed/pull/8730)

## 15.0.3 – 2023-01-19
### Changed
- Update @nextcloud/vue library to 7.4.0
  [#8458](https://github.com/nextcloud/spreed/pull/8458)
  [#8565](https://github.com/nextcloud/spreed/pull/8565)

### Fixed
- Allow autocompleting conversation names from the middle
  [#8505](https://github.com/nextcloud/spreed/pull/8505)
- Call view not shown when rejoining a call in the file sidebar
  [#8507](https://github.com/nextcloud/spreed/pull/8507)
- Fix leaving the call when switching to another conversation
  [#8529](https://github.com/nextcloud/spreed/pull/8529)
- Don't keep the session open longer than necessary when leaving and joining a conversation
  [#8394](https://github.com/nextcloud/spreed/pull/8394)
- Enforce a length of the private JWT keys used for signaling in case libressl has 0 as default
  [#8468](https://github.com/nextcloud/spreed/pull/8468)
- Remove webserver warning when in unknown state as it confuses admins
  [#8512](https://github.com/nextcloud/spreed/pull/8512)
- Remove expired messages from API even when the background job did not execute
  [#8523](https://github.com/nextcloud/spreed/pull/8523)

## 14.0.8 – 2023-01-19
### Fixed
- Allow autocompleting conversation names from the middle
  [#8506](https://github.com/nextcloud/spreed/pull/8506)
- Call view not shown when rejoining a call in the file sidebar
  [#8508](https://github.com/nextcloud/spreed/pull/8508)
- Fix leaving the call when switching to another conversation
  [#8530](https://github.com/nextcloud/spreed/pull/8530)

## 15.0.2 – 2022-12-01
### Changed
- Allow to disable the changelog conversation with an app config
  [#8365](https://github.com/nextcloud/spreed/pull/8365)
- Improve message grouping duration to match better with UX expectations
  [#8288](https://github.com/nextcloud/spreed/pull/8288)
- Update @nextcloud/vue library to 7.1.0
  [#8405](https://github.com/nextcloud/spreed/pull/8405)
  [#8419](https://github.com/nextcloud/spreed/pull/8419)

### Fixed
- Fix in_call flag on the "Join room" API response
  [#8371](https://github.com/nextcloud/spreed/pull/8371)
- Fix bottom stripe of speaker view with high DPI
  [#8319](https://github.com/nextcloud/spreed/pull/8319)
- Make webserver configuration check less error-prone
  [#8310](https://github.com/nextcloud/spreed/pull/8310)
  [#8332](https://github.com/nextcloud/spreed/pull/8332)
- Fix monitoring command when using SQLite
  [#8304](https://github.com/nextcloud/spreed/pull/8304)
- Fix chat not loading in certain situations (e.g. more than 100 votes in a row without any chat message in between)
  [#8322](https://github.com/nextcloud/spreed/pull/8322)
- Immediately remove poll data when deleting the "asking message"
  [#8362](https://github.com/nextcloud/spreed/pull/8362)
- Fix inconsistent behaviour of link and password option on conversation creation
  [#8367](https://github.com/nextcloud/spreed/pull/8367)

## 14.0.7 – 2022-12-01
### Changed
- Allow to disable the changelog conversation with an app config
  [#8365](https://github.com/nextcloud/spreed/pull/8365)

### Fixed
- Fix in_call flag on the "Join room" API response
  [#8372](https://github.com/nextcloud/spreed/pull/8372)
- Fix bottom stripe of speaker view with high DPI
  [#8320](https://github.com/nextcloud/spreed/pull/8320)

## 13.0.11 – 2022-12-01
### Changed
- Allow to disable the changelog conversation with an app config
  [#8366](https://github.com/nextcloud/spreed/pull/8366)

### Fixed
- Fix bottom stripe of speaker view with high DPI
  [#8321](https://github.com/nextcloud/spreed/pull/8321)

## 15.0.1 – 2022-11-03
### Changed
- Take the device pixel ratio into account when calculating minimum grid size (should see more videos now on High DPI settings like MacOS and most 4k setup)
  [#8246](https://github.com/nextcloud/spreed/pull/8246)

### Fixed
- Show the number of cast votes to the question raiser and moderators on the voting screen
  [#8273](https://github.com/nextcloud/spreed/pull/8273)
- Hide talk dashboard when user can not use the Talk app
  [#8236](https://github.com/nextcloud/spreed/pull/8236)
- Hide talk sidebar integration when user can not use the Talk app
  [#8240](https://github.com/nextcloud/spreed/pull/8240)
- Show other participant's name when waiting in a one-to-one call
  [#8228](https://github.com/nextcloud/spreed/pull/8228)

## 14.0.6 – 2022-11-03
### Changed
- Take the device pixel ratio into account when calculating minimum grid size (should see more videos now on High DPI settings like MacOS and most 4k setup)
  [#8247](https://github.com/nextcloud/spreed/pull/8247)

### Fixed
- Fix XML API endpoint for chats with empty reactions result
  [#8110](https://github.com/nextcloud/spreed/pull/8110)
- Hide talk dashboard when user can not use the Talk app
  [#8237](https://github.com/nextcloud/spreed/pull/8237)
- Hide talk sidebar integration when user can not use the Talk app
  [#8241](https://github.com/nextcloud/spreed/pull/8241)
- Fix participant sessions not sent to the HPB
  [#8099](https://github.com/nextcloud/spreed/pull/8099)
- Don't search in lobbied conversations
  [#8116](https://github.com/nextcloud/spreed/pull/8116)
- Fix an issue with detecting Safari on iOS version
  [#8135](https://github.com/nextcloud/spreed/pull/8135)

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

## 12.2.8 – 2022-11-03
### Fixed
- Fix participant sessions not sent to the HPB
  [#8114](https://github.com/nextcloud/spreed/pull/8114)
- Fix guest names in search results
  [#7591](https://github.com/nextcloud/spreed/pull/7591)
- Fix an issue with detecting Safari on iOS version
  [#8277](https://github.com/nextcloud/spreed/pull/8277)

## 15.0.0 – 2022-10-18
### Added
- 🌏 Show link previews for chat messages with links
- 🛂 Chat permission
- 📊 Simple polls
- 📴 "Silent call" for group/public calls
- 🔔 Allow to re-notify a participant for a call
- 🔕 "Silent send" for chat messages
- 🔍 Search for messages in mobile apps
- 📵 Allow to disable calling functionality
- 📞 Allow SIP dial-in without individual user PINs
- 🗒️ Allow to create new files from within the chat
- ⏳ Expiration for chat messages
- 💻 New CLI commands for devops to monitor calls and rooms

## 15.0.0-rc.5 – 2022-10-13
### Fixed
- Fix frequent emoji list breaking due to multiple emoji-data versions
- Keep emoji picker open even when hovering another message
- Adjust dashboard API list to be the same as in the web
- Upgrade to @nextcloud/vue v7.0.0

## 15.0.0-rc.4 – 2022-10-10
### Fixed
- Fix call button missing on Safari for iPadOS
- Fix silent call not working from web

## 15.0.0-rc.3 – 2022-10-10
### Changed
- Move all checkboxes to NcCheckboxRadioSwitch component so the UI doesn't break
- Reorganize the conversation settings

### Fixed
- Fix chats not getting marked as read with reactions

## 15.0.0-rc.2 – 2022-09-29
### Added
- Commands to monitor calls and a single room
- Add a reference provider for call links

### Fixed
- Reaction summary missing when hovering a chat message
- Fix recursion when the lobby of a conversation expired
- Fix missing "Leave call" button for moderators in restricted rooms
- Fix padding in the left sidebar
- Bump @nextcloud/vue and @nextcloud/vue-richtext

## 15.0.0-rc.1 – 2022-09-22
### Added
- Implement the new dashboard widget modes

### Changed
- Allow to opt-out of keyboard shortcuts to improve accessibility

### Fixed
- Show more poll voters in details popover
- Do not allow to forward polls as they become non-functional
- Fix coloring of reaction buttons
- Reduce preview size for non-images
- Disallow polls in one-to-one chats
- Adjust the height of file upload modals
- Show empty content when all messages expire
- Correctly quote the parent again when sending the message failed

## 15.0.0-beta.4 – 2022-09-15
### Added
- Add related_resources UI

### Changed
- Use Node 16 and NPM 8 to compile the interface
- Several dependency updates
- Unload chat messages when moving to another chat to avoid lagging on return

### Fixed
- Several fixes to adapt to the UI changes in Nextcloud 25
- Respect message expiration in the frontend when the user never leaves the conversation
- Fix handling of deleted users in polls
- Allow recalling the owner as a normal moderator

## 14.0.5 – 2022-09-15
### Fixed
- Fix notification sending when the user is blocked by the lobby
  [#7794](https://github.com/nextcloud/spreed/pull/7794)
- Fix missing local media controls in public share sidebar
  [#7758](https://github.com/nextcloud/spreed/pull/7758)
- Fix missing screenshares in sidebar
  [#7760](https://github.com/nextcloud/spreed/pull/7760)
- Fix inconsistent state when leaving a call fails
  [#7803](https://github.com/nextcloud/spreed/pull/7803)

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

## 15.0.0-beta.3 – 2022-09-09
### Changed
- Finish polls UI

### Fixed
- Several fixes to adapt to the UI changes in Nextcloud 25

## 15.0.0-beta.2 – 2022-09-01
### Added
- 🗒️ Allow to create new files from within the chat
- 🌏 Show link previews for chat messages with links
- Show more details on the poll result screen
- Upgrade @nextcloud/vue to version 7

### Changed
- Improve performance by using the UserDisplayNameCache
- Improve performance of chats with a lot of shared files
- Populate ETag and permissions so the image editor works as expected

### Fixed
- Fix depenendency management and make sure all required dependencies are shipped
- Fix type of icon size
- Fix missing aria-labels and tooltips in various places
- Fix missing local media in share sidebar
- Fix missing screenshares in share sidebar
- Ensure that the reactions details is always an object
- Do not allow to close a poll twice
- Make handling of guest moderators more consistent

## 15.0.0-beta.1 – 2022-08-12
### Added
- 🛂 Chat permission
- 📊 Simple polls
- 📴 "Silent call" for group/public calls
- 🔔 Allow to re-notify a participant for a call
- 🔕 "Silent send" for chat messages
- 🔍 Search for messages in mobile apps
- 📵 Allow to disable calling functionality
- 📞 Allow SIP dial-in without individual user PINs

## 14.0.4 – 2022-08-11
### Added
- Extend search result attributes for better handling in mobile clients
  [#7588](https://github.com/nextcloud/spreed/pull/7588)
  [#7587](https://github.com/nextcloud/spreed/pull/7587)

### Fixed
- Location shares not visible in chat anymore (only in the sidebar tab)
  [#7550](https://github.com/nextcloud/spreed/pull/7550)
- Reduce sent information with disabled videos
  [#7709](https://github.com/nextcloud/spreed/pull/7709)
- Multiple accessibility fixes
  [#7599](https://github.com/nextcloud/spreed/pull/7599)
  [#7654](https://github.com/nextcloud/spreed/pull/7654)
  [#7553](https://github.com/nextcloud/spreed/pull/7553)
  [#7570](https://github.com/nextcloud/spreed/pull/7570)

## 13.0.8 – 2022-08-11
### Added
- Extend search result attributes for better handling in mobile clients
  [#7590](https://github.com/nextcloud/spreed/pull/7590)
  [#7589](https://github.com/nextcloud/spreed/pull/7589)

### Fixed
- Reduce sent information with disabled videos
  [#7710](https://github.com/nextcloud/spreed/pull/7710)

## 14.0.3 – 2022-07-08
### Added
- Add brute force protection for conversation tokens and passwords
  [#7535](https://github.com/nextcloud/spreed/pull/7535)
- Allow the HPB to group session pinging across multiple conversations
  [#7444](https://github.com/nextcloud/spreed/pull/7444)
- Add a capability for unified search to enable the feature on the clients
  [#7448](https://github.com/nextcloud/spreed/pull/7448)

### Fixed
- Fix backend URL in request to HPB from command line
  [#7440](https://github.com/nextcloud/spreed/pull/7440)
- Fix error when setting user status while not being in any conversation
  [#7466](https://github.com/nextcloud/spreed/pull/7466)

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

## 12.2.7 – 2022-07-08
### Added
- Add brute force protection for conversation tokens and passwords
  [#7537](https://github.com/nextcloud/spreed/pull/7537)
- Add a capability for unified search to enable the feature on the clients
  [#7450](https://github.com/nextcloud/spreed/pull/7450)

### Fixed
- Fix backend URL in request to HPB from command line
  [#7442](https://github.com/nextcloud/spreed/pull/7442)
- Fix error when setting user status while not being in any conversation
  [#7468](https://github.com/nextcloud/spreed/pull/7468)

## 14.0.2 – 2022-05-26
### Changed
- Add "Others" section to shared items tab to list unknown items
  [#7350](https://github.com/nextcloud/spreed/pull/7350)

### Fixed
- Only declare changed sessions as such instead of all sessions of that participant
  [#7382](https://github.com/nextcloud/spreed/pull/7382)
- Ensure display name of conversation owner is stored correctly
  [#7376](https://github.com/nextcloud/spreed/pull/7376)
- Don't show promotion options for circles and groups
  [#7404](https://github.com/nextcloud/spreed/pull/7404)
- Don't show permissions options for circles and groups
  [#7360](https://github.com/nextcloud/spreed/pull/7360)
- Don't show reactions option for command messages
  [#7345](https://github.com/nextcloud/spreed/pull/7345)
- Fix forwarding replies
  [#7343](https://github.com/nextcloud/spreed/pull/7343)

## 13.0.6 – 2022-05-26
### Fixed
- Ensure display name of conversation owner is stored correctly
  [#7377](https://github.com/nextcloud/spreed/pull/7377)
- Don't show promotion options for circles and groups
  [#7409](https://github.com/nextcloud/spreed/pull/7409)
- Don't show permissions options for circles and groups
  [#7405](https://github.com/nextcloud/spreed/pull/7405)

## 12.2.6 – 2022-05-26
### Fixed
- Ensure display name of conversation owner is stored correctly
  [#7378](https://github.com/nextcloud/spreed/pull/7378)
- Don't show promotion options for circles and groups
  [#7406](https://github.com/nextcloud/spreed/pull/7406)

## 14.0.1 – 2022-05-07
### Fixed
- Fix memory consumption with emoji picker in conversations with a lot of chat messages with reactions
  [#7328](https://github.com/nextcloud/spreed/pull/7328)
- Fix endless offer loop with SIP participants
  [#7288](https://github.com/nextcloud/spreed/pull/7288)
- Fix room selector in deck and maps integration
  [#7290](https://github.com/nextcloud/spreed/pull/7290)
  [#7294](https://github.com/nextcloud/spreed/pull/7294)
- Fix transceiver kind for participants without the HPB
  [#7263](https://github.com/nextcloud/spreed/pull/7263)
- Fix error on console when initiating a screenshare
  [#7330](https://github.com/nextcloud/spreed/pull/7330)
- Add missing translations of 24 branch
  [#7330](https://github.com/nextcloud/spreed/pull/7330)

## 14.0.0 – 2022-05-02
### Added
- Reactions for chat messages
- Media tab in the sidebar to show all the shared items
- Implement `OCP\Talk\IBroker` to allow apps to create conversations
- Sharing a browser tab in Chrome-based browsers can now also share the audio of that tab

### Changed
- Messages of shared objects and files can now be deleted (shares will be removed, files persist)
- Actions like calling and chatting in big rooms are now much smoother
- Compatibility with Nextcloud 24

## 14.0.0-rc.4 – 2022-04-29
### Added
- Add a modal to show more shared items

### Fixed
- Make reactions work for guests and handle guests without name in the summary
  [#7217](https://github.com/nextcloud/spreed/pull/7217)
- Add a link to notification sound settings from talk settings
  [#7224](https://github.com/nextcloud/spreed/pull/7224)
- Fix migration with Postgres and Oracle
  [#7211](https://github.com/nextcloud/spreed/pull/7211)
- Add programmatic output options to talk:active-calls command
  [#7227](https://github.com/nextcloud/spreed/pull/7227)
- Fix media tab and reactions summary in read-only rooms
  [#7236](https://github.com/nextcloud/spreed/pull/7236)

## 14.0.0-rc.3 – 2022-04-22
### Fixed
- Move message.reactions.self to message.reactionsSelf to not merge different data structures
  [#7182](https://github.com/nextcloud/spreed/pull/7182)
- Use actor and time information from the reaction not the message author
  [#7190](https://github.com/nextcloud/spreed/pull/7190)
- Fix migration of attachment types for media
  [#7196](https://github.com/nextcloud/spreed/pull/7196)
- Open chat tab by default in sidebar while in a call
  [#7201](https://github.com/nextcloud/spreed/pull/7201)
- Fix access to undefined key
  [#7195](https://github.com/nextcloud/spreed/pull/7195)
- Only set header when the value changed and the status is not 304
  [#7200](https://github.com/nextcloud/spreed/pull/7200)

### Still in progress
- Media tab showing all shared items of the conversation

## 14.0.0-rc.2 – 2022-04-19
### Fixed
- Remove event to delete shares when user leave of room
  [#7168](https://github.com/nextcloud/spreed/pull/7168)
- Add item shares from chat messages directly to the store
  [#7149](https://github.com/nextcloud/spreed/pull/7149)
- Only switch to the participant tab when the token changes not any other detail
  [#7146](https://github.com/nextcloud/spreed/pull/7146)
- Don't update last message of conversation with invisible message
  [#7142](https://github.com/nextcloud/spreed/pull/7142)

### Still in progress
- Media tab showing all shared items of the conversation

## 14.0.0-rc.1 – 2022-04-13
### Added
- Reactions for chat messages
- Media tab in the sidebar to show all the shared items
- Implement `OCP\Talk\IBroker` to allow apps to create conversations
- Sharing a browser tab in Chrome-based browsers can now also share the audio of that tab
  [#6810](https://github.com/nextcloud/spreed/pull/6810)

### Still in progress
- Media tab showing all shared items of the conversation

### Changed
- Messages of shared objects and files can now be deleted (shares will be removed, files persist)
  [#7047](https://github.com/nextcloud/spreed/pull/7047)
- Actions like calling and chatting in big rooms should now be much smoother
- Compatibility with Nextcloud 24

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

## 12.2.5 – 2022-04-08
### Fixed
- Compatibility with LDAP user backends and more than 64 characters display names
  [#7074](https://github.com/nextcloud/spreed/pull/7074)
- Compatibility with Oracle and MySQL ONLY_FULL_GROUP_BY
  [#7040](https://github.com/nextcloud/spreed/pull/7040)

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

## 12.2.4 – 2022-03-17
### Fixed
- Fix several modals, dialogs and popovers in fullscreen mode
  [#6884](https://github.com/nextcloud/spreed/pull/6884)
- Fix mentions inside brackets
  [#6870](https://github.com/nextcloud/spreed/pull/6870)
- Fix call flags update when track is disabled
  [#7015](https://github.com/nextcloud/spreed/pull/7015)

## 11.3.6 – 2022-03-17
### Fixed
- Fix several modals, dialogs and popovers in fullscreen mode
  [#6886](https://github.com/nextcloud/spreed/pull/6886)
- Fix mentions inside brackets
  [#6871](https://github.com/nextcloud/spreed/pull/6871)

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

## 12.2.3 – 2022-01-24
### Fixed
- Allow joining open conversations which are also shared as link with a password
  [#6710](https://github.com/nextcloud/spreed/pull/6710)
- Prevent handleScroll on initial loading of a conversation
  [#6718](https://github.com/nextcloud/spreed/pull/6718)
- Don't force a signaling mode when starting/ending the HPB trial
  [#6823](https://github.com/nextcloud/spreed/pull/6823)
- Add conversation token and message id to search results
  [#6746](https://github.com/nextcloud/spreed/pull/6746)

## 11.3.5 – 2022-01-24
### Fixed
- Allow joining open conversations which are also shared as link with a password
  [#6711](https://github.com/nextcloud/spreed/pull/6711)
- Don't force a signaling mode when starting/ending the HPB trial
  [#6824](https://github.com/nextcloud/spreed/pull/6824)
- Add conversation token and message id to search results
  [#6747](https://github.com/nextcloud/spreed/pull/6747)

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

## 12.2.2 – 2021-12-07
### Changed
- Show user status and message as description in 1-to-1 conversations
  [#6369](https://github.com/nextcloud/spreed/pull/6369)
- Allow apps to override/modify the TURN server list
  [#6428](https://github.com/nextcloud/spreed/pull/6428)

### Fixed
- Fix connection analyzer when using simulcast with Chromium
  [#6530](https://github.com/nextcloud/spreed/pull/6530)
- Properly allow sha256 checksums for reference ids as advertised
  [#6406](https://github.com/nextcloud/spreed/pull/6406)
- Fix forwarding object shares to other conversations
  [#6398](https://github.com/nextcloud/spreed/pull/6398)
- Fix invisible emoji picker on Safari
  [#6352](https://github.com/nextcloud/spreed/pull/6352)
- Limit deck integration to the current instance for now
  [#6412](https://github.com/nextcloud/spreed/pull/6412)

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

## 11.3.3 – 2021-10-22
### Fixed
- Fix crash of Chrome/Chromium 95
  [#6384](https://github.com/nextcloud/spreed/pull/6384)

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
- Administrators can now define commands which can be used in the chat. See [the commands documentation](https://nextcloud-talk.readthedocs.io/en/latest/commands/) for more information. You can install some sample commands via the console.
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
