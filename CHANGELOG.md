# Changelog
All notable changes to this project will be documented in this file.

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


