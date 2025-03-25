# Constants

## Conversation

### Conversation types
* `1` "One to one"
* `2` Group
* `3` Public
* `4` Changelog
* `5` Former "One to one" (When a user is deleted from the server or removed from all their conversations, `1` "One to one" rooms are converted to this type)
* `6` Note to self 

### Object types

| Constant                | Can be created | Description                                                                  | Object ID                                                                         |
|-------------------------|----------------|------------------------------------------------------------------------------|-----------------------------------------------------------------------------------|
| `file`                  | No             | Conversations about a file in the right sidebar of the files app             | File ID                                                                           |
| `share:password`        | No             | Video verification to verify the identity of the share recipient             | Share token                                                                       |
| `room`                  | Yes            | Room is a breakout room                                                      | Token of the main/parent conversation                                             |
| `phone`                 | Yes            | Room is created when calling a phone number with SIP dial-out                | `phone` (not set atm, just used for the default avatar)                           |
| `sample`                | No             | Room is a sample conversation                                                | User ID the sample                                                                |
| `event`                 | Yes            | Event conversation created via the calendar                                  | Start and end unix timestamp of the event concatenated by pound sign: `start#end` |
| `extended_conversation` | Yes            | Room is created from another conversation (e.g. adding a participant to 1-1) | Token of previous conversation                                                    |

### Read-only states
* `0` Read-write
* `1` Read-only

### Listable scope
* `0` Participants only
* `1` Regular users only, excluding users created with the Guests app
* `2` Everyone

### Webinar lobby states
* `0` No lobby
* `1` Lobby for non moderators

### SIP states
* `0` Disabled
* `1` Enabled (Each participant needs a unique PIN)
* `2` Enabled without PIN (Only the conversation token is required)

### Breakout room modes
* `0` Not configured
* `1` Automatic - Attendees are unsorted and then distributed equaly over the rooms, so they all have the same participant count (+/- 1)
* `2` Manual - A map with attendee to room number specifies the participants
* `3` Free - Each attendee picks their own breakout room

### Breakout room status
* `0` Stopped (breakout rooms lobbies are enabled)
* `1` Started (breakout rooms lobbies are disabled)

### Mention permissions
* `0` Everyone (default) - All participants can mention using `@all`
* `1` Moderators - Only moderators can mention using `@all`

### Conversation list style
* `two-lines` Normal (default) - two-line elements (with display name and last message)
* `compact` Compact - one-line elements (with display name)

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

### Participant typing privacy
* `0` Typing status is public
* `1` Typing status is private

### Attendee types
* `users` - Logged-in users
* `federated_users` - Federated users invited by their CloudID
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
* `bots` - Used by bots, commands (actor-id is the used `/command`) and the changelog conversation (actor-id is `changelog`)
* `bridged` - Users whose messages are bridged in by the [Matterbridge integration](matterbridge.md)
* `deleted_users` - Former logged-in users that got deleted (actor id is hardcoded to `deleted_users` and the display name is empty)
* `federated_users` - Federated users

### Session states
* `0` - Inactive (Notifications should still be sent, even though the user has this session in the room)
* `1` - Active (No notifications should be sent)

## Call

### Start call
* `0` - Everyone
* `1` - Participants of the conversation with an account on the instance
* `2` - Moderators
* `3` - No one

### Call recording status
* `0` - No recording
* `1` - Recording video
* `2` - Recording audio
* `3` - Starting video recording
* `4` - Starting audio recording
* `5` - Recording failed

### Recording consent required
* `0` - No recording consent is required to join a call
* `1` - Recording consent is required
* `2` - Recording consent can be enabled by moderators on conversation level (not allowed on conversation API level, only on config level)

## Chat

### Shared item types
* `audio` - Shared audio file
* `deckcard` - Shared deck card
* `file` - Shared files not falling into any other category
* `location` - Shared geo location
* `media` - Shared files with mimetype starting with image or video
* `other` - Shared objects not falling into any other category
* `voice` - Voice messages
* `recording` - Audio and video recording file of a call

## Poll

### Poll status
* `0` - Open: Participants can cast votes
* `1` - Closed: Participants can no longer cast votes and the result is displayed

### Poll mode
* `0` - Public: Participants can see the result immediately and also who voted for which option
* `1` - Hidden: The result is hidden until the poll is closed and then only the number of votes for each option are displayed

## Bots

### Bot states
* `0` Disabled
* `1` Enabled
* `2` No setup - The bot can neither be enabled nor disabled by a moderator

### Bot features
* `0` (none) - Bot is not functional at the moment
* `1` (webhook) - Bot is receive webhooks
* `2` (response) - Bot is sending webhooks
* `4` (event) - Bot is invoked via PHP event `OCA\Talk\Events\BotInvokeEvent`
* `8` (reaction) - Bot is receiving webhooks or is invoked via PHP event for adding and removing reactions

## Signaling modes
* `internal` - No external signaling server is used
* `external` - A single external signaling server is used
* `conversation_cluster` - An external signaling server is assigned per conversation.
