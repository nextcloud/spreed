<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

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

## 15.0.7 – 2023-07-20
### Fixed
- Make conversation name and description selectable
  [#9784](https://github.com/nextcloud/spreed/issues/9784)

## 15.0.6 – 2023-05-25
### Changed
- Allow Brave browser without unsupported warning
  [#9167](https://github.com/nextcloud/spreed/issues/9167)
- Update dependencies

### Fixed
- Fix call summary when a user has a full numeric user ID
  [#9504](https://github.com/nextcloud/spreed/issues/9504)

## 15.0.5 – 2023-03-24
### Fixed
- fix(calls): Fix RemoteVideoBlocker still active after removing its associated model
  [#9132](https://github.com/nextcloud/spreed/pull/9132)
- fix(polls): Remove polls also when deleting the chat history
  [#8992](https://github.com/nextcloud/spreed/pull/8992)
- fix(reactions): Fix reacting to people that left
  [#8886](https://github.com/nextcloud/spreed/pull/8886)

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

