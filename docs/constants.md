---
title: Constants
---

## Conversation

### Conversation types
* `1` "One to one"
* `2` Group
* `3` Public
* `4` Changelog

### Read-only states
* `0` Read-write
* `1` Read-only

### Listable scope
* `0` Participants only
* `1` Regular users only, excluding guest app users
* `2` Everyone

### Webinar lobby states
* `0` No lobby
* `1` Lobby for non moderators

### SIP states
* `0` Disabled
* `1` Enabled (Each participant needs a unique PIN)
* `2` Enabled without PIN (Only the conversation token is required)

## Participants

### Participant types
* `1` Owner
* `2` Moderator
* `3` User
* `4` Guest
* `5` User following a public link
* `6` Guest with moderator permissions

### Participant in-call flag
* `0` Disconnected
* `1` In-call
* `2` Provides audio
* `4` Provides video
* `8` Uses SIP dial-in

### Participant notification levels
* `0` Default (`1` for one-to-one conversations, `2` for other conversations)
* `1` Always notify
* `2` Notify on mention
* `3` Never notify

### Participant call notification levels
* `0` Off
* `1` On (default)

### Participant read status privacy
* `0` Read status is public
* `1` Read status is private

### Attendee types
* `users` - Logged-in users
* `groups` - Groups
* `circles` - [Circle from the Circles app](https://github.com/nextcloud/circles)
* `guests` - Guest without a login
* `emails` - A guest invited by email address

### Attendee permissions
* `0` Default permissions (will pick the one from the next level of: user, call, conversation)
* `1` Custom permissions (this is required to be able to remove all other permissions)
* `2` Start call
* `4` Join call
* `8` Can ignore lobby
* `16` Can publish audio stream
* `32` Can publish video stream
* `64` Can publish screen sharing stream
* `128` Can post chat message, share items and do reactions

### Attendee permission modifications
* `set` - Setting this permission set.
* `add` - Add the given flags to the permissions.
* `remove` - Remove the given flags from the permissions.

### Actor types of chat messages
* `users` - Logged-in users
* `guests` - Guest users (attendee type `guests` and `emails`)
* `bots` - Used by commands (actor-id is the used `/command`) and the changelog conversation (actor-id is `changelog`)
* `bridged` - Users whose messages are bridged in by the [Matterbridge integration](matterbridge.md)

## Chat

### Shared item types
* `audio` - Shared audio file
* `deckcard` - Shared deck card
* `file` - Shared files not falling into any other category
* `location` - Shared geo location
* `media` - Shared files with mimetype starting with image or video
* `other` - Shared objects not falling into any other category
* `voice` - Voice messages

## Signaling modes
* `internal` - No external signaling server is used
* `external` - A single external signaling server is used
* `conversation_cluster` - An external signaling server is assigned per conversation.
