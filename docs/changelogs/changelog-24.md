<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 24.0.1 – 2026-06-26
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(call): Increase FPS to 30 in all levels and update video quality
  [#18410](https://github.com/nextcloud/spreed/pull/18410)
- fix(call): reconnect participants when media-permissions are re-granted
  [#18441](https://github.com/nextcloud/spreed/pull/18441)
- fix(session): restore access to password-protected rooms for email guests
  [#18347](https://github.com/nextcloud/spreed/pull/18347)
- fix(settings): Allow to configure certificates expiration
  [#18300](https://github.com/nextcloud/spreed/pull/18300)
- fix(call): stop virtual background effect when device checker is not in use
  [#18292](https://github.com/nextcloud/spreed/pull/18292)
- fix(admin): restore admin setting for default group notifications
  [#18281](https://github.com/nextcloud/spreed/pull/18281)
- fix(recording): allow recording service to work on E2EE calls
  [#18268](https://github.com/nextcloud/spreed/pull/18268)

## 24.0.0 – 2026-06-08
### Added
- Call from anywhere - Integration of calls into the avatar menu
- Permanent call rooms
- Advanced noise suppression
- Performance improvements in attachments handling
- Tagging, sorting and grouping options for conversations
- Allow email guests without public link

### Changed
- Update dependencies
- Update translations
- Require Nextcloud 34 / Hub 26 Spring
- Move "Raise hand" in call reactions menu
- Improved private reply show the quote now as well
- Split chat permissions to allow reactions without chat messages

## 24.0.0-rc.4 – 2026-06-02
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(chat): Fix hotkeys handling for editing chat messages
  [#18189](https://github.com/nextcloud/spreed/pull/18189)
- fix(sipbridge): End dial-out calls if rejected by recipient
  [#18193](https://github.com/nextcloud/spreed/pull/18193)

## 24.0.0-rc.3 – 2026-05-28
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(call): Render other user's video while screensharing
  [#18065](https://github.com/nextcloud/spreed/pull/18065)
- fix(notifications): Fix name in reaction notification for federated users
  [#18068](https://github.com/nextcloud/spreed/pull/18068)
- fix(admin): Allow accessing admin settings in limited Talk configuration environment
  [#18127](https://github.com/nextcloud/spreed/pull/18127)
- fix(sipbridge): Authenticate internal request from SIP Bridge
  [#18119](https://github.com/nextcloud/spreed/pull/18119)
- fix(conversation): Refresh list on disinvite event 
  [#18132](https://github.com/nextcloud/spreed/pull/18132)
- fix(chat): Fix email guests name caching 
  [#18136](https://github.com/nextcloud/spreed/pull/18136)
- fix(conversations): Adjust logic for 'Unread mentions' navigation
  [#18134](https://github.com/nextcloud/spreed/pull/18134)
- fix(call): Respect participant permissions for hardware access
  [#18137](https://github.com/nextcloud/spreed/pull/18137)

## 24.0.0-rc.2 – 2026-05-21
### Added
- feat(call): Move "Raise hand" in call reactions menu
  [#18046](https://github.com/nextcloud/spreed/issues/18046)

### Changed
- Update dependencies
- Update translations

### Fixed
- fix(recording): fix recipient share path for normal shares when conversation subfolders is used
  [#18051](https://github.com/nextcloud/spreed/issues/18051)
- fix(tags): Move created tags before "Others" when "Others" is the last tag
  [#18050](https://github.com/nextcloud/spreed/issues/18050)
- fix(tags): collapse tag groups by clicking on header
  [#18050](https://github.com/nextcloud/spreed/issues/18050)

## 24.0.0-rc.1 – 2026-05-13
### Added
- feat(email): Allow email guests without public link
  [#13609](https://github.com/nextcloud/spreed/issues/13609)
- feat(preset): Allow to force the lobby
  [#17935](https://github.com/nextcloud/spreed/issues/17935)

### Changed
- Update dependencies
- Update translations

### Fixed
- fix(navigation): adjust active "Home" link to the new design
  [#17932](https://github.com/nextcloud/spreed/issues/17932)
- fix(sidebar): rework call UI in Talk integrations
  [#17860](https://github.com/nextcloud/spreed/issues/17860)
- fix(files-sidebar): reduce initial loading size of Files sidebar
  [#11551](https://github.com/nextcloud/spreed/issues/11551)
- fix(sharing): Fix probe attachment call for windows compatible filenames
  [#17925](https://github.com/nextcloud/spreed/issues/17925)
- fix(voice-rooms): Hide preset when calls are disabled
  [#17933](https://github.com/nextcloud/spreed/issues/17933)


## 24.0.0-beta.1 – 2026-05-04
### Added
- Call from anywhere - Integration of calls into the avatar menu
  [#15416](https://github.com/nextcloud/spreed/issues/15416)
- Permanent call rooms
  [#15417](https://github.com/nextcloud/spreed/issues/15417)
- Advanced noise suppression
  [#17147](https://github.com/nextcloud/spreed/issues/17147)
- Performance improvements in attachments handling
  [#4340](https://github.com/nextcloud/spreed/issues/4340)
- Tagging, sorting and grouping options for conversations
  [#12025](https://github.com/nextcloud/spreed/issues/12025)

### Changed
- Update dependencies
- Update translations
- Require Nextcloud 34 / Hub 26 Spring
- Improved private reply show the quote now as well
  [#6301](https://github.com/nextcloud/spreed/issues/6301)
- Split chat permissions to allow reactions without chat messages
  [#11329](https://github.com/nextcloud/spreed/issues/11329)

