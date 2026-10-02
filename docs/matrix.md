# Matrix rooms in Talk

Talk can show and use rooms of a [Matrix](https://matrix.org) account as conversations. Talk's
server acts as a Matrix *client* (one device per linked Nextcloud user): it syncs the rooms of the
linked account into ordinary Talk conversations, so the web interface as well as the unchanged
mobile and desktop apps work with them.

Status: **phase 4** – everything below plus: create Matrix rooms and direct chats from Talk,
join rooms by address or from the public directory (*Matrix room …* in the conversation-list
menu), restore encrypted history from the key backup (verified device or recovery key),
mentions of Matrix-only members, `occ talk:matrix:rekey`. Phase 3 – everything from phases 1 and 2 plus reactions, edits, deletions and
threads in both directions, read receipts to Matrix, inline media with thumbnails, "Save to
Nextcloud" and uploads of shared files (also into encrypted rooms). Phase 2 – everything from phase 1 (account linking, rooms read/write, replies,
invitations, membership, power levels, room name/topic) plus **end-to-end encrypted rooms**:
Talk is a real Matrix E2EE device (Olm/Megolm implemented in PHP, interoperable with
Element/vodozemac), decrypts incoming messages when they arrive, encrypts outgoing ones and
shares room keys with the members' devices, decrypts encrypted attachments, and can be
verified from another client via emoji (SAS). Media upload, reactions/edits/threads and room
creation follow in later phases. See `docs/matrix/SPEC.md` for the full design.

## Administration

1. In *Administration settings → Talk → Matrix rooms* enable the feature.
2. Add the homeserver(s) people may link accounts on. Talk resolves the server name via
   `.well-known/matrix/client` and validates `/_matrix/client/versions`; a client API URL can be set
   manually. Only these servers are ever contacted.
3. Optionally restrict linking to groups and tune the synchronisation settings.

Synchronisation runs in the background job `MatrixSync` every time cron runs (interval 10 s, budget
25 s per run, per-account locks). Real latency is bounded by how often cron runs – **run cron every
minute**. When a person looks at a Matrix conversation in a client, Talk additionally syncs inline if
the account's data is older than *foreground sync age* (default 5 s), so the web UI feels near-real-time.

`occ` commands:

```
occ talk:matrix:homeserver add example.org [--name=Label] [--base-url=https://matrix.example.org]
occ talk:matrix:homeserver list|remove|test <server-name>
occ talk:matrix:account list
occ talk:matrix:account sync --user=<uid> [--budget=20]
occ talk:matrix:account unlink --user=<uid>
occ talk:matrix:status [--output=json]        # exit code 2 when accounts need a re-login
occ talk:matrix:account request-keys --user=<uid>   # ask the other devices for missing room keys again
occ talk:matrix:rekey --old-secret=<previous config.php secret> [--dry-run]   # after rotating "secret"
```

App config keys (`occ config:app:set spreed <key>`): `matrix_enabled`, `matrix_allowed_groups`
(JSON list), `matrix_sync_interval` (10–300 s, default 30), `matrix_idle_sync_interval` (default
120), `matrix_max_parallel_syncs` (4), `matrix_foreground_sync_age` (5), `matrix_history_events`
(200), `matrix_history_days` (30), `matrix_max_upload` (bytes), `matrix_typing_in`, `matrix_typing_out`,
`matrix_e2ee_shared_lookup`, `matrix_e2ee_verified_only`.

## Linking an account

In Talk → Settings → *Matrix account* a person picks a homeserver and enters their Matrix username
and password. Talk performs a `m.login.password` login once, stores only the access token (encrypted
with the instance secret) and device id, and registers as a device named `Nextcloud Talk (<instance>)`.
Rooms are imported immediately (bounded) and then kept in sync. If the homeserver invalidates the
token, the person gets a notification and can log in again; the device id is reused.

Unlinking logs the device out on the homeserver, deletes the stored token and removes the person from
their Matrix conversations. Conversations without any remaining linked Nextcloud user are deleted.

## End-to-end encryption

* On linking, Talk creates an Olm account for the new device and uploads its device keys plus
  one-time keys. Key material and tokens are stored encrypted with the instance secret in the
  `talk_matrix_*` crypto tables; **decrypted messages are stored like any other Talk message**
  (this is what makes search, previews and all clients work — the Nextcloud server is inside
  the trust boundary, see the spec).
* Incoming encrypted events are decrypted during sync. When the key is missing the message is
  shown as "🔒 Encrypted message – waiting for the key", a key request is sent to the person's
  other devices and to the sender, and the placeholder is replaced in place once the key arrives.
* Sending creates/rotates a Megolm session per room (rotation by age/message count/membership
  change) and shares it via Olm with every device of every member (unless the device is blocked,
  or the admin enabled *only trusted devices*).
* **Verify the Talk device** in Talk → Settings → Matrix account → *Verify this device*: the
  request appears on the other Matrix clients (Element etc.), both sides compare seven emoji,
  then the other client cross-signs the Talk device and shares keys for rooms it knows. Until
  a device is verified, other clients may refuse to share keys with it.
* Key requests from the person's *own* devices are answered only when that device is
  cross-signed or was verified. Forwarded keys are accepted only from such devices.
* Admin policy per homeserver: *Allow encrypted rooms* off → Talk neither decrypts nor sends
  into encrypted rooms (placeholders only). `matrix_e2ee_shared_lookup` (default on) lets a
  message be decrypted with another linked Nextcloud user's copy of the key when they are in the
  same room; `matrix_e2ee_verified_only` restricts key sharing to cross-signed/verified devices.
* **Encrypted history from before the link**: Talk → Settings → Matrix account → *Restore
  encrypted history*. After a verification the other client usually hands over the backup key
  automatically; otherwise paste the recovery key (`EsTc …`). Talk downloads the server-side key
  backup, imports the sessions and replaces the placeholders.
* Not yet: verifying *other* users, creating cross-signing keys.

## How rooms map to conversations

| Matrix | Talk |
|---|---|
| joined room | group conversation with `objectType = "matrix"`, `objectId = <room id>` |
| room name / canonical alias / heroes | conversation name (Matrix room-name algorithm) |
| topic | description |
| `m.room.avatar` (or the peer's avatar in a direct chat) | conversation avatar |
| linked Nextcloud users | `users` attendees |
| other members | attendees with actor type `matrix` (actor id = Matrix user id, display name and avatar from Matrix; avatar served by `/apps/spreed/matrix/avatar/{token}/{size}/{mxid}`) |
| power level ≥ 50 / creator | moderator / owner |
| `m.room.message` text/notice/emote | chat message (HTML → Markdown, pills → mentions) |
| `m.image`/`m.file`/… | `matrix-media` rich object (link served by `/apps/spreed/matrix/media/{eventId}`, `?thumbnail=1` for previews, `POST …/save` copies it into the user's files) |
| `m.in_reply_to` | reply |
| `m.thread` | Talk thread (created on first use) |
| `m.reaction` / redaction of one | reaction added / removed |
| `m.replace` (edit by the original sender) | message edited |
| `m.room.redaction` | message deleted (Talk moderator rules do not apply – Matrix power levels do) |
| Nextcloud file shared into the conversation | uploaded to the homeserver and posted as `m.file`/`m.image`/… (encrypted attachment in encrypted rooms) |
| membership changes | system messages (`matrix_user_added`, `matrix_user_removed` for Matrix-only members) |
| `m.room.tombstone` | conversation becomes read-only, `matrix_room_upgraded` |
| calls | `matrix_call_unsupported` system message |
| invite | pending invitation in the federation invitation API (`remoteServerUrl` = `matrix:<server>`) |

Calls, lobby, SIP, listable, password, read-only toggle, message expiration, recording consent and
permission changes are rejected with HTTP 405 `matrix-unsupported`. Renaming, topic, invites, kicks,
promotions, leaving, reactions, edits, deletions and thread replies are executed on the Matrix side
first (the homeserver enforces power levels); the Talk read marker is mirrored as an `m.read` receipt.

## API additions

* Capabilities: feature `matrix-rooms` (local), `config.matrix` with `enabled`, `can-link`, `linked`,
  `homeservers`, `e2ee-enabled`, `upload-enabled`, `typing-enabled`.
* Conversation objects of Matrix rooms carry `matrixRoomId` and `matrixCapabilities` (room-level
  capabilities plus the current user's power-level dependent ones, e.g. `canSend`, `canInvite`,
  `canRename`, `isModerator`, `isDirect`, `encrypted`).
* `GET|POST|PUT|DELETE /ocs/v2.php/apps/spreed/api/v1/matrix/account`, `POST …/matrix/account/sync`
* Verification: `POST|GET|PUT|DELETE …/matrix/account/verification` (start, poll, confirm/reject, cancel)
* Key backup: `POST …/matrix/account/backup` (`recoveryKey` optional)
* Rooms: `POST …/matrix/room` (create/DM), `POST …/matrix/room/join` (`reference`), `GET …/matrix/room/directory`
* Administration: `…/matrix/admin/homeserver[/{id}[/test]]`, `PUT …/matrix/admin/settings`, `GET …/matrix/admin/status`
* Adding a participant to a Matrix conversation accepts `source=users` (linked Nextcloud user) or
  `source=matrix` with a Matrix user id.

## The Matrix client library

The protocol implementation lives in `matrix-client/` as the standalone, framework-free package
`nextcloud/matrix-client-php` (namespace `Nextcloud\Matrix`). It has no Nextcloud dependency and
its own test-suite; `examples/echo-bot.php` runs against a plain Synapse. Talk-specific code lives
in `lib/Matrix/` (`OCA\Talk\Matrix`).
