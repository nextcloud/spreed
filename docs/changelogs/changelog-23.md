<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 23.0.2 – 2026-03-19
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(bots): Support threads for bots
  [#17344](https://github.com/nextcloud/spreed/pull/17344)
- fix(call): Hide call button from dashboard when calls are disabled
  [#17316](https://github.com/nextcloud/spreed/pull/17316)
- fix(call): Migrate background option from attendee to user level
  [#17340](https://github.com/nextcloud/spreed/pull/17340)
- fix(call): Prefix typed phone number with configured prefix if needed
  [#17206](https://github.com/nextcloud/spreed/pull/17206)
- fix(call): Fix unneeded signaling messages when sending initial state
  [#17408](https://github.com/nextcloud/spreed/pull/17408)
- fix(conversation): Fix password form of password protected public conversations
  [#17315](https://github.com/nextcloud/spreed/pull/17315)
- fix(conversation): Allow to change the password of a conversation without disabling it
  [#17222](https://github.com/nextcloud/spreed/pull/17222)
- fix(federation): Fix federation when using the email instead of the user ID
  [#17313](https://github.com/nextcloud/spreed/pull/17313)
- fix(settings): Don't discard hosted High-performance backend account when 401 is returned
  [#17383](https://github.com/nextcloud/spreed/pull/17383)
- fix(settings): Expose more initial state data as capabilities
  [#17330](https://github.com/nextcloud/spreed/pull/17330)
  [#17217](https://github.com/nextcloud/spreed/pull/17217)
- fix(settings): Fix problem when editing some matterbridge components that have boolean fields
  [#17392](https://github.com/nextcloud/spreed/pull/17392)
- fix(settings): Create a stronger/longer turn secret when --generate-secret option is used
  [#17398](https://github.com/nextcloud/spreed/pull/17398)
- fix(settings): Add app config to allow specifying the default for conversation list and chat style
  [#17274](https://github.com/nextcloud/spreed/pull/17274)
- fix(branding): Remove label referencing the server name
  [#17348](https://github.com/nextcloud/spreed/pull/17348)
  [#17326](https://github.com/nextcloud/spreed/pull/17326)
  [#17325](https://github.com/nextcloud/spreed/pull/17325)

## 23.0.1 – 2026-02-18
### Fixed
- fix(sharing): Fix type error when a share is loaded before the user loaded all their shares
  [#17160](https://github.com/nextcloud/spreed/issues/17160)

## 23.0.0 – 2026-02-18
### Added
- Live translations in call
- Allow moderators to pin messages in a chat
  [#3390](https://github.com/nextcloud/spreed/issues/3390)
- Chat is now shown in split view by default
  [#14944](https://github.com/nextcloud/spreed/issues/14944)
- Allow users to schedule messages to send at a later time
  [#3954](https://github.com/nextcloud/spreed/issues/3954)
- Use hardware acceleration for background blurring when available
  [#16072](https://github.com/nextcloud/spreed/issues/16072)
- Allow participants to control noise suppression, echo cancellation and auto gain for their microphone
  [#3252](https://github.com/nextcloud/spreed/issues/3252)
- Relay chat messages via the High-performance backend to improve performance and scaling
  [#624](https://github.com/nextcloud/spreed/issues/624)
- Add number of calls and participants to openmetrics
  [#16874](https://github.com/nextcloud/spreed/pull/16874)

### Changed
- Update dependencies
- Update translations
- Require Nextcloud 33 / Hub 26 Winter
- Enable notifications in group conversations by default for new instances
  [#16319](https://github.com/nextcloud/spreed/issues/16319)
- Bots: The `object.name` was set to an empty string for messages with attachments. This was fixed to be `'message'` as for normal messages without any attachments.
- Improve performance for shares

## 23.0.0-rc.4 – 2026-02-12
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(mobile-clients): Fix error message for Talk iOS when end-to-end encryption for calls is enabled
  [#17001](https://github.com/nextcloud/spreed/pull/17001)
- fix(chat): Correctly show started threads from messages via chat relay
  [#16924](https://github.com/nextcloud/spreed/pull/16924)
  [#17058](https://github.com/nextcloud/spreed/pull/17058)
  [#17065](https://github.com/nextcloud/spreed/pull/17065)
- fix(chat): Fix layout of split-view in right sidebar and mobile screens
  [#17033](https://github.com/nextcloud/spreed/pull/17033)
- fix(chat): Block sending messages from scheduled messages view to avoid UX confusion
  [#17034](https://github.com/nextcloud/spreed/pull/17034)
- fix(chat): Correctly highlight "Call started" messages via chat relay
  [#17035](https://github.com/nextcloud/spreed/pull/17035)
- fix(chat): Mark messages that failed sending more obvious
  [#17054](https://github.com/nextcloud/spreed/pull/17054)
- fix(chat): Don't mark chat read when a scheduled message is sent
  [#17056](https://github.com/nextcloud/spreed/pull/17056)
- fix(chat): Improve unread message marker with chat relay
  [#17067](https://github.com/nextcloud/spreed/pull/17067)
- fix(chat): Improve handling when chat relay and long polling are in parallel
  [#17072](https://github.com/nextcloud/spreed/pull/17072)
- fix(chat): Reset action menu state after reacting
  [#17063](https://github.com/nextcloud/spreed/pull/17063)
- fix(meeting): Add timezone to events created from Talk
  [#17059](https://github.com/nextcloud/spreed/pull/17059)
- fix(signaling): Unify request validation for HPB, recording and other services
  [#17075](https://github.com/nextcloud/spreed/pull/17075)
- perf(sharing): Implement filter for path in getShares()
  [#17004](https://github.com/nextcloud/spreed/pull/17004)
- perf(sharing): Improve performance on mount point name generation
  [#17048](https://github.com/nextcloud/spreed/pull/17048)

## 23.0.0-rc.3 – 2026-02-05
### Changed
- Update dependencies
- Update translations

### Fixed
- perf(sharing): Improve performance for validating access to file conversations
  [#16918](https://github.com/nextcloud/spreed/pull/16918)
  [#16970](https://github.com/nextcloud/spreed/pull/16970)
- fix(federation): Bail out early when federation is disabled
  [#16964](https://github.com/nextcloud/spreed/pull/16964)
- fix(chat): Switch unread/read icon to material design icons
  [#16972](https://github.com/nextcloud/spreed/pull/16972)
- fix(chat): Fix mention and emoji autocomplete in the background when typing a caption
  [#16982](https://github.com/nextcloud/spreed/pull/16982)
- fix(chat): Fix client response when loading chat
  [#16985](https://github.com/nextcloud/spreed/pull/16985)

## 23.0.0-rc.2 – 2026-01-29
### Added
- feat(openmetrics): Add number of calls and participants to openmetrics
  [#16874](https://github.com/nextcloud/spreed/pull/16874)
- feat(chat): Show message expiration in sidebar
  [#16830](https://github.com/nextcloud/spreed/pull/16830)

### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Polishing of message bubbles and pinned messages
  [#16906](https://github.com/nextcloud/spreed/pull/16906)
- fix(chat): Fix email guests without a displayname showing a HASH
  [#16868](https://github.com/nextcloud/spreed/pull/16868)
- fix(bots): Fix actor information when a reaction was done
  [#16899](https://github.com/nextcloud/spreed/pull/16899)
- fix(bots): Fix bot reaction missing in relayed chat messages
  [#16895](https://github.com/nextcloud/spreed/pull/16895)

## 23.0.0-rc.1 – 2026-01-22
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Polishing of message bubbles
  [#16760](https://github.com/nextcloud/spreed/pull/16760)
  [#16763](https://github.com/nextcloud/spreed/pull/16763)
- fix(chat): Polishing of scheduled messages
  [#16764](https://github.com/nextcloud/spreed/pull/16764)
  [#16790](https://github.com/nextcloud/spreed/pull/16790)
- fix(chat): Fix chat infos in sidebar layout
  [#16782](https://github.com/nextcloud/spreed/pull/16782)
- fix(chat): Correctly post replies and threads when sharing files
  [#16819](https://github.com/nextcloud/spreed/pull/16819)

## 23.0.0-beta.2 – 2026-01-15
### Added
- Live translations in call

### Changed
- Update dependencies
- Update translations
- Bots: The `object.name` was set to an empty string for messages with attachments. This was fixed to be `'message'` as for normal messages without any attachments
  [#16724](https://github.com/nextcloud/spreed/pull/16724)

### Fixed
- fix(chat): Allow getting a single message
  [#16730](https://github.com/nextcloud/spreed/pull/16730)
- fix(chat): Don't show chat messages of the old chat when switching to a new chat
  [#16715](https://github.com/nextcloud/spreed/pull/16715)
- fix(call): Allow selecting a media device after an error occurred
  [#16699](https://github.com/nextcloud/spreed/pull/16699)
- perf(shares): Improve performance for shares
  [#16721](https://github.com/nextcloud/spreed/pull/16721)
  [#16655](https://github.com/nextcloud/spreed/pull/16655)
  [#16713](https://github.com/nextcloud/spreed/pull/16713)

## 23.0.0-beta.1 – 2026-01-09
### Added
- Allow moderators to pin messages in a chat
  [#3390](https://github.com/nextcloud/spreed/issues/3390)
- Chat is now shown in split view by default
  [#14944](https://github.com/nextcloud/spreed/issues/14944)
- Allow users to schedule messages to send at a later time
  [#3954](https://github.com/nextcloud/spreed/issues/3954)
- Use hardware acceleration for background blurring when available
  [#16072](https://github.com/nextcloud/spreed/issues/16072)
- Allow participants to control noise suppression, echo cancellation and auto gain for their microphone
  [#3252](https://github.com/nextcloud/spreed/issues/3252)
- Relay chat messages via the High-performance backend to improve performance and scaling
  [#624](https://github.com/nextcloud/spreed/issues/624)

### Changed
- Update dependencies
- Update translations
- Require Nextcloud 33 / Hub 26 Winter
- Enable notifications in group conversations by default for new instances
  [#16319](https://github.com/nextcloud/spreed/issues/16319)
- Bots: The `object.name` was set to an empty string for messages with attachments. This was fixed to be `'message'` as for normal messages without any attachments.

