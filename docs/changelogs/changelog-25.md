<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 25.0.8 – 2026-11-12
### Added
- feat(reference): Improve reference widget layout
  [#20293](https://github.com/nextcloud/spreed/pull/20293)
- feat(call): move from checkbox to split button
  [#20300](https://github.com/nextcloud/spreed/pull/20300)

### Changed
- Update translations

### Fixed
- fix: Verify read marker before setting
  [#20288](https://github.com/nextcloud/spreed/pull/20288)
- fix(PollViewer): keep selected options when another participant votes
  [#20284](https://github.com/nextcloud/spreed/pull/20284)
- fix(sharing-sidebar): Fix session handling when checking share password authentication
  [#20271](https://github.com/nextcloud/spreed/pull/20271)
- fix(webrtc): Fix Firefox simulcast race condition
  [#20259](https://github.com/nextcloud/spreed/pull/20259)
- fix(AdminSettings): do not rely on Report.toJSON() for CSP violations
  [#20245](https://github.com/nextcloud/spreed/pull/20245)
- fix(polls): Expire polls and votes when message expiration is on
  [#20179](https://github.com/nextcloud/spreed/pull/20179)

## 25.0.7 – 2026-11-05
### Added
- feat(external-call): Allow the service to set the participant list
  [#20062](https://github.com/nextcloud/spreed/pull/20062)
- feat(reaction): Mark one-to-one unread on reactions
  [#20064](https://github.com/nextcloud/spreed/pull/20064)

### Changed
- Update translations

### Fixed
- fix(notifications): open conversation from notification action without page reload
  [#20166](https://github.com/nextcloud/spreed/pull/20166)
- fix(sharing): Remove file from shared folder on message delete
  [#20152](https://github.com/nextcloud/spreed/pull/20152)
- fix(lobby): Skip content during lobby
  [#20150](https://github.com/nextcloud/spreed/pull/20150)
- fix(recording): Retry transcript and summary
  [#20137](https://github.com/nextcloud/spreed/pull/20137)
- fix(dashboard): Don't treat "no mention" as unread when the read marker is negative
  [#20135](https://github.com/nextcloud/spreed/pull/20135)
- fix(dashboard): Hide "Scheduled meetings" when calls are disabled
  [#20122](https://github.com/nextcloud/spreed/pull/20122)
- fix: do not encode query params as special characters
  [#20121](https://github.com/nextcloud/spreed/pull/20121)
- fix(expiration): Improve shown expiration time
  [#20068](https://github.com/nextcloud/spreed/pull/20068)
- fix(Matterbridge): fix a regression in the additional validation of the bridge parts
  [#20059](https://github.com/nextcloud/spreed/pull/20059)
- fix: Mark session as inactive again after rejoin
  [#20043](https://github.com/nextcloud/spreed/pull/20043)
- fix(classified): Change expiration to 7 days
  [#20029](https://github.com/nextcloud/spreed/pull/20029)
- fix(dashboard): add password protection to instant meeting
  [#20023](https://github.com/nextcloud/spreed/pull/20023)
- fix(reminder): Add "Next weekend" option to message reminders
  [#19951](https://github.com/nextcloud/spreed/pull/19951)
- fix: return from listener if no federated participants
  [#19930](https://github.com/nextcloud/spreed/pull/19930)
- fix(recording): Remove "Place and time" from summary
  [#19929](https://github.com/nextcloud/spreed/pull/19929)
- fix(attachments): Fix share preview for email attendees in private conversations
  [#19928](https://github.com/nextcloud/spreed/pull/19928)
- fix(admin): Limit certificate check to configured hosts
  [#19924](https://github.com/nextcloud/spreed/pull/19924)
- fix(attachment): Fix "Download file" option for guests when "conversation folder" is used
  [#19911](https://github.com/nextcloud/spreed/pull/19911)
- fix(conversations): load list in non-leader tab without cache
  [#19862](https://github.com/nextcloud/spreed/pull/19862)

## 25.0.6 – 2026-10-15
### Added
- feat: user option to show unread conversations in collapsed tags
  [#19745](https://github.com/nextcloud/spreed/pull/19745)

### Changed
- Update translations
- Unarchive a conversation automatically on new messages or mentions
  [#19736](https://github.com/nextcloud/spreed/pull/19736)
- perf(unread-cache): Improve caching of conversations list
  [#19729](https://github.com/nextcloud/spreed/pull/19729)

### Fixed
- fix(sip): Handle direct incoming calls as phone participants
  [#19849](https://github.com/nextcloud/spreed/pull/19849)
- fix(ConfirmDialog): do not submit input value on cancel
  [#19841](https://github.com/nextcloud/spreed/pull/19841)
- fix(admin): split virtual background checks
  [#19827](https://github.com/nextcloud/spreed/pull/19827)
- fix(matterbridge): validate the server domain for Matterbridge
- fix: Do not show {TALK_PLACEHOLDER} in Files
  [#19808](https://github.com/nextcloud/spreed/pull/19808)
- fix(chat): Send delete for all combined files shares
  [#19735](https://github.com/nextcloud/spreed/pull/19735)
- fix: Find all room shares for a single path
  [#19728](https://github.com/nextcloud/spreed/pull/19728)
- fix(signaling): bound the connect time of backend notifications
  [#19715](https://github.com/nextcloud/spreed/pull/19715)

## 25.0.5 – 2026-10-02
### Fixed
- fix: web application does not load due to a regression in the build config
  [#19670](https://github.com/nextcloud/spreed/pull/19670)

## 25.0.4 – 2026-10-01
### Fixed
- fix(sharing): Fix undefined method setPasswordHash which is only added in Nextcloud 35.0.2
  [#19665](https://github.com/nextcloud/spreed/pull/19665)

## 25.0.3 – 2026-10-01
### Added
- feat(CallButton): add a split-menu with 'silent call' action
  [#19655](https://github.com/nextcloud/spreed/pull/19655)

### Changed
- Update translations

### Fixed
- fix(dashboard): Display actual last mention for user in Dashboard widget
  [#19656](https://github.com/nextcloud/spreed/pull/19656)
- fix(media-settings): Allow keyboard shortcuts on device checker
  [#19644](https://github.com/nextcloud/spreed/pull/19644)
- fix(sharing): Automatically set room password for file shares in protected conversations
  [#19641](https://github.com/nextcloud/spreed/pull/19641)
- fix(publicshareauth): rate-limit and expire password verification rooms
  [#19632](https://github.com/nextcloud/spreed/pull/19632)
  [#19617](https://github.com/nextcloud/spreed/pull/19617)
- fix(recording): Support long room tokens in upload config key
  [#19612](https://github.com/nextcloud/spreed/pull/19612)
- fix(chat): Allow search all messages in chat from selected user
  [#19590](https://github.com/nextcloud/spreed/pull/19590)


## 25.0.2 – 2026-09-25

### Fixed
- fix(migration): Correctly check for Index rather than Constraint
  [#19540](https://github.com/nextcloud/spreed/pull/19540)

## 25.0.1 – 2026-09-24
### Changed
- Update dependencies
- Update translations

### Fixed
- fix(polls): improve wording for 'anonymous' polls
  [#19527](https://github.com/nextcloud/spreed/pull/19527)
- fix(notification): fix notification links when fully subscribed
  [#19520](https://github.com/nextcloud/spreed/pull/19520)
- fix(integrations): show conversation avatars in Files Sharing tab
  [#19515](https://github.com/nextcloud/spreed/pull/19515)
- fix(upload): save compressed images as JPEG
  [#19493](https://github.com/nextcloud/spreed/pull/19493)
- fix(chat): show preview for single-share documents
  [#19434](https://github.com/nextcloud/spreed/pull/19434)
- fix(recording): log activity to recording owner
  [#19424](https://github.com/nextcloud/spreed/pull/19424)

## 25.0.0 – 2026-09-16
### Added
- Classified conversations, announcements and channels
- Owner role: promote and demote users to and from owners
- Global message search in the conversation list
- Preserve conversations to restrict deletion, purging and visibility changes
- Setting to limit the start of calls to specific groups
- Upload editor in the message composer with image compression
- Combine file shares into a single message in the chat
- Introduce multi-speaker view in calls
- Show the time of the last message in the conversation list
- Support configurable call summary prompts
- New design of the Talk dashboard panel

### Changed
- Update dependencies
- Update translations
- Require Nextcloud 35 / Hub 26 Summer

## 25.0.0-rc.3 – 2026-09-10
### Changed
- Update translations
- design: rearrange file previews in combined messages
  [#19269](https://github.com/nextcloud/spreed/pull/19269)

### Fixed
- fix(chat): fallback to real image in case of a preview failure
  [#19361](https://github.com/nextcloud/spreed/pull/19361)
- fix(chat): start threads with the file upload
  [#19351](https://github.com/nextcloud/spreed/pull/19351)

## 25.0.0-rc.2 – 2026-09-03
### Changed
- Update dependencies
- Update translations
- feat(recording): support configurable call summary prompts
  [#19252](https://github.com/nextcloud/spreed/pull/19252)
- feat(search): promote "search messages in current chat" feature better
  [#19185](https://github.com/nextcloud/spreed/pull/19185)

### Fixed
- fix(attachments): Fix missing previews for guests
  [#19254](https://github.com/nextcloud/spreed/pull/19254)
  [#19168](https://github.com/nextcloud/spreed/pull/19168)
- fix(chat): lock scrolling in inactive session
  [#19226](https://github.com/nextcloud/spreed/pull/19226)
- fix(chat): Send read marker only when session is active
  [#19219](https://github.com/nextcloud/spreed/pull/19219)
- fix(chat): Sanitise file name
  [#19230](https://github.com/nextcloud/spreed/pull/19230)
- fix(presets): apply default presets that are set by admins
  [#19184](https://github.com/nextcloud/spreed/pull/19184)
- fix(sharing): Add a repair step to remove orphan entries
  [#19246](https://github.com/nextcloud/spreed/pull/19246)
- fix(sip): Correct restrict SIP creation
  [#19243](https://github.com/nextcloud/spreed/pull/19243)

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
