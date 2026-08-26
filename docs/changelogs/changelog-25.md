<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 25.0.0-rc.1 – 2026-08-26
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(call): Improve speaker view layout
  [#19035](https://github.com/nextcloud/spreed/pull/19035)
  [#19003](https://github.com/nextcloud/spreed/pull/19003)
- fix(chat): Make scroll to bottom work for very long messages
  [#19018](https://github.com/nextcloud/spreed/pull/19018)
- fix(chat): Don't break message grouping on edited messages
  [#19006](https://github.com/nextcloud/spreed/pull/19006)
- fix(conversations): Hide unread counter from tag headers
  [#19104](https://github.com/nextcloud/spreed/pull/19104)
- fix(conversations): Correctly scroll to unread mentions in conversation list
  [#19102 ](https://github.com/nextcloud/spreed/pull/19102)
- fix(unified-search): Correctly register app as search result
  [#19085](https://github.com/nextcloud/spreed/pull/19085)
- fix(upload): Combine temporary messages during upload
  [#19106](https://github.com/nextcloud/spreed/pull/19106)
- fix(upload): Show file size on upload
  [#19045](https://github.com/nextcloud/spreed/pull/19045)

## 25.0.0-alpha.1 – 2026-08-13
### Added
- Classified conversations, announcements and channels
  [#18680](https://github.com/nextcloud/spreed/pull/18680)
  [#18685](https://github.com/nextcloud/spreed/pull/18685)
- Owner role: promote and demote users to and from owners
  [#18924](https://github.com/nextcloud/spreed/pull/18924)
- Global message search in the conversation list
  [#16344](https://github.com/nextcloud/spreed/pull/16344)
- Preserve conversations to restrict deletion, purging and visibility changes
  [#18351](https://github.com/nextcloud/spreed/pull/18351)
- Setting to limit the start of calls to specific groups
  [#18647](https://github.com/nextcloud/spreed/pull/18647)
- Upload editor in the message composer with image compression
  [#18984](https://github.com/nextcloud/spreed/pull/18984)
  [#18906](https://github.com/nextcloud/spreed/pull/18906)
- Combine file shares into a single message in the chat
  [#18989](https://github.com/nextcloud/spreed/pull/18989)
- Introduce multi-speaker view in calls
  [#18966](https://github.com/nextcloud/spreed/pull/18966)
- Show the time of the last message in the conversation list
  [#18987](https://github.com/nextcloud/spreed/pull/18987)

### Changed
- Update dependencies
- Update translations
- Require Nextcloud 35 / Hub 26 Summer
- New design of the Talk dashboard panel
  [#18980](https://github.com/nextcloud/spreed/pull/18980)
