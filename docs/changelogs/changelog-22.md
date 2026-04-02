<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 22.0.11 – 2026-04-02
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Keep thread information when copying link of a message
  [#17478](https://github.com/nextcloud/spreed/pull/17478)
- fix(call): Fix unneeded signaling messages when sending initial state
  [#17407](https://github.com/nextcloud/spreed/pull/17407)
- fix(conversation): Improve translation string for automatic deletion of conversations
  [#17411](https://github.com/nextcloud/spreed/pull/17411)
- fix(meeting): Don't log a PHP error when a todo item is edited
  [#17547](https://github.com/nextcloud/spreed/pull/17547)
- fix(phone): Improve phone number input validation of OCC commands
  [#17551](https://github.com/nextcloud/spreed/pull/17551)
- fix(video-verification): Fix video-verification when "Start call" is limited to moderators
  [#17504](https://github.com/nextcloud/spreed/pull/17504)
- fix(signaling): Check recipient room with internal signaling
  [#17579](https://github.com/nextcloud/spreed/pull/17579)
- fix(signaling): Limit signaling support without conversation-token
  [#17587](https://github.com/nextcloud/spreed/pull/17587)
- fix(signaling): Expect nonce on request when setting up hosted signaling server
  [#17582](https://github.com/nextcloud/spreed/pull/17582)

## 22.0.10 – 2026-03-19
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(bots): Support threads for bots
  [#17345](https://github.com/nextcloud/spreed/pull/17345)
- fix(call): Hide call button from dashboard when calls are disabled
  [#17317](https://github.com/nextcloud/spreed/pull/17317)
- fix(call): Prefix typed phone number with configured prefix if needed
  [#17207](https://github.com/nextcloud/spreed/pull/17207)
- fix(chat): Improve rendering of markdown in mention bubbles
  [#17211](https://github.com/nextcloud/spreed/pull/17211)
- fix(conversation): Allow to change the password of a conversation without disabling it
  [#17221](https://github.com/nextcloud/spreed/pull/17221)
- fix(federation): Fix federation when using the email instead of the user ID
  [#17312](https://github.com/nextcloud/spreed/pull/17312)
- fix(search): Fix conversation and user search with unicode characters
  [#17143](https://github.com/nextcloud/spreed/pull/17143)
- fix(settings): Don't discard hosted High-performance backend account when 401 is returned
  [#17384](https://github.com/nextcloud/spreed/pull/17384)
- fix(settings): Expose more initial state data as capabilities
  [#17341](https://github.com/nextcloud/spreed/pull/17341)
  [#17216](https://github.com/nextcloud/spreed/pull/17216)
- fix(settings): Fix problem when editing some matterbridge components that have boolean fields
  [#17391](https://github.com/nextcloud/spreed/pull/17391)
- fix(settings): Create a stronger/longer turn secret when --generate-secret option is used
  [#17397](https://github.com/nextcloud/spreed/pull/17397)

## 22.0.9 – 2026-02-12
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(mobile-clients): Fix error message for Talk iOS when end-to-end encryption for calls is enabled
  [#17003](https://github.com/nextcloud/spreed/pull/17003)
- fix(breakout-rooms): Fix managing existing breakout rooms in conversation settings
  [#16968](https://github.com/nextcloud/spreed/pull/16968)
- fix(call): Improve new chat messages hint during calls
  [#16797](https://github.com/nextcloud/spreed/pull/16797)
- fix(chat): Fix system messages with email-invited guests
  [#16867](https://github.com/nextcloud/spreed/pull/16867)
- fix(chat): Respect thread and parent when sharing a file
  [#16860](https://github.com/nextcloud/spreed/pull/16860)
- fix(chat): Correctly update last message and unread counter from polling
  [#16910](https://github.com/nextcloud/spreed/pull/16910)
- fix(meeting): Add timezone to events created from Talk
  [#17060](https://github.com/nextcloud/spreed/pull/17060)
- fix(search): Readd missing input border for search
  [#16861](https://github.com/nextcloud/spreed/pull/16861)
- fix(federation): Abort requests early when federation is disabled
  [#16963](https://github.com/nextcloud/spreed/pull/16963)
- fix(signaling): Unify request validation for HPB, recording and other services
  [#17074](https://github.com/nextcloud/spreed/pull/17074)
- fix(bots): Fix reaction author when notifying bots
  [#16900](https://github.com/nextcloud/spreed/pull/16900)

## 22.0.8 – 2026-01-15
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Allow getting a single message
  [#16731](https://github.com/nextcloud/spreed/pull/16731)
- fix(chat): Don't show chat messages of the old chat when switching to a new chat
  [#16718](https://github.com/nextcloud/spreed/pull/16718)
- fix(chat): Don't set the cursor to the end when someone reacts to a message while editing
  [#16633](https://github.com/nextcloud/spreed/pull/16633)
- fix(call): Allow selecting a media device after an error occurred
  [#16701](https://github.com/nextcloud/spreed/pull/16701)
- fix(call): Allow preparing media devices even when the user has no permissions yet
  [#16682](https://github.com/nextcloud/spreed/pull/16682)
- fix(call): Still block mobile clients when call end-to-end encryption is enabled
  [#16674](https://github.com/nextcloud/spreed/pull/16674)

## 22.0.7 – 2025-12-17
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): fix loading more shared items
  [#16589](https://github.com/nextcloud/spreed/pull/16589)

## 22.0.6 – 2025-12-15
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Correctly expire shared items in sidebar
  [#16569](https://github.com/nextcloud/spreed/pull/16569)
- fix(call): Show video streams of other attendees for guests
  [#16544](https://github.com/nextcloud/spreed/pull/16544)
- fix(call): Prevent call reactions overflow for users
  [#16581](https://github.com/nextcloud/spreed/pull/16581)

## 22.0.5 – 2025-12-11
### Changed
- Update dependencies
- Update translations
- Build mechanism was changed from Webpack to RSPack

### Fixed
- fix(call): Fix low frame rate in the grid layout
  [#16519](https://github.com/nextcloud/spreed/pull/16519)
- fix(call): Fix "Stay in call" as primary action in "Leave call"-dialog
  [#16481](https://github.com/nextcloud/spreed/pull/16481)
- fix(call): Keep media disabled when reassigning permissions
  [#16522](https://github.com/nextcloud/spreed/pull/16522)
- fix(chat): Fix a case where chat-relay would pause and skip messages
  [#16407](https://github.com/nextcloud/spreed/pull/16407)
- fix(chat): Correctly handle unsorted reaction details
  [#16426](https://github.com/nextcloud/spreed/pull/16426)
- fix(settings): Hide unsupported shortcuts for guests
  [#16410](https://github.com/nextcloud/spreed/pull/16410)
- fix(settings): Fix wasm file name in system check
  [#16395](https://github.com/nextcloud/spreed/pull/16395)
- fix(settings): Fix default for listable conversation to be off
  [#16512](https://github.com/nextcloud/spreed/pull/16512)
- fix(settings): Fix a missing check when configuring Matterbridge
  [#16524](https://github.com/nextcloud/spreed/pull/16524)

## 22.0.4 – 2025-11-20
### Changed
- Update translations

### Fixed
- fix(settings): Show appearance and sounds settings for guests again
  [#16377](https://github.com/nextcloud/spreed/pull/16377)
- fix(settings): Add app config to disable play-sounds for guests
  [#16381](https://github.com/nextcloud/spreed/pull/16381)
- fix(settings): Do not warn about missing experimental feature
  [#16388](https://github.com/nextcloud/spreed/pull/16388)
- fix(settings): Fix path for WASM file check
  [#16389](https://github.com/nextcloud/spreed/pull/16389)

## 22.0.3 – 2025-11-20
### Changed
- Update dependencies
- Update translations
- feat(call): Use hardware acceleration for background blur
  [#16310](https://github.com/nextcloud/spreed/pull/16310)
- feat(settings): Redesign Talk settings
  [#16304](https://github.com/nextcloud/spreed/pull/16304)
- perf(chat): Chat messages can be relayed via the HPB when enabling an experiment
  [#16240](https://github.com/nextcloud/spreed/pull/16240)

### Fixed
- fix(chat): Fix memory leak from styles recalculation
  [#16260](https://github.com/nextcloud/spreed/pull/16260)
- fix(chat): Fix rendering performance from message bottom-bar
  [#16307](https://github.com/nextcloud/spreed/pull/16307)
- fix(conversations): Fix rendering performance of left sidebar with many conversations
  [#16340](https://github.com/nextcloud/spreed/pull/16340)
- fix(call): Fix squeezed buttons in small screens
  [#16205](https://github.com/nextcloud/spreed/pull/16205)
- fix(call): Use bulk activity events when ending a call
  [#16263](https://github.com/nextcloud/spreed/pull/16263)
- fix(chat): Fix amount of requests when a guest sets their name
  [#16364](https://github.com/nextcloud/spreed/pull/16364)
- fix(federation): Allow access to shared items like location, threads, … for federated users
  [#16269](https://github.com/nextcloud/spreed/pull/16269)
- fix(search): Use short datetime style in search
  [#16233](https://github.com/nextcloud/spreed/pull/16233)

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

