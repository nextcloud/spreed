# Nextcloud Talk API Specification

> **Generated from source analysis of `nextcloud/spreed` repository**
> **API Versions:** v1 (chat, polls, reactions), v3 (signaling), v4 (rooms, calls)
> **Base URL:** `/ocs/v2.php/apps/spreed`

---

## Table of Contents

1. [Authentication & Headers](#authentication--headers)
2. [Critical Notes](#critical-notes)
3. [Polling Patterns](#polling-patterns)
4. [Rooms API (v4)](#rooms-api-v4)
5. [Chat API (v1)](#chat-api-v1)
6. [Participants API (v4)](#participants-api-v4)
7. [Call API (v4)](#call-api-v4)
8. [Signaling API (v3)](#signaling-api-v3)
9. [Polls API (v1)](#polls-api-v1)
10. [Reactions API (v1)](#reactions-api-v1)
11. [Avatar API (v1)](#avatar-api-v1)
12. [Bans API (v1)](#bans-api-v1)
13. [Bots API (v1)](#bots-api-v1)
14. [Breakout Rooms API (v1)](#breakout-rooms-api-v1)
15. [Federation API (v1)](#federation-api-v1)
16. [Recording API (v1)](#recording-api-v1)
17. [Threads API (v1)](#threads-api-v1)
18. [Data Types](#data-types)
19. [Constants](#constants)

---

## Authentication & Headers

### Required Headers

```http
OCS-APIRequest: true
Accept: application/json
Content-Type: application/json
```

### Authentication Methods

| Method | Header | Use Case |
|--------|--------|----------|
| **App Password** | `Authorization: Basic base64(username:app-password)` | Recommended for custom clients |
| **Session Cookie** | `Cookie: nc_session_id=...` | Web browser sessions |
| **Bearer Token** | `Authorization: Bearer <token>` | OAuth2 flows |

### Response Format

All OCS endpoints return:

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": { /* endpoint-specific response */ }
  }
}
```

---

## Critical Notes

### Room Token vs Room ID

| Field | Type | Usage |
|-------|------|-------|
| **`token`** | `string` (4-30 chars, `[a-z0-9]`) | **Primary identifier** - Used in ALL API paths |
| **`id`** | `integer` | Internal database ID - Rarely used in API calls |

**IMPORTANT:** Always use `token` for API calls, not `id`.

### API Version Matrix

| Domain | Version | Base Path |
|--------|---------|-----------|
| Rooms/Conversations | **v4** | `/api/v4/room` |
| Participants | **v4** | `/api/v4/room/{token}/participants` |
| Calls | **v4** | `/api/v4/call` |
| Chat/Messages | **v1** | `/api/v1/chat` |
| Signaling | **v3** | `/api/v3/signaling` |
| Polls | **v1** | `/api/v1/poll` |
| Reactions | **v1** | `/api/v1/reaction` |
| Avatars | **v1** | `/api/v1/room/{token}/avatar` |
| Breakout Rooms | **v1** | `/api/v1/breakout-rooms` |

---

## Polling Patterns

### Chat Messages (Long Polling)

```
GET /api/v1/chat/{token}?lookIntoFuture=1&timeout=30&lastKnownMessageId={id}
```

| Parameter | Value | Description |
|-----------|-------|-------------|
| `timeout` | `30` | Server holds connection up to 30 seconds |
| `lookIntoFuture` | `1` | Poll for new messages |
| `lastKnownMessageId` | `{id}` | Messages after this ID |
| `limit` | `100` | Max messages per response |

**Frontend Implementation:**
- Poll interval: **500ms** between successful requests
- Error backoff: **1s → 30s** (incremental +5s per error)
- Message expiration cleanup: **30s** interval

### Participants List

| Context | Interval | Trigger |
|---------|----------|---------|
| Panel visible / In call | **3 seconds** | Active user engagement |
| Background tab | **15 seconds** | Reduced activity |
| After signaling update | **60 seconds** | Post-event refresh |

### Conversation List

```
GET /api/v4/room?modifiedSince={timestamp}
```

- Polling interval: **30 seconds**
- Use `modifiedSince` for delta updates

### WebSocket (Signaling)

- Welcome timeout: **3 seconds**
- Reconnect backoff: **1s → 16s** (exponential)
- Message batch interval: **500ms**

---

## Rooms API (v4)

### List Conversations

```http
GET /api/v4/room
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `noStatusUpdate` | `0\|1` | `0` | Don't update user online status |
| `includeStatus` | `boolean` | `false` | Include user status for 1-1 chats |
| `modifiedSince` | `integer` | `0` | Unix timestamp for delta sync |
| `includeLastMessage` | `boolean` | `true` | Include last message in response |

**Response Headers:**

| Header | Description |
|--------|-------------|
| `X-Nextcloud-Talk-Hash` | Config hash for cache invalidation |
| `X-Nextcloud-Talk-Modified-Before` | Timestamp for next delta request |
| `X-Nextcloud-Talk-Federation-Invites` | Pending federation invite count |

**Response:** `TalkRoom[]`

---

### Get Single Room

```http
GET /api/v4/room/{token}
```

**Authentication:** Public (with brute-force protection)

**Response:** `TalkRoom`

**Error Codes:**

| Status | Description |
|--------|-------------|
| `401` | Unauthorized |
| `404` | Room not found |

---

### Create Room

```http
POST /api/v4/room
```

**Request Body:**

```json
{
  "roomType": 2,
  "roomName": "Team Chat",
  "invite": "user-id",
  "source": "users",
  "objectType": "",
  "objectId": "",
  "password": "",
  "readOnly": 0,
  "listable": 0,
  "messageExpiration": 0,
  "lobbyState": 0,
  "lobbyTimer": null,
  "sipEnabled": 0,
  "permissions": 0,
  "recordingConsent": 0,
  "mentionPermissions": 0,
  "description": "",
  "participants": []
}
```

**Room Types:**

| Value | Type |
|-------|------|
| `1` | One-to-one |
| `2` | Group |
| `3` | Public |
| `4` | Changelog |
| `5` | Former one-to-one |
| `6` | Note to self |

**Participant Sources:**

| Source | Description |
|--------|-------------|
| `users` | Nextcloud users |
| `groups` | Nextcloud groups |
| `circles` | Nextcloud circles |
| `emails` | Email addresses |
| `federated_users` | Federated users (cloud IDs) |
| `phones` | Phone numbers |
| `teams` | Nextcloud teams |

**Response:** `TalkRoom` (Status: 200 existing, 201 created, 202 with invalid invitations)

---

### Update Room Name

```http
PUT /api/v4/room/{token}
```

**Requires:** Moderator

**Request Body:**

```json
{
  "roomName": "New Room Name"
}
```

---

### Update Room Description

```http
PUT /api/v4/room/{token}/description
```

**Requires:** Moderator

**Request Body:**

```json
{
  "description": "Room description (max 2000 chars)"
}
```

---

### Delete Room

```http
DELETE /api/v4/room/{token}
```

**Requires:** Moderator

---

### Set Room Password

```http
PUT /api/v4/room/{token}/password
```

**Requires:** Moderator

**Request Body:**

```json
{
  "password": "secret123"
}
```

---

### Toggle Public/Private

```http
POST /api/v4/room/{token}/public   # Make public (allow guests)
DELETE /api/v4/room/{token}/public # Make private
```

**Requires:** Logged-in Moderator

---

### Set Read-Only State

```http
PUT /api/v4/room/{token}/read-only
```

**Request Body:**

```json
{
  "state": 1
}
```

| State | Description |
|-------|-------------|
| `0` | Read-write |
| `1` | Read-only |

---

### Set Listable Scope

```http
PUT /api/v4/room/{token}/listable
```

**Request Body:**

```json
{
  "scope": 2
}
```

| Scope | Description |
|-------|-------------|
| `0` | Not listable |
| `1` | Listable for users |
| `2` | Listable for everyone |

---

### Favorites

```http
POST /api/v4/room/{token}/favorite     # Add to favorites
DELETE /api/v4/room/{token}/favorite   # Remove from favorites
```

---

### Archive/Unarchive

```http
POST /api/v4/room/{token}/archive      # Archive
DELETE /api/v4/room/{token}/archive    # Unarchive
```

---

### Important/Sensitive Flags

```http
POST /api/v4/room/{token}/important    # Mark important (notifications on DND)
DELETE /api/v4/room/{token}/important  # Unmark important

POST /api/v4/room/{token}/sensitive    # Mark sensitive (hide preview)
DELETE /api/v4/room/{token}/sensitive  # Unmark sensitive
```

---

### Notification Settings

```http
POST /api/v4/room/{token}/notify
```

**Request Body:**

```json
{
  "level": 2
}
```

| Level | Description |
|-------|-------------|
| `0` | Default |
| `1` | Always notify |
| `2` | Notify on mention |
| `3` | Never notify |

```http
POST /api/v4/room/{token}/notify-calls
```

| Level | Description |
|-------|-------------|
| `0` | Off |
| `1` | On |

---

### Lobby Settings

```http
PUT /api/v4/room/{token}/webinar/lobby
```

**Request Body:**

```json
{
  "state": 1,
  "timer": 1704067200
}
```

| State | Description |
|-------|-------------|
| `0` | No lobby |
| `1` | Lobby for non-moderators |

---

### Permissions

```http
PUT /api/v4/room/{token}/permissions/{mode}
```

**Path Parameters:**

| Parameter | Values |
|-----------|--------|
| `mode` | `default`, `call` |

**Request Body:**

```json
{
  "permissions": 127
}
```

See [Permission Constants](#permission-constants) for bitmask values.

---

### Message Expiration

```http
POST /api/v4/room/{token}/message-expiration
```

**Request Body:**

```json
{
  "seconds": 86400
}
```

---

### Get Room Capabilities (Federated)

```http
GET /api/v4/room/{token}/capabilities
```

**Response:** `TalkCapabilities`

---

## Chat API (v1)

### Receive Messages (Polling)

```http
GET /api/v1/chat/{token}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `lookIntoFuture` | `0\|1` | - | `0` = history, `1` = new messages |
| `limit` | `integer` | `100` | Max 200 |
| `lastKnownMessageId` | `integer` | `0` | Pagination cursor |
| `timeout` | `integer` | `30` | Long-poll timeout (max 60) |
| `setReadMarker` | `0\|1` | `1` | Auto-update read marker |
| `includeLastKnown` | `0\|1` | `0` | Include boundary message |
| `markNotificationsAsRead` | `0\|1` | `1` | Clear notifications |
| `threadId` | `integer` | `0` | Filter by thread |

**Response Headers:**

| Header | Description |
|--------|-------------|
| `X-Chat-Last-Common-Read` | Last message read by all (public read status) |
| `X-Chat-Last-Given` | Last message ID in response |

**Response:** `TalkChatMessageWithParent[]`

**Status Codes:**

| Status | Description |
|--------|-------------|
| `200` | Messages returned |
| `304` | No new messages (long-poll timeout) |

---

### Send Message

```http
POST /api/v1/chat/{token}
```

**Request Body:**

```json
{
  "message": "Hello world!",
  "actorDisplayName": "Guest Name",
  "referenceId": "unique-client-id",
  "replyTo": 0,
  "silent": false,
  "threadId": 0,
  "threadTitle": ""
}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `message` | `string` | Message content (max 32000 chars) |
| `actorDisplayName` | `string` | Display name for guests |
| `referenceId` | `string` | Client-generated ID for deduplication |
| `replyTo` | `integer` | Parent message ID |
| `silent` | `boolean` | Don't trigger notifications |
| `threadId` | `integer` | Thread ID (requires `threads` capability) |
| `threadTitle` | `string` | Create new thread with title |

**Response:** `TalkChatMessageWithParent` (Status: 201)

**Error Codes:**

| Status | Description |
|--------|-------------|
| `400` | Sending not possible |
| `404` | Actor not found |
| `413` | Message too long |
| `429` | Mention rate limit exceeded |

---

### Edit Message

```http
PUT /api/v1/chat/{token}/{messageId}
```

**Request Body:**

```json
{
  "message": "Updated message"
}
```

---

### Delete Message

```http
DELETE /api/v1/chat/{token}/{messageId}
```

**Response:** `TalkChatMessageWithParent` (deleted message placeholder)

---

### Get Message Context

```http
GET /api/v1/chat/{token}/{messageId}/context
```

**Query Parameters:**

| Parameter | Type | Default |
|-----------|------|---------|
| `limit` | `integer` | `50` (max 100) |
| `threadId` | `integer` | `0` |

---

### Share Rich Object

```http
POST /api/v1/chat/{token}/share
```

**Request Body:**

```json
{
  "objectType": "file",
  "objectId": "123",
  "metaData": "{}",
  "referenceId": "",
  "threadId": 0
}
```

---

### Clear Chat History

```http
DELETE /api/v1/chat/{token}
```

**Requires:** Moderator

---

### Read Marker

```http
POST /api/v1/chat/{token}/read         # Set read marker
DELETE /api/v1/chat/{token}/read       # Mark as unread
```

**Request Body (POST):**

```json
{
  "lastReadMessage": 12345
}
```

---

### Mentions Search

```http
GET /api/v1/chat/{token}/mentions?search={query}&limit=20&includeStatus=true
```

**Response:** `TalkChatMentionSuggestion[]`

---

### Shared Items

```http
GET /api/v1/chat/{token}/share/overview?limit=7
GET /api/v1/chat/{token}/share?objectType=file&limit=100&lastKnownMessageId=0
```

**Object Types:** `file`, `audio`, `voice`, `video`, `location`, `deckcard`, `other`, `poll`, `recording`

---

### Pin/Unpin Messages

```http
POST /api/v1/chat/{token}/{messageId}/pin
DELETE /api/v1/chat/{token}/{messageId}/pin
DELETE /api/v1/chat/{token}/{messageId}/pin/self  # Hide for self
```

**Requires:** Moderator (for pin/unpin)

**Request Body (POST):**

```json
{
  "pinUntil": 1704153600
}
```

---

### Message Reminders

```http
POST /api/v1/chat/{token}/{messageId}/reminder
GET /api/v1/chat/{token}/{messageId}/reminder
DELETE /api/v1/chat/{token}/{messageId}/reminder
GET /api/v1/chat/upcoming-reminders
```

**Request Body (POST):**

```json
{
  "timestamp": 1704153600
}
```

---

### Scheduled Messages

```http
GET /api/v1/chat/{token}/schedule
POST /api/v1/chat/{token}/schedule
POST /api/v1/chat/{token}/schedule/{messageId}    # Edit
DELETE /api/v1/chat/{token}/schedule/{messageId}
```

**Requires:** `scheduled-messages` capability

---

### Summarize Chat

```http
POST /api/v1/chat/{token}/summarize
```

**Request Body:**

```json
{
  "fromMessageId": 12345
}
```

**Requires:** `chat-summary-api` capability

---

## Participants API (v4)

### Get Participants

```http
GET /api/v4/room/{token}/participants?includeStatus=true
```

**Response Headers:**

| Header | Description |
|--------|-------------|
| `X-Nextcloud-Has-User-Statuses` | Boolean indicating status availability |

**Response:** `TalkParticipant[]`

---

### Add Participant

```http
POST /api/v4/room/{token}/participants
```

**Requires:** Logged-in Moderator

**Request Body:**

```json
{
  "newParticipant": "user-id",
  "source": "users"
}
```

**Sources:** `users`, `groups`, `circles`, `emails`, `federated_users`, `phones`, `teams`

---

### Remove Participant

```http
DELETE /api/v4/room/{token}/attendees?attendeeId={id}
```

**Requires:** Moderator

---

### Remove Self

```http
DELETE /api/v4/room/{token}/participants/self
```

---

### Join Room (Create Session)

```http
POST /api/v4/room/{token}/participants/active
```

**Request Body:**

```json
{
  "password": "",
  "force": true
}
```

**Response:** `TalkRoom`

**Special Response (409 Conflict):**

```json
{
  "sessionId": "existing-session-id",
  "inCall": 0,
  "lastPing": 1704067200
}
```

---

### Leave Room

```http
DELETE /api/v4/room/{token}/participants/active
```

---

### Set Session State

```http
PUT /api/v4/room/{token}/participants/state
```

**Request Body:**

```json
{
  "state": 1
}
```

| State | Description |
|-------|-------------|
| `0` | Inactive |
| `1` | Active |

---

### Promote/Demote Moderator

```http
POST /api/v4/room/{token}/moderators     # Promote
DELETE /api/v4/room/{token}/moderators   # Demote
```

**Request Body/Params:**

```json
{
  "attendeeId": 123
}
```

---

### Set Attendee Permissions

```http
PUT /api/v4/room/{token}/attendees/permissions
```

**Request Body:**

```json
{
  "attendeeId": 123,
  "method": "set",
  "permissions": 127
}
```

| Method | Description |
|--------|-------------|
| `set` | Replace permissions |
| `add` | Add permissions (OR) |
| `remove` | Remove permissions (AND NOT) |

---

### Resend Invitations

```http
POST /api/v4/room/{token}/participants/resend-invitations
```

**Request Body:**

```json
{
  "attendeeId": 123
}
```

---

### Import Email Participants (CSV)

```http
POST /api/v4/room/{token}/import-emails
Content-Type: multipart/form-data
```

**Form Data:**

| Field | Type | Description |
|-------|------|-------------|
| `file` | `File` | CSV file |
| `testRun` | `boolean` | Validate only |

---

### Set Guest Name

```http
POST /api/v1/guest/{token}/name
```

**Request Body:**

```json
{
  "displayName": "Guest Name"
}
```

---

## Call API (v4)

### Get Peers in Call

```http
GET /api/v4/call/{token}
```

**Response:** `TalkCallPeer[]`

---

### Join Call

```http
POST /api/v4/call/{token}
```

**Request Body:**

```json
{
  "flags": 7,
  "silent": false,
  "recordingConsent": false,
  "silentFor": []
}
```

**In-Call Flags (bitmask):**

| Flag | Value | Description |
|------|-------|-------------|
| `DISCONNECTED` | `0` | Not in call |
| `IN_CALL` | `1` | In call |
| `WITH_AUDIO` | `2` | Audio enabled |
| `WITH_VIDEO` | `4` | Video enabled |
| `WITH_PHONE` | `8` | Phone/SIP |

---

### Update Call Flags

```http
PUT /api/v4/call/{token}
```

**Request Body:**

```json
{
  "flags": 3
}
```

---

### Leave Call

```http
DELETE /api/v4/call/{token}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `all` | `boolean` | End call for everyone (moderator only) |

---

### Ring Attendee

```http
POST /api/v4/call/{token}/ring/{attendeeId}
```

**Requires:** `START_CALL` permission

---

### SIP Dial-Out

```http
POST /api/v4/call/{token}/dialout/{attendeeId}
```

---

### Download Call Participants

```http
GET /api/v4/call/{token}/download?format=csv
```

**Requires:** Moderator, `download-call-participants` capability

---

## Signaling API (v3)

### Get Signaling Settings

```http
GET /api/v3/signaling/settings?token={token}
```

**Response:**

```json
{
  "signalingMode": "internal|external|conversation_cluster",
  "userId": "user-id",
  "server": "wss://signaling.example.com",
  "ticket": "auth-ticket",
  "helloAuthParams": {
    "1.0": { "userid": "...", "ticket": "..." },
    "2.0": { "token": "jwt-token" }
  },
  "stunservers": [{ "urls": ["stun:stun.example.com:443"] }],
  "turnservers": [{
    "urls": ["turn:turn.example.com:443"],
    "username": "...",
    "credential": "..."
  }],
  "sipDialinInfo": "..."
}
```

---

### Internal Signaling Messages

```http
POST /api/v3/signaling/{token}
GET /api/v3/signaling/{token}
```

**POST Body:**

```json
{
  "messages": "[{\"ev\":\"message\",\"fn\":\"{...}\",\"sessionId\":\"...\"}]"
}
```

---

### Backend Signaling (External HPB)

```http
POST /api/v3/signaling/backend
```

**Required Headers:**

| Header | Description |
|--------|-------------|
| `spreed-signaling-random` | Random seed |
| `spreed-signaling-checksum` | HMAC-SHA256 checksum |

---

## Polls API (v1)

### Create Poll

```http
POST /api/v1/poll/{token}
```

**Request Body:**

```json
{
  "question": "What's your favorite color?",
  "options": ["Red", "Blue", "Green"],
  "resultMode": 0,
  "maxVotes": 1,
  "draft": false,
  "threadId": 0
}
```

| resultMode | Description |
|------------|-------------|
| `0` | Public results |
| `1` | Hidden results |

---

### Get Poll

```http
GET /api/v1/poll/{token}/{pollId}
```

---

### Vote on Poll

```http
POST /api/v1/poll/{token}/{pollId}
```

**Request Body:**

```json
{
  "optionIds": [0, 2]
}
```

---

### Close/Delete Poll

```http
DELETE /api/v1/poll/{token}/{pollId}
```

---

### Poll Drafts

```http
GET /api/v1/poll/{token}/drafts
POST /api/v1/poll/{token}/draft/{pollId}   # Update draft
```

---

## Reactions API (v1)

### Add Reaction

```http
POST /api/v1/reaction/{token}/{messageId}
```

**Request Body:**

```json
{
  "reaction": "👍"
}
```

---

### Remove Reaction

```http
DELETE /api/v1/reaction/{token}/{messageId}?reaction=👍
```

---

### Get Reactions

```http
GET /api/v1/reaction/{token}/{messageId}?reaction=👍
```

**Response:**

```json
{
  "👍": [
    {
      "actorType": "users",
      "actorId": "user-id",
      "actorDisplayName": "User Name",
      "timestamp": 1704067200
    }
  ]
}
```

---

## Avatar API (v1)

### Upload Avatar (File)

```http
POST /api/v1/room/{token}/avatar
Content-Type: multipart/form-data
```

**Requires:** Moderator

---

### Set Emoji Avatar

```http
POST /api/v1/room/{token}/avatar/emoji
```

**Request Body:**

```json
{
  "emoji": "🚀",
  "color": "#FF5733"
}
```

---

### Get Avatar

```http
GET /api/v1/room/{token}/avatar?darkTheme=false
GET /api/v1/room/{token}/avatar/dark
```

---

### Delete Avatar

```http
DELETE /api/v1/room/{token}/avatar
```

---

### User Proxy Avatar (Federation)

```http
GET /api/v1/proxy/{token}/user-avatar/{size}?cloudId={cloudId}&darkTheme=false
GET /api/v1/proxy/new/user-avatar/{size}?cloudId={cloudId}&darkTheme=false
```

**Sizes:** `64`, `512`

---

## Bans API (v1)

### List Bans

```http
GET /api/v1/ban/{token}
```

**Requires:** Moderator

**Response:** `TalkBan[]`

---

### Ban Actor

```http
POST /api/v1/ban/{token}
```

**Request Body:**

```json
{
  "actorType": "users",
  "actorId": "user-id",
  "internalNote": "Reason for ban"
}
```

---

### Unban Actor

```http
DELETE /api/v1/ban/{token}/{banId}
```

---

## Bots API (v1)

### List Bots (Admin)

```http
GET /api/v1/bot/admin
```

**Requires:** Admin

---

### List Conversation Bots

```http
GET /api/v1/bot/{token}
```

**Requires:** Logged-in Moderator

---

### Enable/Disable Bot

```http
POST /api/v1/bot/{token}/{botId}     # Enable
DELETE /api/v1/bot/{token}/{botId}   # Disable
```

---

### Bot Send Message (Bot Auth)

```http
POST /api/v1/bot/{token}/message
```

**Request Body:**

```json
{
  "message": "Bot message",
  "referenceId": "",
  "replyTo": 0,
  "silent": false
}
```

---

### Bot React (Bot Auth)

```http
POST /api/v1/bot/{token}/reaction/{messageId}
DELETE /api/v1/bot/{token}/reaction/{messageId}
```

---

## Breakout Rooms API (v1)

### Configure Breakout Rooms

```http
POST /api/v1/breakout-rooms/{token}
```

**Request Body:**

```json
{
  "mode": 1,
  "amount": 3,
  "attendeeMap": "{}"
}
```

| Mode | Description |
|------|-------------|
| `0` | Not configured |
| `1` | Automatic |
| `2` | Manual |
| `3` | Free |

---

### Delete Breakout Rooms

```http
DELETE /api/v1/breakout-rooms/{token}
```

---

### Get Breakout Rooms

```http
GET /api/v4/room/{token}/breakout-rooms
GET /api/v4/room/{token}/breakout-rooms/participants
```

---

### Start/Stop Breakout Rooms

```http
POST /api/v1/breakout-rooms/{token}/rooms     # Start
DELETE /api/v1/breakout-rooms/{token}/rooms   # Stop
```

---

### Broadcast Message

```http
POST /api/v1/breakout-rooms/{token}/broadcast
```

**Request Body:**

```json
{
  "message": "Announcement to all rooms"
}
```

---

### Request/Reset Assistance

```http
POST /api/v1/breakout-rooms/{token}/request-assistance
DELETE /api/v1/breakout-rooms/{token}/request-assistance
```

---

### Switch Breakout Room

```http
POST /api/v1/breakout-rooms/{token}/switch
```

**Request Body:**

```json
{
  "target": "target-room-token"
}
```

---

## Federation API (v1)

### Get Federation Invitations

```http
GET /api/v1/federation/invitation
```

**Response:** `TalkFederationInvite[]`

---

### Accept/Reject Invitation

```http
POST /api/v1/federation/invitation/{id}     # Accept
DELETE /api/v1/federation/invitation/{id}   # Reject
```

---

## Recording API (v1)

### Start Recording

```http
POST /api/v1/recording/{token}
```

**Request Body:**

```json
{
  "status": 1
}
```

| Status | Description |
|--------|-------------|
| `1` | Video recording |
| `2` | Audio recording |

---

### Stop Recording

```http
DELETE /api/v1/recording/{token}
```

---

### Share Recording to Chat

```http
POST /api/v1/recording/{token}/share-chat
```

**Request Body:**

```json
{
  "fileId": 123,
  "timestamp": 1704067200
}
```

---

### Dismiss Recording Notification

```http
DELETE /api/v1/recording/{token}/notification?timestamp={timestamp}
```

---

## Threads API (v1)

### Get Recent Active Threads

```http
GET /api/v1/chat/{token}/threads/recent?limit=50
```

---

### Get Single Thread

```http
GET /api/v1/chat/{token}/threads/{threadId}
```

---

### Rename Thread

```http
PUT /api/v1/chat/{token}/threads/{threadId}
```

**Request Body:**

```json
{
  "threadTitle": "New Thread Title"
}
```

---

### Set Thread Notification Level

```http
POST /api/v1/chat/{token}/threads/{messageId}/notify
```

**Request Body:**

```json
{
  "level": 1
}
```

---

### Get Subscribed Threads

```http
GET /api/v1/chat/subscribed-threads?limit=100&offset=0
```

---

## Data Types

### TalkRoom

```typescript
interface TalkRoom {
  id: number;                    // Internal database ID
  token: string;                 // API identifier (use this!)
  type: number;                  // Conversation type
  name: string;                  // Room name
  displayName: string;           // Computed display name
  description: string;           // Room description

  // Current user context
  actorType: string;
  actorId: string;
  attendeeId: number;
  participantType: number;       // Role in room
  permissions: number;           // Effective permissions
  attendeePermissions: number;   // Dedicated permissions

  // Room state
  hasPassword: boolean;
  hasCall: boolean;
  callFlag: number;              // Combined participant flags
  callStartTime: number;
  callRecording: number;

  // Settings
  readOnly: number;
  listable: number;
  lobbyState: number;
  lobbyTimer: number;
  sipEnabled: number;
  mentionPermissions: number;
  messageExpiration: number;
  recordingConsent: number;

  // Chat state
  lastActivity: number;
  lastMessage?: TalkChatMessage;
  lastReadMessage: number;
  lastCommonReadMessage: number;
  unreadMessages: number;
  unreadMention: boolean;
  unreadMentionDirect: boolean;

  // Capabilities
  canStartCall: boolean;
  canLeaveConversation: boolean;
  canDeleteConversation: boolean;
  canEnableSIP: boolean;

  // Status (1-1 only)
  status?: string;
  statusIcon?: string;
  statusMessage?: string;
  statusClearAt?: number;

  // Federation
  remoteServer?: string;
  remoteToken?: string;

  // Flags
  isFavorite: boolean;
  isArchived: boolean;
  isImportant: boolean;
  isSensitive: boolean;
}
```

### TalkChatMessage

```typescript
interface TalkChatMessage {
  id: number;
  token: string;
  actorType: string;
  actorId: string;
  actorDisplayName: string;
  timestamp: number;
  message: string;
  messageType: string;
  systemMessage: string;
  messageParameters: Record<string, TalkRichObjectParameter>;

  // Features
  isReplyable: boolean;
  markdown: boolean;
  referenceId: string;
  expirationTimestamp: number;

  // Reactions
  reactions: Record<string, number>;
  reactionsSelf?: string[];

  // Editing
  lastEditActorType?: string;
  lastEditActorId?: string;
  lastEditActorDisplayName?: string;
  lastEditTimestamp?: number;

  // Threads
  threadId?: number;
  isThread?: boolean;
  threadTitle?: string;
  threadReplies?: number;

  // Parent (for replies)
  parent?: TalkChatMessage | { id: number; deleted: true };

  // Metadata
  metaData?: {
    pinnedActorType?: string;
    pinnedActorId?: string;
    pinnedActorDisplayName?: string;
    pinnedAt?: number;
    pinnedUntil?: number;
  };
}
```

### TalkParticipant

```typescript
interface TalkParticipant {
  actorType: string;
  actorId: string;
  displayName: string;
  attendeeId: number;
  participantType: number;
  permissions: number;
  attendeePermissions: number;
  attendeePin: string;
  roomToken: string;
  sessionIds: string[];
  inCall: number;
  lastPing: number;

  // Optional status
  status?: string;
  statusIcon?: string;
  statusMessage?: string;
  statusClearAt?: number;

  // Phone participants
  phoneNumber?: string;
  callId?: string;
}
```

---

## Constants

### Conversation Types

| Value | Constant | Description |
|-------|----------|-------------|
| `1` | `TYPE_ONE_TO_ONE` | 1-1 conversation |
| `2` | `TYPE_GROUP` | Group conversation |
| `3` | `TYPE_PUBLIC` | Public conversation |
| `4` | `TYPE_CHANGELOG` | Changelog |
| `5` | `TYPE_ONE_TO_ONE_FORMER` | Former 1-1 |
| `6` | `TYPE_NOTE_TO_SELF` | Note to self |

### Participant Types

| Value | Constant | Description |
|-------|----------|-------------|
| `1` | `OWNER` | Owner |
| `2` | `MODERATOR` | Moderator |
| `3` | `USER` | User |
| `4` | `GUEST` | Guest |
| `5` | `USER_SELF_JOINED` | Self-joined user |
| `6` | `GUEST_MODERATOR` | Guest moderator |

### Permission Constants

| Value | Constant | Description |
|-------|----------|-------------|
| `0` | `DEFAULT` | Inherit default |
| `1` | `CUSTOM` | Custom permissions |
| `2` | `CALL_START` | Can start call |
| `4` | `CALL_JOIN` | Can join call |
| `8` | `LOBBY_IGNORE` | Can bypass lobby |
| `16` | `PUBLISH_AUDIO` | Can send audio |
| `32` | `PUBLISH_VIDEO` | Can send video |
| `64` | `PUBLISH_SCREEN` | Can share screen |
| `128` | `CHAT` | Can post messages |
| `256` | `FILE_SHARE` | Can share files |

### Actor Types

| Value | Description |
|-------|-------------|
| `users` | Nextcloud users |
| `guests` | Guest users |
| `emails` | Email invitees |
| `groups` | User groups |
| `circles` | Nextcloud circles |
| `bots` | Bot accounts |
| `bridged` | Matterbridge users |
| `federated_users` | Federated users |
| `phones` | Phone participants |

### Message Types

| Value | Description |
|-------|-------------|
| `comment` | Regular message |
| `system` | System message |
| `command` | Command message |
| `comment_deleted` | Deleted message |
| `voice-message` | Voice message |
| `record-audio` | Audio recording |
| `record-video` | Video recording |
| `poll` | Poll message |

### Notification Levels

| Value | Description |
|-------|-------------|
| `0` | Default |
| `1` | Always notify |
| `2` | Mention only |
| `3` | Never notify |

### Read-Only States

| Value | Description |
|-------|-------------|
| `0` | Read-write |
| `1` | Read-only |

### Lobby States

| Value | Description |
|-------|-------------|
| `0` | No lobby |
| `1` | Lobby enabled |

### SIP States

| Value | Description |
|-------|-------------|
| `0` | Disabled |
| `1` | Enabled (no PIN) |
| `2` | Enabled (with PIN) |

### Recording Status

| Value | Description |
|-------|-------------|
| `0` | No recording |
| `1` | Recording video |
| `2` | Recording audio |
| `3` | Starting video recording |
| `4` | Starting audio recording |
| `5` | Recording failed |

---

## Error Handling

### Common HTTP Status Codes

| Status | Meaning |
|--------|---------|
| `200` | Success |
| `201` | Created |
| `202` | Accepted (async processing) |
| `304` | Not Modified (long-poll timeout) |
| `400` | Bad Request |
| `401` | Unauthorized |
| `403` | Forbidden |
| `404` | Not Found |
| `405` | Method Not Allowed |
| `409` | Conflict |
| `412` | Precondition Failed |
| `413` | Payload Too Large |
| `429` | Rate Limited |
| `501` | Not Implemented |

### Brute Force Protection

Response header when rate-limited:
```http
X-Nextcloud-Bruteforce-Throttled: true
```

---

## Frontend Service Reference

The Vue.js frontend uses these service files:

| Service | File | Purpose |
|---------|------|---------|
| Conversations | `conversationsService.ts` | Room CRUD operations |
| Messages | `messagesService.ts` | Chat messaging |
| Participants | `participantsService.js` | Participant management |
| Calls | `callsService.ts` | Call operations |
| Polls | `pollService.ts` | Poll management |
| Reactions | `reactionsService.ts` | Message reactions |
| Avatars | `avatarService.ts` | Avatar management |
| Bans | `banService.ts` | Ban management |
| Bots | `botsService.ts` | Bot configuration |
| Recording | `recordingService.js` | Recording control |
| Federation | `federationService.ts` | Federation invites |
| Breakout Rooms | `breakoutRoomsService.ts` | Breakout room management |

---

*Generated from Nextcloud Talk (spreed) source code analysis*
