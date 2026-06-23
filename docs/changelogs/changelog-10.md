<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

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

## 10.1.6 – 2021-07-15
### Fixed
- Fix connection quality stats not reset when setting a new peer connection
  [#5770](https://github.com/nextcloud/spreed/pull/5770)

## 10.0.9 – 2021-07-15
### Fixed
- Fix connection quality stats not reset when setting a new peer connection
  [#5769](https://github.com/nextcloud/spreed/pull/5769)

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

## 10.1.3 – 2021-03-04
### Fixed
- Fixed a bug in the migration that could prevent copying all participants to the attendee table
  [#5245](https://github.com/nextcloud/spreed/pull/5245)

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

