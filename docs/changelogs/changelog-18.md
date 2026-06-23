<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

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

## 18.0.13 – 2024-11-07
### Changed
- Update translations
- Update dependencies

### Fixed
- fix(chat): Fix layout for guests on public conversations
  [#13620](https://github.com/nextcloud/spreed/issues/13620)
- fix(UI): Improve handling of sidebar on mobile view
  [#12693](https://github.com/nextcloud/spreed/issues/12693)

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

## 18.0.10 – 2024-07-11
### Fixed
- fix(sharing): Fix share detection within object stores
  [#12628](https://github.com/nextcloud/spreed/pull/12628)

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

## 18.0.8 – 2024-05-23
### Fixed
- fix(polls): Remove actor info from system message
  [#12343](https://github.com/nextcloud/spreed/pull/12343)
- fix(recording): Stop broken recording backend
  [#12402](https://github.com/nextcloud/spreed/pull/12402)

## 18.0.7 – 2024-04-12
### Changed
- Update translations
- Update several dependencies

### Fixed
- fix(conversation): Fix error when adding participants while creating a conversation
  [#12057](https://github.com/nextcloud/spreed/issues/12057)
- fix(conversation): Fix missing icon in conversation settings for file conversations
  [#12051](https://github.com/nextcloud/spreed/issues/12051)

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

