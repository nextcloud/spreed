# Nextcloud Talk – Matrix Rooms Specification

Status: Draft 1 (2026-08-29), derived from a stakeholder interview. Intended as the
implementation hand-off for Claude Code. Target: `apps/spreed` 26.x (Nextcloud 35).

**Implementation status (2026-08-30): Phase 1 implemented** – library `matrix-client/`
(`nextcloud/matrix-client-php`, 36 unit tests, Synapse integration suite), Talk glue in
`lib/Matrix/`, migration `Version25000Date20260829120000`, controllers, guards, settings UI,
occ commands, docs. Deviations from this draft, decided during implementation:
- One `MatrixSync` TimedJob loops over all due accounts (per-account DB lock, 25 s budget,
  adaptive idle interval) instead of one registered job per account – fewer job-list rows,
  same semantics. The foreground sync (§7.3) is implemented in `ChatController::receiveMessages`.
- Matrix-only members that join/leave produce `matrix_user_added` / `matrix_user_removed`
  system messages; linked users reuse Talk's `user_added` / `user_removed` via the attendee events.
- Encrypted events are stored as bookkeeping rows with a placeholder message
  ("encrypted Matrix rooms are not supported yet") until phase 2; sending into encrypted
  rooms is refused (`e2ee-unsupported`).
- Media: incoming attachments are mirrored as `matrix-media` rich objects with a download
  link (`/apps/spreed/matrix/media/{eventId}`, unencrypted only); uploads come in phase 3.
- Reactions, edits, redactions and threads are recorded in `talk_matrix_events` but not
  applied yet (phase 3). Read receipts *from* Matrix are applied; receipts *to* Matrix are
  wired in `SendService::sendReadReceipt` but not yet called from the read-marker endpoint.
- Not yet done in phase 1: Behat/Synapse integration test in spreed's CI, OpenAPI
  regeneration (`composer openapi`), mobile-app smoke test (A5/C4).

**Phase 2 implemented (2026-08-30):** pure-PHP Olm/Megolm in the library
(`Nextcloud\Matrix\Crypto`), verified byte-for-byte against vodozemac (Element's library) via
`tests/interop/vodozemac_check.py` (Olm both directions incl. ratchet advance and out-of-order
delivery, Megolm both directions incl. part rollovers and export/import, device-key
signatures, SAS bytes/emoji/decimals/MACs). Talk side: crypto tables migration, encrypted
`CryptoStore`, `CryptoService` (bootstrap, to-device, key requests, forwarding to own
cross-signed devices, verification), decrypt-on-ingest with in-place placeholder
replacement, encrypted sending with key sharing, encrypted attachment download, SAS
verification UI. Deviations/limits: cross-signing keys are not *created* by Talk (the device
gets signed by another client); key backup (§9.8) still phase 4; `ICryptoBackend`
swap point exists as the `Machine`/session classes but no FFI backend; B6 perf budget not
yet measured on a large room.

**Phase 3 implemented (2026-08-30):** reactions, edits and redactions in both directions
(`EventMapper::applyReaction/applyEdit/applyRedaction`, `SendService::addReaction/
removeReaction/editMessage/deleteMessage`; reactions are sent unencrypted like Element does,
edits/deletes honour Matrix power levels, incoming edits only from the original sender),
threads both ways (`m.thread` ↔ Talk threads; the Talk thread is created on first use, thread
replies from Talk carry `m.thread` + `m.in_reply_to`), Talk → Matrix read receipts
(`ChatController::setReadMarker`, throttled 5 s per room, never regressing), media: thumbnails
(`?thumbnail=1`, homeserver thumbnail or sender-provided one), "Save to Nextcloud"
(`POST /apps/spreed/matrix/media/{eventId}/save`, default folder `Talk`), uploads of Nextcloud
files shared into a Matrix conversation (`FileShareListener` on `file_shared`, plain or as
encrypted attachment, `m.image/m.video/m.audio/m.file` by mime type, size-capped by
`matrix_max_upload` and the homeserver's `allow_upload`), and the web rendering of
`matrix-media` (inline images with thumbnail, video/audio players, file card, lock mark for
decrypted attachments, save button). Not done in phase 3: incoming typing indicators (needs a
push source to be useful — deferred to the appservice/HPB work in phase 4), mention
autocomplete of Matrix-only members, encrypted thumbnails (encrypted images are shown in full
size), C4 mobile smoke test.

**Phase 4 implemented (2026-08-30):** room lifecycle from Talk — `POST …/matrix/room` (create
room or DM, encryption/public flags, invitees by Matrix id or Nextcloud user), `POST
…/matrix/room/join` (id, alias, matrix.to, `matrix:` URIs, user links → DM; knock-only rooms get
a knock and HTTP 202), `GET …/matrix/room/directory` (public rooms of the homeserver) and the
web dialog *Matrix room …* in the conversation-list menu; key backup restore
(`Nextcloud\Matrix\Crypto\Backup`: PkDecryption for `m.megolm_backup.v1.curve25519-aes-sha2`
— MAC over the ciphertext as vodozemac does, libolm's empty-string MAC accepted on read —,
recovery-key decoding, SSSS secret decryption; `POST …/matrix/account/backup` with the key
from a verified device or a typed recovery key, placeholders replaced after import); mention
autocomplete for Matrix-only members (`matrix_users` result type, mention id
`matrix_user/@user:server`); `occ talk:matrix:rekey --old-secret=…`. Already delivered in
earlier phases: adaptive polling, health panel, cleanup job, room upgrades, moderation actions.
Room avatars (§8.2 `m.room.avatar`) are mirrored into the Talk avatar via the homeserver
thumbnail endpoint, DMs fall back to the peer's avatar; the applied mxc is remembered in the
room capabilities (`avatarMxc`) so pictures are fetched only on change. Matrix-only members'
avatars (§11) are served by `GET /apps/spreed/matrix/avatar/{token}/{64|512}/{mxid}` (thumbnail
through the viewer's homeserver, person placeholder as fallback); `AvatarWrapper.vue` treats the
`matrix` actor type like federated users. `RoomController::getParticipants` needed an explicit
`matrix` branch – unknown actor types are listed with the actor id as display name.
Not done: moving the library to its own repository / tagging 1.0.0 (needs the GitHub repo to
be created — the code is release-ready under `matrix-client/`), Application-Service / HPB push
source (§7.9, design only), incoming typing, room avatar changes from Talk, encrypted
thumbnails, Behat/Synapse CI in spreed, OpenAPI regeneration, mobile smoke test.

**Follow-ups after the first live E2EE test (2026-08-30):**
- `Machine::publishKeys()` publishes the device keys on the *first* upload regardless of the
  calling path (the one-time-key top-up in `processSync` could otherwise run before the
  bootstrap and leave a device without published keys — other clients then ignore its
  verification requests). Tracked with the `device_keys_published` store secret.
- `SyncService` bootstraps E2EE for accounts linked before phase 2 (`olm_account` empty).
- `occ talk:matrix:account request-keys --user=…` and automatic re-requests of all missing
  session keys once the device was verified (verified/cross-signed devices answer them).
- Conversation objects of encrypted rooms carry `matrixCapabilities.deviceVerified`
  (viewer's Talk device verified or cross-signed), cached per request.
- Web UI (§17 items 3/5/6/11, first set): `[m]` avatar badge for Matrix rooms, lock badge
  for encrypted rooms (amber while the device is unverified), `Matrix` / `Matrix · Encrypted`
  chip in the conversation header with alias/homeserver tooltip, lock icon in the composer,
  and a warning card above the message list with a *Verify device* button while unverified.
  Still open from §17: alias/MXID subline in the list, "Matrix" list filter, "About this room"
  sidebar block, "Verify" link on placeholder messages, `mention-matrix` pills, media rendering.
- Deployment note: NC 35 has no `migrations:execute` outside debug mode and `occ upgrade`
  skips unchanged app versions — pending spreed migrations are applied with
  `new \OC\DB\MigrationService('spreed', $connection)->migrate()`.

---

## 0. Summary of decisions (interview outcome)

| Topic | Decision |
|---|---|
| Where the Matrix client lives | **Server-side in PHP inside spreed.** Talk's backend is a full Matrix client (Client-Server API). Mobile/desktop apps talk to the unchanged Talk OCS API. |
| Identity | **One Matrix account per Nextcloud user.** Users link an account on an admin-configured homeserver with username + password (`m.login.password`); only the access token + device ID are stored, never the password. |
| Homeservers | **Admin-configured list only.** Users cannot enter arbitrary homeservers. |
| Room scope | **All rooms of the linked account** are mirrored into Talk (DMs, groups, public rooms). Spaces are flattened (see §12.6). |
| Sync | **Background-job polling of `/sync` now**, with an ingestion abstraction so an Application-Service push source or an HPB-hosted sync worker can be plugged in later. Plus opportunistic foreground sync when a client is actively looking at a Matrix conversation. |
| Storage | **Matrix events are mirrored into Talk's normal message storage** (`oc_comments` via `ChatManager`) with an event-ID mapping table, so every existing code path (OCS API, notifications, read markers, threads, mobile apps) works. |
| E2EE | **Encryption terminates in Talk's Matrix client.** Olm/Megolm implemented in **pure PHP** (sodium + openssl). Events are decrypted **once at ingestion** and the mirrored message row stores **plaintext**, exactly like an unencrypted room, so search, previews, notifications and all clients work identically. The Nextcloud DB is inside the trust boundary (revised 2026-08-29; earlier draft stored ciphertext). |
| Device trust | **Cross-signing supported**: the Talk device can be verified from another client (SAS/emoji) so other devices share room keys with it. |
| Room model | **One shared Talk conversation per Matrix room**, deduplicated by event ID, regardless of how many NC users are members. |
| Feature detection | **Per-room capability set** computed from room state (version, encryption, power levels, join rules) + homeserver `/versions`. Exposed on the conversation object; unsupported features are hidden using Talk's existing permission/read-only mechanisms so unchanged clients behave. |
| Calls | **No calls in Matrix rooms in v1** (call permissions stripped). |
| Media | **Both directions**: Matrix media (incl. encrypted attachments) proxied inline; Nextcloud file shares uploaded to the homeserver media repo. |
| Message features (v1) | Reactions, edits & deletions, replies & threads, read receipts, typing indicators (typing is best-effort, see §8.7). |
| Room lifecycle | Full: join/leave/accept-reject invites, create DM & rooms, invite by MXID, room directory/alias lookup. |
| Matrix-only participants | **New actor type `matrix`** with display name + avatar from Matrix. Unchanged clients fall back to guest-like rendering. |
| Power levels | Mapped to Talk moderator badge; moderation actions executed on the Matrix side, errors surfaced. No Talk-only moderator state. |
| History | Recent window on first sync (default 200 events / 30 days), older history lazily backfilled via `/messages` on scroll. |
| Clients | Existing released mobile/desktop apps must work **unchanged**. Client-side polish is out of scope of this spec. |
| Notifications | Reuse Talk's notification pipeline; per-conversation notification level initialised from Matrix push rules, then local. |
| Unlink / user deletion | Log out the Matrix device, wipe token + keys; keep mirrored messages if other NC users remain in the room, otherwise delete the conversation. |
| Admin UI | Homeservers + feature toggle, group restriction for linking, polling/rate knobs + sync health, policy toggles for E2EE rooms and file upload. |
| Formatting | Markdown ↔ `org.matrix.custom.html`, mention mapping both ways. |
| Testing | PHPUnit (mocked HTTP + crypto vectors) and CI integration tests against dockerised Synapse; Playwright/Cypress for web UI. |
| Delivery | Four phases, each shippable with acceptance criteria (§21). |
| Code layout | **The Matrix client is a standalone, framework-free PHP library** (`nextcloud/matrix-client-php`, PSR-4 `Nextcloud\Matrix\`) in its own directory/repository with no dependency on Nextcloud or Talk; spreed consumes it via Composer. Talk-specific glue lives in `OCA\Talk\Matrix`. |

---

## 1. Goals and non-goals

### 1.1 Goals
1. A Nextcloud user can link a Matrix account (on an admin-approved homeserver) and see all their Matrix rooms as conversations in Talk – web, Android, iOS and desktop – next to native Talk conversations.
2. Reading and writing in Matrix rooms from Talk is fully functional for text, formatted text, mentions, replies, threads, reactions, edits, deletions, files/images, read receipts and membership changes.
3. Encrypted Matrix rooms are readable and writable; the crypto terminates in Talk's server-side Matrix client. Decrypted content is stored like any other Talk message so search and every other feature work unchanged.
4. The Talk UI hides or disables anything a given Matrix room cannot do, driven by a computed per-room capability set.
5. Unchanged existing mobile/desktop apps keep working: Matrix rooms must be indistinguishable from ordinary conversations at the OCS API level except for additive fields.
6. Admins can control the feature (homeservers, who may link, policy toggles, operational knobs) and observe sync health.

### 1.2 Non-goals (v1)
- Audio/video calls in Matrix rooms (MatrixRTC / Element Call / legacy `m.call.*`). Call events are shown as system messages only.
- Bridging *native* Talk conversations to Matrix (the reverse direction). Matterbridge already covers that use case (`lib/MatterbridgeManager.php`, `docs/matterbridge.md`).
- Acting as a Matrix homeserver or federating server-to-server. Talk is a *client*.
- Matrix Spaces hierarchy UI (spaces are flattened).
- Polls (`m.poll.*`, MSC3381), location sharing, voice-message-specific rendering (rendered as plain files), stickers (rendered as images).
- Users choosing arbitrary homeservers, SSO login, Application-Service provisioning (designed for, not built – see §6.5 and §7.9).
- Changes inside the mobile/desktop client repositories.

---

## 2. Terminology
- **HS** – Matrix homeserver (Synapse in tests; any spec-compliant HS should work).
- **MXID** – Matrix user ID `@localpart:server`.
- **Linked user** – NC user with an active Matrix account link.
- **Matrix conversation** – Talk `Room` whose `object_type = 'matrix'` and `object_id = <matrix room id>`.
- **Sync source** – component that delivers Matrix events into the ingestion pipeline (v1: `/sync` poller).
- **Talk device** – the Matrix device (device ID + Olm identity keys) owned by one linked user's Talk link.

---

## 3. Architecture overview

```
 ┌──────────────┐   OCS /apps/spreed/api   ┌──────────────────────────────────────┐
 │ Talk web /   │ ◄──────────────────────► │ spreed (PHP)                          │
 │ mobile /     │                          │                                       │
 │ desktop      │                          │  Controllers (unchanged public API)   │
 └──────────────┘                          │        │                              │
                                           │  ChatManager / RoomService / ...      │
                                           │        │            ▲                 │
                                           │  MatrixRoomService  │ (mirror)        │
                                           │  MatrixSendService  │                 │
                                           │        │       IngestionPipeline      │
                                           │        │            ▲                 │
                                           │  MatrixHttpClient   │  SyncSource     │
                                           │  (C-S API, retry)   │  ├ SyncPoller   │
                                           │        │            │  └ (AS push,    │
                                           │  Crypto (Olm/Megolm │     later)      │
                                           │  pure PHP, keys     │                 │
                                           │  encrypted at rest) │                 │
                                           └────────┼────────────┼─────────────────┘
                                                    ▼            │
                                              ┌──────────┐       │ background jobs /
                                              │ Synapse  │ ◄─────┘ foreground trigger
                                              └──────────┘
```

### 3.1 Two-layer code layout

**Layer A – standalone library `nextcloud/matrix-client-php`** (own directory, own git repository, own release cycle, MIT/AGPL-compatible licence chosen by the team; PSR-4 namespace `Nextcloud\Matrix\`).
- Contains everything that is *Matrix*, nothing that is *Nextcloud*: Client-Server API client, sync loop, room-state model, event model, canonical JSON, Olm/Megolm/attachment crypto, SAS verification, key backup, HTML sanitiser for `formatted_body`, `matrix.to`/`matrix:` URI parsing, room-name algorithm, push-rule evaluator.
- Hard rules: no `OCP\`/`OC\` imports, no Talk types, no database access. All I/O is behind interfaces the host implements: `HttpClientInterface` (PSR-18 `ClientInterface` + PSR-17 factories), `StoreInterface` (persistence of accounts, sync tokens, devices, Olm/Megolm sessions, secrets – key/value + a few typed queries), `ClockInterface` (PSR-20), `LoggerInterface` (PSR-3), `SecretEncryptorInterface` (encrypt/decrypt blobs at rest), `RandomInterface`.
- Ships with an in-memory store and a PDO/SQLite store for its own tests and for non-Nextcloud users.
- Public API is a small façade: `Client::login()`, `Client::sync(): SyncBatch`, `Room`, `Client::send(...)`, `Client::media()`, `Crypto` (behind `CryptoBackendInterface`, pure-PHP default), `Verification`, `Backup`. Semantic versioning; `CHANGELOG.md`; PHP ≥ 8.1; `ext-sodium`, `ext-openssl`, `ext-json` only.
- Own CI: PHPUnit (crypto test vectors, canonical JSON, event parsing) + integration tests against dockerised Synapse using the SQLite store, Psalm level 1, php-cs-fixer with Nextcloud coding standard.
- Directory (during development) `apps/spreed/matrix-client/` referenced from spreed's `composer.json` as a `path` repository; before the first spreed release it moves to `github.com/nextcloud/matrix-client-php`, is published on Packagist and pinned by version. spreed's `composer.json` lists it under `require`; `composer/` autoloader bundles it into the app-store tarball like other dependencies.

**Layer B – Talk integration `OCA\Talk\Matrix\…`** (inside spreed, `lib/Matrix/`): implements the library's interfaces on top of Nextcloud (`IClientService` → PSR-18 adapter, `ICrypto` → `SecretEncryptorInterface`, DB tables of §4 → `StoreInterface`, `ILogger`), and contains all Talk mapping: `IngestionPipeline`, `EventMapper`, `RoomStateApplier`, `MatrixSendService`, `CapabilityResolver`, media proxy controller, background jobs, settings, occ commands. Frontend: `src/components/Matrix*`, `src/services/matrixService.ts`, `src/stores/matrix.ts`.

Layering rules:
0. Layer A must be usable from a plain PHP script against Synapse with the bundled SQLite store; a `examples/echo-bot.php` in the library proves it and doubles as a smoke test.
1. Nothing outside `Nextcloud\Matrix` (library) speaks the Matrix protocol; nothing outside `OCA\Talk\Matrix` (glue) instantiates the library.
2. `OCA\Talk\Matrix` never writes `oc_comments` directly; it goes through `ChatManager` (adding a "source" flag so the outgoing hook does not echo back to Matrix – see §8.3). Encrypted events are decrypted *before* they reach `ChatManager`; nothing downstream knows about E2EE.
3. Public OCS controllers stay unchanged in shape; they gain conditional branches (`$room->isMatrixRoom()`), mirroring how federated remote rooms already branch today (`lib/Federation/Proxy/TalkV1`).

---

## 4. Data model

All new tables are prefixed `talk_matrix_`. Column names ≤ 30 chars, index names ≤ 30 chars (Oracle constraint in NC migrations). Use a single new migration class per phase.

### 4.1 `talk_matrix_homeservers`
| column | type | notes |
|---|---|---|
| id | int PK | |
| name | string(64) | label shown to users |
| server_name | string(255) | Matrix server name, e.g. `example.org` |
| base_url | string(255) | resolved C-S base URL (from `.well-known` at add time, re-resolved daily) |
| enabled | bool | |
| allow_e2ee | bool | policy toggle (§15) |
| allow_upload | bool | policy toggle |
| versions_json | text | cached `GET /_matrix/client/versions` |
| versions_fetched | datetime | |

### 4.2 `talk_matrix_accounts` (one per linked NC user)
| column | type | notes |
|---|---|---|
| id | int PK | |
| user_id | string(64) UNIQUE | NC user |
| homeserver_id | int FK | |
| mxid | string(255) | |
| access_token | text | encrypted with `ICrypto` (instance secret) |
| device_id | string(64) | |
| next_batch | string(255) NULL | sync token |
| filter_id | string(64) NULL | server-side sync filter |
| status | smallint | 0 active, 1 token invalid (needs re-login), 2 disabled by admin |
| last_sync | datetime NULL | |
| last_error | text NULL | |
| lock_until | datetime NULL | per-account sync lock |
| olm_account | text NULL | encrypted pickle of Olm account (identity keys, one-time keys) |
| cross_signing | text NULL | encrypted JSON: master/self/user-signing public keys, own device signature status |
| created_at | datetime | |

### 4.3 `talk_matrix_rooms`
| column | type | notes |
|---|---|---|
| id | int PK | |
| room_id | int FK → talk_rooms.id UNIQUE | the Talk conversation |
| matrix_room_id | string(255) UNIQUE | `!abc:server` |
| room_version | string(16) | |
| encrypted | bool | `m.room.encryption` present |
| encryption_algo | string(64) NULL | |
| rotation_period_ms | int NULL | from `m.room.encryption` |
| rotation_period_msgs | int NULL | |
| join_rule | string(32) | |
| is_direct | bool | derived from `m.direct` of any linked member |
| canonical_alias | string(255) NULL | |
| power_levels | text | JSON of `m.room.power_levels` content |
| creator | string(255) | |
| tombstone_target | string(255) NULL | set on `m.room.tombstone` (room upgrade, §12.5) |
| prev_batch | string(255) NULL | oldest known `/messages` token for backfill |
| backfill_done | bool | true when start of room reached |
| capabilities | text | JSON of computed capability set (§10) |
| state_updated | datetime | |

### 4.4 `talk_matrix_members`
Per Matrix room membership incl. non-NC users.
| column | type | notes |
|---|---|---|
| id | int PK | |
| matrix_room_id | string(255) | |
| mxid | string(255) | |
| membership | string(16) | join/leave/invite/ban/knock |
| display_name | string(255) NULL | |
| avatar_url | string(255) NULL | `mxc://` |
| power_level | int | effective level |
| attendee_id | int NULL | FK → talk_attendees.id if represented in Talk |
| account_id | int NULL | FK → talk_matrix_accounts.id when this MXID is a linked NC user |
| updated_at | datetime | |
UNIQUE(matrix_room_id, mxid).

### 4.5 `talk_matrix_events`
Maps Matrix events to Talk messages (idempotency + echo suppression).
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| matrix_room_id | string(255) | |
| event_id | string(255) UNIQUE | |
| event_type | string(128) | |
| comment_id | bigint NULL | `oc_comments.id` when mirrored as a message |
| txn_id | string(64) NULL | our own txnId when sent from Talk |
| origin_ts | bigint | `origin_server_ts` |
| sender | string(255) | |
| relates_to | string(255) NULL | target event id for edits/reactions/redactions/threads |
| rel_type | string(64) NULL | `m.replace`, `m.annotation`, `m.thread`, `redaction` |
| encrypted | bool | |
| ciphertext | text NULL | full `m.room.encrypted` event JSON, kept **only while** `decrypt_state=2` (missing key); cleared after successful decryption |
| decrypt_state | smallint | 0 n/a, 1 ok (keys known), 2 missing session, 3 failed |
| session_id | string(255) NULL | Megolm session used, for key-arrival re-evaluation |
| processed | bool | false while waiting for keys / ordering |

### 4.6 Crypto tables (all `*_pickle` columns encrypted with `ICrypto`)
These tables are spreed's implementation of the library's `StoreInterface`; the library defines *what* must be persisted (accounts, sync token, devices, Olm sessions, inbound/outbound Megolm sessions, secrets), spreed decides the schema.
- `talk_matrix_devices` – (mxid, device_id, curve25519_key, ed25519_key, signatures_json, trust smallint [0 unknown,1 cross-signed,2 verified,3 blocked], updated_at). UNIQUE(mxid, device_id).
- `talk_matrix_olm_sessions` – (account_id, their_curve25519, session_id, pickle, last_used). Olm 1:1 sessions.
- `talk_matrix_megolm_in` – (account_id, matrix_room_id, sender_key, session_id, pickle, first_known_index, forwarding_chains_json, imported_from smallint [to-device, backup, forwarded, self], UNIQUE(matrix_room_id, session_id, account_id)). Inbound group sessions. `account_id` is the Talk device that received the key (needed for the shared-conversation decrypt lookup, §9.7).
- `talk_matrix_megolm_out` – (account_id, matrix_room_id, session_id, pickle, created_at, message_count, shared_with_json). Current outbound group session per (device, room).
- `talk_matrix_secrets` – (account_id, name, value_enc). For SSSS/recovery-key-derived secrets and in-flight verification state (§9.6, §9.8).
- `talk_matrix_media_cache` – (mxc, path, size, mimetype, last_access) for media LRU (§13).

### 4.7 Changes to existing tables / constants
- `Room::OBJECT_TYPE_MATRIX = 'matrix'` (`lib/Room.php`). `object_id` = Matrix room ID. Room `type` is `TYPE_GROUP` for every Matrix room (see §12.1 for why not `TYPE_ONE_TO_ONE`/`TYPE_PUBLIC`).
- `Attendee::ACTOR_MATRIX = 'matrix'` (`lib/Model/Attendee.php`); `actor_id` = MXID. Add to every actor-type whitelist/switch (`ParticipantService`, `MessageParser`, `Notifier`, `UserConverter`, `SystemMessage` parsing, avatar controller).
- `talk_attendees` – no new columns; Matrix-only participants get rows with `actor_type='matrix'`, `participant_type = USER or MODERATOR`, `display_name` mirrored, `permissions` per §11.
- `talk_rooms.default_permissions` for Matrix rooms = `PERMISSIONS_CHAT` only (no call/publish bits) → all clients hide call UI.
- Frontend `src/constants.ts`: add `CONVERSATION.OBJECT_TYPE.MATRIX = 'matrix'`, `ATTENDEE.ACTOR_TYPE.MATRIX = 'matrix'`.

---

## 5. Matrix HTTP client (`Nextcloud\Matrix\Client`, library layer)

- Built on PSR-18; spreed supplies an adapter over `IClientService`. Base URL from `talk_matrix_homeservers.base_url` (never user-supplied). All requests carry `Authorization: Bearer <token>`; token decrypted just-in-time, never logged.
- Spec version target: **Matrix v1.11+** (`/_matrix/client/v3`, authenticated media `/_matrix/client/v1/media/*`). Fall back to unauthenticated `/_matrix/media/v3` only if `versions_json` lacks `v1.11`.
- Retry: on 429 honour `retry_after_ms` (cap 60 s, then abort job and reschedule). 5xx → 3 retries exponential. `M_UNKNOWN_TOKEN` → set account `status=1`, notify user (Talk notification "Matrix account needs re-login"), stop syncing that account.
- Timeouts: sync 8 s (job budget), other calls 20 s, uploads 300 s.
- Transaction IDs for `PUT /rooms/{id}/send/{type}/{txnId}` are `nc-<uuidv4>`, persisted in `talk_matrix_events.txn_id` before the request so a crash cannot cause duplicates.
- SSRF: only the configured base URL host is ever contacted. `mxc://` server names are **not** contacted directly; media is always fetched through the user's own HS (`/media/download/{serverName}/{mediaId}`), as the spec allows.
- Canonical JSON (Matrix appendix) implementation for signing/verification is shared with the crypto layer.
- Everything in this section is library code; the SSRF policy (allowed base URLs) is enforced by the host's `HttpClientInterface` adapter *and* by the library refusing to contact any host other than the configured base URL.

---

## 6. Identity & account linking

### 6.1 Personal settings UI (`lib/Settings/Personal.php`, new Vue section "Matrix")
- If feature disabled or user not in allowed group → section hidden.
- Not linked: homeserver selector (if >1 configured), username (localpart or full MXID), password, "Link account". Explain that the password is used once and not stored, and that Talk becomes a *device* on the account named `Nextcloud Talk (<instance name>)`.
- Linked: show MXID, device ID, verification state (unverified / cross-signed), "Verify with another device" (§9.6), "Sync now", "Unlink" (with warning about encrypted history, §16).

### 6.2 Link flow (server)
1. `POST /ocs/v2.php/apps/spreed/api/v1/matrix/account` `{homeserverId, user, password}`; brute-force protected (`@BruteForceProtection(action: 'matrixLink')`), CSRF/OCS as usual.
2. `POST /login` with `type=m.login.password`, `identifier=m.id.user`, `initial_device_display_name`. Store token (encrypted), device_id, mxid.
3. Create Olm account, upload device keys + one-time keys (`/keys/upload`), see §9.2.
4. Create server-side sync filter (`/user/{id}/filter`): `room.timeline.limit=<initial window>`, `room.state.lazy_load_members=true`, `presence.types=[]` (presence not used).
5. Run initial sync synchronously if it finishes within 10 s, else dispatch job and return `202` – the UI shows "Importing rooms…".
6. Emit `MatrixAccountLinkedEvent`.

### 6.3 Re-login
When `status=1`, personal settings show a "Re-login" form (password only). A re-login **reuses the existing device_id** via `device_id` in the login body so Olm identity and room keys remain valid.

### 6.4 Admin restrictions
- `matrix_enabled` app config; `matrix_allowed_groups` (JSON list, empty = everyone).
- `occ talk:matrix:account:list|unlink --user=<uid>`.

### 6.5 Designed-for, not built
`m.login.sso` and Application-Service puppeting. Keep `AccountLinker` behind an interface (`ILoginMethod`) so these can be added without touching storage.

---

## 7. Sync engine

### 7.1 SyncSource abstraction
```php
interface ISyncSource { public function pull(MatrixAccount $account, int $budgetSeconds): SyncBatch; }
final class SyncBatch { public array $joinedRooms; public array $invitedRooms; public array $leftRooms; public array $toDevice; public array $accountData; public array $deviceLists; public int $otkCount; public string $nextBatch; }
```
v1 implementation `SyncPoller` (library: `Nextcloud\Matrix\Sync\Syncer`, host: thin `ISyncSource` wrapper) = `GET /sync?since=<next_batch>&filter=<filter_id>&timeout=<min(budget,5000)>`. The `IngestionPipeline` (§7.4) only consumes `SyncBatch`, so an appservice transaction endpoint or HPB worker can construct batches later.

### 7.2 Background job (`lib/BackgroundJob/MatrixSync.php`)
- One `TimedJob` **per linked account** (argument `accountId`), interval from app config `matrix_sync_interval` (default 30 s; note NC cron cadence bounds actual latency – document "run cron every minute").
- `setTimeSensitivity(IJob::TIME_SENSITIVE)`; `setAllowParallelRuns(false)`.
- Per-account lock (`talk_matrix_accounts.lock_until`) so job + foreground trigger never sync the same account concurrently.
- Budget: max 25 s per run; loop `/sync` while `next_batch` changes and budget remains.
- Non-fatal error → `last_error` set, exponential back-off (max 15 min).

### 7.3 Opportunistic foreground sync
When `ChatController::receiveMessages` (and `RoomController::getSingleRoom`/`getRooms`) is called by a linked user for a Matrix conversation and `last_sync` is older than `matrix_foreground_sync_age` (default 5 s), perform one `/sync` with `timeout=0` inline **if** the lock is free, before answering. Cap at 3 s wall time; on timeout answer with what we have. This gives web users near-real-time behaviour without new infrastructure. Long-polling (`lookIntoFuture=1`) requests re-check every 2 s of their wait loop.

### 7.4 IngestionPipeline
Per `SyncBatch`, in order:
1. **to_device** events → `CryptoService::handleToDevice()` (Olm decrypt, `m.room_key`, `m.forwarded_room_key`, `m.room_key_request`, verification events). Store new inbound Megolm sessions; then retry events in `talk_matrix_events` with `decrypt_state=2` and matching `session_id`, replacing their placeholders (§9.5).
2. **device_lists.changed** → mark users dirty; `/keys/query` lazily before the next send.
3. **device_one_time_keys_count** → replenish OTKs if below 50 % (`/keys/upload`).
4. **account_data**: `m.direct` → `is_direct` flags; `m.push_rules` → initial notification levels (§14); `m.ignored_user_list` → drop events from ignored users.
5. **rooms.invite** → create pending invitation (§12.2).
6. **rooms.join**: for each room
   a. apply `state` then `timeline` state events → `RoomStateApplier` (name, topic, avatar, members, power levels, encryption, tombstone, join rules, canonical alias) → update `talk_matrix_rooms`, `talk_matrix_members`, Talk room metadata + attendees, emit Talk system messages (§8.5).
   b. timeline message events → `EventMapper` (§8) → `ChatManager` with `source=matrix`.
   c. `ephemeral`: `m.receipt` → read markers (§8.6), `m.typing` → signaling (§8.7).
   d. `unread_notifications` are ignored; Talk computes its own.
   e. if `timeline.limited` and room not yet backfilled to our last known event → schedule `MatrixBackfill` job for the gap (`prev_batch`).
7. **rooms.leave** → §12.3.
8. persist `next_batch` **last**, in the same transaction as the batch's DB writes where possible; otherwise idempotency via `talk_matrix_events.event_id` UNIQUE makes replay safe.
9. Recompute capabilities for rooms whose state changed (§10).

### 7.5 Shared conversation & multiple accounts
Several linked users may sync the same Matrix room. The first ingestion of an `event_id` creates the Talk message; later ingestions (other accounts) are no-ops except: encrypted events that were undecryptable with account A's sessions get a second chance with account B's sessions (§9.7).

Talk room ownership: the Talk conversation for a Matrix room is owned by nobody in particular; `talk_matrix_rooms` is the authority. Attendee rows: one per linked NC user (`actor_type=users`) and one per other Matrix member (`actor_type=matrix`).

### 7.6 Backfill (`lib/BackgroundJob/MatrixBackfill.php`, `QueuedJob`)
`GET /rooms/{id}/messages?dir=b&from=<prev_batch>&limit=100&filter=lazy_load_members`. Triggered (a) at room discovery for the initial window (`matrix_history_events`=200 / `matrix_history_days`=30, whichever hits first), (b) when a client requests messages older than the oldest mirrored message (`ChatController::receiveMessages` with `lookIntoFuture=0` and `lastKnownMessageId` = our oldest) – performed inline once (≤3 s), then continues as a job.

Ordering: Talk orders by comment id; backfilled messages get *higher* ids than newer ones. Implementation choice: for `object_type='matrix'` rooms, `ChatManager::getHistory()`/`waitForNewMessages()` order by `creation_timestamp, id`, and paging cursors (`lastKnownMessageId`) are translated to timestamps internally. Backfilled messages older than an attendee's current read marker must not increase that attendee's unread count (set `last_read_message` semantics by timestamp for Matrix rooms or mark backfilled rows as read for existing attendees). Verify in phase 1 against unchanged mobile apps (open question §22.2).

### 7.7 Rate limits & fairness
Global concurrency guard: at most `matrix_max_parallel_syncs` (default 4) accounts syncing at once per instance (DB counter). Synapse default rate limits are per user, so per-account polling is safe; document `rc_*` tuning for large deployments. Adaptive interval: idle accounts (no events for 10 min) back off to 2 min; a foreground request resets back-off.

### 7.8 Health
`talk_matrix_accounts.last_sync/last_error` shown in admin settings ("Sync health": accounts, last-sync age, errors), `occ talk:matrix:status [--json]`.

### 7.9 Later push sources (design only)
- Application Service: `PUT /_matrix/app/v1/transactions/{txnId}` endpoint in spreed constructing `SyncBatch`s. Requires `hs_token` config and homeserver.yaml registration; E2EE via AS needs MSC3202 – out of scope.
- HPB worker: Go signaling server holds `/sync` long-polls, forwards to a spreed endpoint with the backend secret.

---

## 8. Event mapping (Matrix ⇄ Talk)

### 8.1 Incoming message events → Talk messages
| Matrix | Talk (`oc_comments` / message object) |
|---|---|
| `m.room.message` `m.text` (body, `formatted_body`) | `messageType=comment`; message = HTML→Talk-markdown (§8.4); `messageParameters` mentions |
| `m.notice` | plain `comment` |
| `m.emote` | `comment` with body `* <name> <body>` (Talk has no emote type) |
| `m.image`/`m.file`/`m.video`/`m.audio` (+ `m.sticker`) | `comment` with rich object parameter `{type:'matrix-media', id:<event_id>, name, mimetype, size, width, height, preview-available, link:<proxy URL>}`; see §13 for proxy. Unchanged clients render unknown rich objects as their `name` → acceptable fallback |
| `m.location` | `comment` with `geo-location` rich object (Talk already supports) |
| `m.room.encrypted` (decryptable) | decrypted at ingestion, then mapped as the decrypted event type above and stored as plaintext; `talk_matrix_events.encrypted=1` for the badge |
| `m.room.encrypted` (undecryptable) | placeholder `comment` "🔒 Encrypted message – waiting for keys" with parameter `{matrix-undecryptable}`; replaced in place (same comment id, *without* marking edited) once the key arrives |
| `m.reaction` (`m.annotation`) | `ReactionManager::addReactionMessage` on the mapped comment; actor from sender |
| `m.replace` edit | `ChatManager::editMessage` on original comment (only if editor == original sender, per spec) |
| `m.room.redaction` | `ChatManager::deleteMessage` (→ "Message deleted by …") or reaction removal if target was a reaction |
| `m.in_reply_to` | Talk reply (`parent_id`) |
| `m.thread` relation | Talk thread: root comment gets `talk_threads` entry; message gets `thread_id` (Talk 25 threads API); `m.in_reply_to` with `is_falling_back` ignored |
| unknown `m.room.message` msgtype | `comment` with `body` |
| unknown event type with no `body` | not mirrored (recorded in `talk_matrix_events` only) |

Text limits: Talk `ChatManager::MAX_CHAT_LENGTH` (32000) – longer bodies are truncated with "…" and a `{matrix-truncated}` parameter; original retrievable via event id in the message's `metaData`.

### 8.2 State events → Talk system messages (§8.5) and metadata
| Matrix state | Talk |
|---|---|
| `m.room.name` | `Room::setName` + system `conversation_renamed` |
| `m.room.topic` | `setDescription` (truncate to Talk's limit) + `description_set` |
| `m.room.avatar` | download via HS media, store as Talk room avatar (`AvatarService`) + `avatar_set` |
| `m.room.member` join/leave/invite/ban/kick, display name/avatar change | attendee add/remove/update; system messages `user_added`/`user_removed`/… actor = sender, target = member. NC-linked MXIDs map to `users` actors, others to `matrix` |
| `m.room.power_levels` | attendee `participant_type` MODERATOR/USER (§11) + `moderator_promoted/demoted` |
| `m.room.encryption` | `encrypted=1`, recompute caps, new system message `matrix_encryption_enabled` |
| `m.room.join_rules`, `m.room.canonical_alias`, `m.room.history_visibility`, `m.room.guest_access` | stored, capabilities |
| `m.room.tombstone` | §12.5 |
| `m.room.create` | `creator`, `room_version`, `type` (spaces filtered, §12.6) |
| `m.call.invite`/`m.call.*`, `org.matrix.msc3401.call*` | system message `matrix_call_unsupported` (once per call id) |

### 8.3 Outgoing (Talk → Matrix)
Hook point: **new `MatrixSendService` called from the controller/service layer before the local write**, not via post-write event listeners, so a failing Matrix send returns an error to the client and nothing is stored locally.

| Talk action | Matrix request |
|---|---|
| `ChatController::sendMessage` | `PUT …/send/m.room.message/{txn}` with `msgtype=m.text`, `body`, `format=org.matrix.custom.html`, `formatted_body`, `m.mentions`, `m.relates_to` for reply/thread. In encrypted rooms wrapped as `m.room.encrypted` (§9.4). Then `ChatManager::sendMessage(source=matrix, eventId)` locally and mapping row |
| edit | `m.replace` with `m.new_content` |
| delete | `PUT …/redact/{eventId}/{txn}` |
| reaction add | `m.reaction` with `m.annotation` |
| reaction remove | redact our reaction event (mapping stored in `talk_matrix_events` keyed by the reaction's comment id) |
| share file (`ChatController::shareObjectToChat` with `file` rich object) | upload to HS (`/_matrix/client/v1/media/upload` or `/_matrix/media/v3/upload`) + `m.file/m.image/...` (encrypted attachment in E2EE rooms, §9.9); file read via the sharer's NC file access; size cap `matrix_max_upload` (default 100 MB, also bounded by HS `m.upload.size`) |
| read marker set (`ChatController::setReadMarker`) | `POST …/receipt/m.read/{eventId}` (throttled: ≤1 per 5 s per room/user; skipped if older than last receipt) |
| typing (signaling) | §8.7 |
| rename / description / avatar (`RoomController`) | `PUT …/state/m.room.name` etc. Checked against power levels first; Matrix error → OCS 403 with the Matrix `error` message |
| add participant | `POST …/invite` (`user_id` = MXID for `matrix` source, or the linked MXID for an NC user; NC user without link → 400 "user has no Matrix account") |
| remove participant / ban | `/kick`, `/ban` |
| leave | `/leave` (+ `/forget`) |
| promote/demote moderator | `m.room.power_levels` update (50 / users_default) |
| set notification level | local only (§14) |

Echo suppression: the mapping row is written *before* returning; when the event later arrives via sync, `event_id` exists → skip content.

Failure semantics: Matrix 4xx → OCS error with the Matrix `error` string; 5xx/timeouts → OCS 502 "Matrix homeserver unavailable"; nothing stored locally (Talk clients already handle failed sends with retry UI).

Attendees are created only for linked users, so every writer has a device. A user whose link is `status=1` gets chat permission removed on Matrix rooms and a banner (web).

### 8.4 Formatting & mentions
- Incoming: `formatted_body` sanitised (whitelist per Matrix spec: `b,i,u,s,strong,em,code,pre,blockquote,ul,ol,li,a(href http/https/matrix/mxc),br,p,h1-h6,span(data-mx-color…),img(mxc only → media proxy),del,table…`), then converted to Talk markdown (Talk renders markdown client-side). `mx-reply` fallback blocks are stripped. Pills `<a href="https://matrix.to/#/@user:hs">` → Talk `{mention-user}` if the MXID is a linked NC user, else rich parameter `{type:'user', id:'@x:y', name, server:'matrix'}` (`mention-matrix`) → unchanged clients render the name.
- Outgoing: Talk markdown → HTML (server-side, same subset), `@user` mentions → matrix.to pills + `m.mentions.user_ids`; `@all` → `m.mentions.room=true` and `@room` in body (requires PL `notifications.room`, else sent as plain text).
- `body` is always the plain-text rendering.

### 8.5 System messages
Reuse existing Talk system message identifiers wherever semantics match so unchanged clients render them; introduce new ones only for Matrix-specific facts (`matrix_encryption_enabled`, `matrix_room_upgraded`, `matrix_call_unsupported`). New identifiers are added to `docs/chat.md`; unchanged clients render the accompanying plain `message`.

### 8.6 Read receipts
- Incoming `m.receipt` (`m.read` of other users): update the corresponding attendee's `last_read_message` to the mapped comment id → Talk's read-status avatars show Matrix readers (`chat-read-status` capability).
- The NC user's Talk read marker is local plus mirrored out (§8.3).

### 8.7 Typing
- Incoming `m.typing` → for rooms with active Talk sessions, publish a typing message via the signaling backend (internal signaling message queue; HPB: backend `message` request to the room) with actor type `matrix`. With 30 s polling this is mostly stale; acceptable, becomes useful with foreground sync (§7.3) and future push sources. Config `matrix_typing_in` (default on).
- Outgoing typing: Talk typing never reaches PHP when the HPB is used; in internal-signaling mode it does. Implement only for internal signaling (`PUT …/typing/{userId}` with `timeout=10000`, throttled). Config `matrix_typing_out` (default off). Document limitation.

---

## 9. End-to-end encryption

### 9.1 Scope & trust model
- Talk's PHP client is a real Matrix E2EE **device** per linked user. Encryption terminates there: **the Nextcloud server and its database are inside the trust boundary.** Decrypted messages are stored in `oc_comments` like any other Talk message (decision: full-text search and feature parity outweigh at-rest secrecy on the NC side; deployments needing otherwise should rely on NC server-side/DB encryption and access control). Only *key material and tokens* are additionally encrypted at rest (§9.10).
- Algorithms: `m.olm.v1.curve25519-aes-sha2` (to-device), `m.megolm.v1.aes-sha2` (rooms). Encrypted attachments per spec (AES-CTR-256, SHA-256).
- Implementation is **pure PHP** on top of `ext-sodium` (bundled in PHP ≥ 7.2) and `ext-openssl`: X25519 (`sodium_crypto_scalarmult`), Ed25519 (`sodium_crypto_sign_*`), HKDF-SHA-256 (`hash_hkdf`), HMAC-SHA-256, AES-256-CBC (`openssl_encrypt`). No native binaries → app-store installable. Constant-time compares via `hash_equals`. Everything in the library under `Nextcloud\Matrix\Crypto\{Olm,Megolm,Canonical,Attachment}` with **no dependency on Talk or Nextcloud** so it can be unit-tested against the libolm/vodozemac test vectors and cross-checked in CI against a Python `matrix-nio` client.
- **Risk to flag explicitly to the implementer:** hand-rolling ratchets is security-sensitive; the crypto module must pass the published test vectors, be reviewed separately, and sit behind `CryptoBackendInterface` so it can be swapped for a vodozemac FFI binding later.

### 9.2 Device bootstrap (at link time)
1. Generate Olm account: Ed25519 signing key, Curve25519 identity key, 50 one-time keys, one fallback key.
2. `POST /keys/upload` device keys signed with Ed25519 (canonical JSON), plus OTKs/fallback key.
3. Persist pickle encrypted (`ICrypto::encrypt`) in `talk_matrix_accounts.olm_account`. Pickle format is our own (JSON), not libolm's.
4. Advertise algorithms `m.olm.v1.curve25519-aes-sha2`, `m.megolm.v1.aes-sha2`.

### 9.3 Receiving room keys
- to-device `m.room.encrypted` (Olm): try existing sessions with `their_curve25519`; else if prekey message create inbound session, mark OTK used; decrypt; verify `keys.ed25519`, `recipient`, `recipient_keys` match own device (spec checks). Payload `m.room_key` → store inbound Megolm session (`imported_from=to-device`). `m.forwarded_room_key` → store with forwarding chain, accepted by default only from own cross-signed devices.
- Missing keys: for `decrypt_state=2` events, send `m.room_key_request` to own other devices and the sender's devices (once per session, retry after 10 min, max 3). Send `request_cancellation` when satisfied.
- Key requests **from** others for our outbound sessions: answer only to our own cross-signed devices.

### 9.4 Sending in encrypted rooms
1. Ensure room member device lists are fresh (`/keys/query` for members with `device_lists.changed` or unknown).
2. Get/create outbound Megolm session for (device, room); rotate on `rotation_period_ms`/`msgs` or when a member leaves (new device → share current index, no rotation).
3. Share the session key to every device of every joined/invited member not yet in `shared_with_json`: `/keys/claim` OTKs → Olm prekey → `m.room_key` via `PUT /sendToDevice/m.room.encrypted/{txn}`. `blocked` devices skipped. Unverified devices are **shared with** (Element default) unless admin toggle `matrix_e2ee_verified_only` is set.
4. Encrypt event content (`type`, `content`, `room_id`) with Megolm, send `m.room.encrypted`. Store our own inbound copy of the session (`imported_from=self`) so we can read our own message back.

### 9.5 Decrypt-on-ingest
- The `IngestionPipeline` decrypts every `m.room.encrypted` timeline event before mapping (§8.1). Session lookup per §9.7. On success the plaintext event is mapped and stored exactly like an unencrypted one; `talk_matrix_events.encrypted=1`, `decrypt_state=1`, `ciphertext=NULL`. Verify `sender_key`/Ed25519 binding to a known device of the sender; on mismatch store the message with parameter `{matrix-unverified-sender}` (web shows a warning badge).
- On missing session: store the placeholder comment ("🔒 Encrypted message – waiting for keys", parameter `matrix-undecryptable`), keep the ciphertext in `talk_matrix_events.ciphertext`, `decrypt_state=2`, send key requests (§9.3). When the key arrives (to-device, forwarded key, backup import, or another linked account's session via §9.7) the placeholder comment is **updated in place** (same id, no "edited" marker) with the plaintext and `ciphertext` is cleared. Relations (edits/reactions/redactions/threads) targeting a still-undecryptable event are queued (`processed=0`) and applied once the target decrypts.
- Because storage is plaintext, unified search, notification previews, `lastMessage`, mention detection, activity and all clients work with no special casing. Edits of encrypted messages go through `ChatManager::editMessage` like plain ones.
- Performance target: ≤ 2 ms per Megolm decrypt in PHP; decryption cost lands in the background job, not in request paths (except opportunistic foreground sync, §7.3). Measure in phase 2; fallback is the `CryptoBackendInterface` swap.
- Admin policy: `allow_e2ee=0` on a homeserver makes Talk refuse to *send* into encrypted rooms (`canSend=false`, reason `e2ee-disabled`) and skips decryption of incoming events (placeholders only), for deployments that must not hold decrypted content.

### 9.6 Cross-signing & verification
- Talk does **not** create cross-signing keys (the user's other client owns them). Talk uploads its device keys and, when the user clicks "Verify" in personal settings, sends `m.key.verification.request` (to-device, to all own devices) and runs the SAS (`m.sas.v1`, emoji) flow server-side; emojis are shown in Talk personal settings (polled via a small OCS endpoint; flow state kept in `talk_matrix_secrets` as `verification:<txn>`). On success the other client signs our device with its self-signing key; if it also sends secrets via `m.secret.send`, store them encrypted (§9.8).
- Track other users' master keys (`/keys/query` `master_keys`, `self_signing_keys`); devices signed by a seen master key get `trust=1`. TOFU on master keys; a changed master key sets the room capability `identityChanged`.
- Verifying *other users* from Talk is out of scope (v1).

### 9.7 Shared conversation decryption lookup
At ingestion of event E with session S: try inbound sessions for S owned by the syncing account; if none, try sessions for S owned by **any linked account that is a joined member of the room** (`matrix_e2ee_shared_lookup`, default **on**). Since the result is stored once and shown to every NC member anyway, this mainly shortens "waiting for keys" time; off = only the syncing account's own sessions are used (the message still becomes visible to all NC members once *any* member's sync decrypts it).

### 9.8 Key backup / recovery key (phase 4)
If the user pastes a recovery key (or the backup key arrived via `m.secret.send`), fetch `/room_keys/version`, decrypt the backup (`m.megolm_backup.v1.curve25519-aes-sha2`), import sessions (`imported_from=backup`), and upload newly received sessions to the backup. Enables history for encrypted rooms that predate the link.

### 9.9 Encrypted attachments
Incoming `file` object with `key`, `iv`, `hashes.sha256`: media proxy (§13) downloads, verifies hash, decrypts AES-256-CTR, streams to the client; decrypted media may be cached like unencrypted media (§13). Outgoing: encrypt to an encrypted temp file, upload, send `m.file` with `file` object.

### 9.10 Key material at rest
All pickles/tokens encrypted with `ICrypto` (derived from `secret` in config.php). Rotating `secret` invalidates all links unless `occ talk:matrix:rekey --old-secret=…` is run.

---

## 10. Per-room capability detection

Computed in `MatrixCapabilityResolver` after every state change, stored in `talk_matrix_rooms.capabilities`, exposed on the conversation object as `matrixCapabilities` (additive field, ignored by old clients) **and** enforced via existing fields so unchanged clients behave:

| capability key | derived from | enforcement for unchanged clients |
|---|---|---|
| `canSend` | membership=join and user PL ≥ `events["m.room.message"]`/`events_default` | attendee `permissions` without `PERMISSIONS_CHAT` |
| `canReact`, `canEdit`, `canDeleteOwn`, `canDeleteOthers` | HS `/versions`, PL for `m.reaction`, `redact` | 403/405 on attempt; web pre-hides via `matrixCapabilities` |
| `threads` | HS `/versions` v1.4+ | 405 on attempt |
| `canInvite`, `canKick`, `canBan`, `canRename`, `canSetDescription`, `canSetAvatar`, `canPromote` | power levels | `participant_type` MODERATOR only if PL ≥ 50 (§11); server rejects otherwise |
| `encrypted`, `encryptionOk`, `missingKeys` (count) | room state + `decrypt_state` stats | shown in web conversation settings |
| `upload` | HS media config, homeserver `allow_upload`, PL | 403 on attempt; web hides |
| `calls` | always `false` (v1) | `default_permissions` w/o call bits; `RoomController::joinCall` → 405 |
| `polls`, `locationSharing`, `voiceMessages` | `false`/`false`/`true` (as file) | 405; web hides |
| `lobby`, `listable`, `sipEnabled`, `breakoutRooms`, `recording`, `messageExpiration`, `guests` | fixed `false` | `RoomController` rejects with 405 `matrix-unsupported` |
| `avatarUpload` | PL for `m.room.avatar` | 403 |
| `maxMessageLength` | 32000 (Talk) vs. 65535-byte event limit | truncation/reject |
| `isDirect` | `m.direct` | informational (web DM styling) |
| `identityChanged`, `upgradedTo`, `invited[]` | §9.6, §12.5, §11 | informational |

Global capability additions (`lib/Capabilities.php`): feature `matrix-rooms` (local-only, not federation-forwarded); `config.matrix = {enabled, can-link, homeservers:[{id,name,server_name}], e2ee-enabled, upload-enabled, typing-enabled}`.

---

## 11. Participants & power levels

- Every joined Matrix member is an attendee (`users` for linked MXIDs, `matrix` otherwise). Display name/avatar from `talk_matrix_members`. Avatar endpoint `GET /avatar/{token}/matrix/{mxid}` → proxied via HS media, cached 24 h in `IAppData`.
- `participant_type`: creator with PL ≥ 100 → `OWNER`; PL ≥ 50 (or ≥ `kick`/`ban`/`redact` levels) → `MODERATOR`; else `USER`.
- Invited Matrix members are **not** attendees (Talk has no pending state for attendees); listed via `matrixCapabilities.invited[]` for the web.
- Moderator actions in `ParticipantController`/`RoomController` on Matrix rooms are proxied to Matrix with an optimistic local update, reverted if the next sync disagrees.
- Attendee `permissions`: `PERMISSIONS_CHAT` if `canSend` else `0`; never call bits.
- Own attendee for linked user U in room R exists iff U's membership is `join`.

---

## 12. Room lifecycle

### 12.1 Conversation creation & type
On discovering a joined Matrix room: `RoomService::createConversation(TYPE_GROUP, name, owner=null, objectType='matrix', objectId=<room id>)`. Name: `m.room.name` → canonical alias → computed from heroes (Matrix room-name algorithm). Rationale for `TYPE_GROUP` everywhere: `TYPE_ONE_TO_ONE` requires two NC users and has locking semantics; `TYPE_PUBLIC` implies guest links. DMs carry `isDirect`; the web renders them 1:1-style.

### 12.2 Invites
Reuse Talk's pending-invitation surface (`talk_invitations`, `FederationManager`, `RoomController::getPendingInvitations`): a Matrix invite appears as a pending invitation with `remoteServerUrl = matrix:<server_name>`, `roomName`, `inviterDisplayName`; accept → `POST /join/{roomId}?via=…` (server hints from `invite_room_state`), reject → `/leave`. Unchanged clients show Matrix invites in their existing invitation UI.

### 12.3 Leave / kick / ban
- User leaves in Talk (`removeSelfFromRoom`) → `/leave` + `/forget`; attendee removed; if no linked user remains → delete Talk conversation and all `talk_matrix_*` rows/ciphertext for it; else keep.
- Sync shows our membership `leave`/`ban` → same handling without the Matrix call.

### 12.4 Create from Talk
"New conversation" gets a **"Matrix room"** mode (visible if linked): name, topic, encryption on/off (default on; disabled if `allow_e2ee=0`), private/public, invitees (linked NC users or raw MXIDs) → `POST /createRoom` (`preset`, `initial_state` incl. `m.room.encryption`), followed by a forced inline sync. DM: `createRoom(is_direct=true, invite=[mxid])` + `m.direct` update.

Join by alias/ID: input accepting `#alias:server`, `!id:server`, `matrix.to` and `matrix:` URIs → `/join/{roomIdOrAlias}`. Public room directory (`/publicRooms`) – phase 4.

### 12.5 Room upgrades (`m.room.tombstone`)
Mark old room `upgradedTo`; when we join the replacement, post `matrix_room_upgraded` system messages in both; the old conversation becomes read-only (permissions 0) and is **not** merged (as in Element).

### 12.6 Spaces
Rooms with `m.room.create.type = m.space` are not shown; their children appear flat. Later: map to conversation tags.

### 12.7 Ignored users, knocks
`m.ignored_user_list` respected on ingest. `join_rule=knock` → "Join" performs `/knock`; knocking members listed with invited.

---

## 13. Media proxy

`GET /ocs/v2.php/apps/spreed/api/v1/matrix/media/{token}/{eventId}[/thumbnail?w=&h=]` plus a non-OCS `<img>`-friendly route (`#[NoCSRFRequired]`, session-authenticated):
- Auth: requester must be an attendee of the Talk room owning `eventId`; the requester's own HS token is used.
- Downloads `mxc` via the requester's HS (authenticated media), enforces size limit, decrypts if encrypted attachment (§9.9), streams with sniffed + allow-listed `Content-Type` (everything else `application/octet-stream` + `Content-Disposition: attachment`; SVG never inline), `Cache-Control: private, max-age=86400`. Thumbnails: HS `/thumbnail` for unencrypted; for encrypted generated in memory from the decrypted stream (source ≤ 20 MB).
- Media (decrypted where applicable) may be cached in `IAppData/matrix-media` for 7 days (LRU via `talk_matrix_media_cache`).
- "Save to Nextcloud" (web) copies the (decrypted) file into the user's files via `IRootFolder` – phase 3.

---

## 14. Notifications

- Mirrored messages go through the ordinary `ChatManager` → `Notifier` path, so mentions, replies, per-conversation levels and push work. `matrix` actor names resolved from `talk_matrix_members`.
- Initial notification level per linked user from `m.push_rules`: room rule `dont_notify` → `NEVER`; `isDirect` → `ALWAYS`; default → `MENTION`. Afterwards Talk-local, not written back.
- Messages from the user's *other* Matrix devices (same MXID) never notify that user.
- `m.read` receipts for our own MXID from other devices advance the Talk read marker so notifications clear cross-client.

---

## 15. Admin settings (`lib/Settings/Admin`, new Vue section "Matrix")

- Enable Matrix rooms (`matrix_enabled`).
- Homeservers table: name, server name (→ `.well-known` resolution with manual base-URL override), enabled, allow E2EE rooms, allow file upload, "Test connection" (`/versions`).
- Who may link: group multiselect (`matrix_allowed_groups`).
- Operations: sync interval (10–300 s), max parallel syncs, foreground sync age, history window (events/days), max upload size, typing in/out, E2EE shared lookup, verified-only key sharing.
- Health panel: linked accounts, accounts in error (MXIDs), median sync age, backlog of `decrypt_state=2` events, last 20 errors (`GET …/matrix/admin/status`).
- occ: `talk:matrix:homeserver:add|list|remove`, `talk:matrix:account:list|unlink|sync`, `talk:matrix:status`, `talk:matrix:rekey`.

Documentation: new `docs/matrix.md` (admin + user + API); update `docs/capabilities.md`, `docs/conversation.md`, `docs/constants.md`, `docs/chat.md`.

---

## 16. Unlink & user deletion

`DELETE …/matrix/account`:
1. Best-effort `POST /logout` (invalidates token & device on the HS).
2. Delete account row, Olm/Megolm sessions owned by the account, secrets, own device cache.
3. For each Matrix conversation the user was in: remove attendee; if no linked users remain → delete conversation (`RoomService::deleteRoom`) + Matrix rows; else keep. With shared lookup on, remaining users may lose decryptability of some history – the UI warns before unlinking.
4. `UserDeletedListener` (`lib/Listener/UserDeletedListener.php`) performs the same.

---

## 17. Web UI changes (spreed `src/`)

1. **Personal settings**: link/re-login/unlink forms, SAS verification wizard, sync status.
2. **Admin settings** (§15).
3. **Conversation list**: Matrix badge on `objectType === 'matrix'`; DM styling when `matrixCapabilities.isDirect`; lock icon when encrypted.
4. **Conversation settings**: `#alias`/`!id`, homeserver, encryption status & missing-keys counter, upgrade notice; hide unsupported sections per `matrixCapabilities`.
5. **Top bar**: no call buttons (permissions already hide them) – add explicit guard.
6. **Message list**: `matrix-media` rich object (inline image/video, file card, "Save to Nextcloud"), `matrix-undecryptable` placeholder with "Verify this device" link, `mention-matrix` pills, unverified-sender indicator, truncated notice.
7. **Composer**: hide poll/location/voice per capabilities; file share uploads to Matrix with progress; mention autocomplete includes Matrix members (extend `/mentions` endpoint with `matrix` source).
8. **Participants**: `matrix` actors with MXID subtitle, PL moderator badge; invite dialog accepts MXIDs and shows link status of NC users; "Invited (pending)" list.
9. **New conversation**: Matrix room / DM / join-by-alias (§12.4).
10. **Invitations**: pending list shows Matrix invites (`remoteServerUrl` starting `matrix:`) with a Matrix icon.
11. **Banners**: "Matrix account needs re-login", "This device is unverified – encrypted history may be unavailable".

No changes to signaling/call code paths beyond guards.

---

## 18. Compatibility with unchanged mobile/desktop clients

Checklist the implementation must satisfy (integration tests assert the API shape):
- Matrix conversations appear in `GET /room` with `type=2`, `objectType='matrix'`, valid `token`, `displayName`, `lastMessage`, `unreadMessages`, `permissions` without call bits, `callFlag=0`, `canStartCall=false`.
- All message objects are valid Talk messages; new rich-object types (`matrix-media`, `mention-matrix`, `matrix-undecryptable`, `matrix-truncated`) carry a sensible `name` for fallback rendering.
- `actorType='matrix'` messages have `actorDisplayName`; `/avatar/…` returns a valid image (generic fallback).
- Reactions, edits, deletes, replies, threads, read markers work through the standard endpoints.
- Unsupported actions return standard OCS errors (403/405), never 500.
- Invitations flow through the existing invitation API.
- Desktop app (Electron wrapper) gets the full web UI.

---

## 19. Security & privacy

- Tokens/keys encrypted at rest (§9.10); never logged; debug logs contain event ids only.
- Brute-force protection on link; rate limiting (`@UserRateLimit`) on media proxy (300/min) and lifecycle endpoints.
- SSRF: only configured base URLs (§5); `.well-known` fetched only at admin add/refresh time.
- Media content-type allow-list for inline display; same-origin proxying keeps CSP intact.
- Server-side HTML sanitisation of `formatted_body`, converted to markdown for Talk's existing rendering pipeline.
- Decrypted content is stored and indexed like any Talk message (deliberate, §9.1); documented prominently for admins. Talk message expiration disabled for Matrix rooms.
- GDPR export includes MXID/device id, excludes keys.
- Docs privacy note: room members see the linked user's real MXID and a device "Nextcloud Talk (<instance>)"; the Nextcloud server stores decrypted content of encrypted rooms.

---

## 20. Performance & operations

- Per-account idle sync run < 300 ms. 1000 linked users at 30 s = ~33 req/s to the HS; adaptive back-off (§7.7) reduces idle load.
- Megolm decrypt budget §9.5; Olm/Curve25519 via sodium is ~50 µs.
- Backfill jobs ≤ 5 concurrent per instance.
- `occ talk:matrix:status --json` for monitoring.
- Cleanup job: prune `talk_matrix_events` rows without `comment_id` older than 90 days, expire media cache, drop stale devices.

---

## 21. Delivery phases & acceptance criteria

### Phase 1 – Linking + unencrypted rooms read/write
Deliverables: §4.1–4.5 tables, §5 client, §6 linking, §7 poller + foreground sync + backfill, §8.1/8.2/8.3 text/state mapping (text, notice, emote, replies, membership, name/topic/avatar, power levels), §10 resolver with call/lobby/etc. suppression, §11 participants, §12.1–12.3, §14 notifications, admin settings basics (enable, homeservers, groups, interval), docs.
Acceptance:
- A1 Link an account against dockerised Synapse; rooms appear in web and in `GET /room` with the §18 shape within one sync interval.
- A2 Messages from a Python `nio` client appear in Talk ≤ interval+2 s; messages from Talk appear in `nio` with correct `formatted_body`, pills and reply relations; no duplicates after 3 forced re-syncs.
- A3 Two NC users in one Matrix room see one shared conversation with both as `users` attendees and an external member as `matrix` attendee.
- A4 Call button absent in web; `POST /call/{token}` returns 405; lobby/SIP/breakout endpoints return 405.
- A5 Matrix invite shows in pending invitations; accept joins; reject leaves.
- A6 Unlink removes the device from Synapse (`/devices`) and handles conversations per §16.
- A7 PHPUnit coverage for `EventMapper`, `RoomStateApplier`, `CapabilityResolver`, markdown/HTML converter; CI job with Synapse green.
- A8 Library boundary: `matrix-client/` has its own green CI, contains no `OCP\`/`OCA\` reference (grep-enforced in CI), and `examples/echo-bot.php` logs in, joins a room and echoes a message against Synapse without Nextcloud.

### Phase 2 – E2EE
Deliverables: §9.1–9.7, §9.9 (read side), placeholders + in-place replacement, key requests, SAS verification UI, admin toggles.
Acceptance:
- B1 Library crypto module passes libolm/vodozemac vectors (Olm session establishment, Megolm ratchet/export/import, canonical JSON, attachments).
- B2 Encrypted room created in `nio`; after SAS verification of the Talk device from `nio`, new messages from `nio` render in Talk and messages from Talk decrypt in `nio` with a trusted sender.
- B3 Automated test: a sentinel string sent in an encrypted room is found by Talk's unified search and in `GET /chat/{token}`; `talk_matrix_events.ciphertext` is NULL for it after decryption.
- B4 Missing-key event shows placeholder; after `m.forwarded_room_key` arrives it is replaced without changing the comment id.
- B5 Second NC user in the same encrypted room reads history with shared lookup on; cannot with it off until keys are shared to their device.
- B6 A sync batch of 100 encrypted messages is ingested in < 500 ms on the CI runner; message pages render as fast as unencrypted rooms.

### Phase 3 – Media, reactions, edits, deletions, threads, receipts
Deliverables: §8.1 relations, §8.6 receipts, §8.7 typing (incoming), §13 media proxy + upload (plain and encrypted), "Save to Nextcloud", composer/message UI (§17.6–17.7).
Acceptance:
- C1 Reaction/edit/delete/thread round-trip both directions, including encrypted rooms.
- C2 Image from `nio` (plain and encrypted) renders inline with thumbnail; file from Talk arrives in `nio` as `m.file` with correct size/mimetype; encrypted upload decrypts in `nio`.
- C3 Matrix users' read receipts appear as read status in Talk; Talk read marker produces `m.read` on the HS.
- C4 Unchanged Android/iOS app (manual test on devel) shows media messages as fallback text without crashing.

### Phase 4 – Lifecycle & polish
Deliverables: library moved to its own repository and tagged 1.0.0 (spreed pins it), §12.4 create/DM/join-by-alias, public room directory, §12.5 upgrades, §12.7 knock, moderation actions from Talk, §9.8 key backup/recovery key, adaptive polling, health panel & occ status, cleanup jobs, final `docs/matrix.md`, `talk:matrix:rekey`.
Acceptance:
- D1 Create encrypted Matrix room with NC + external invitees from Talk; appears in `nio` with expected `initial_state`.
- D2 Power-level-driven moderator actions succeed/fail with proper errors; `nio` observes state changes.
- D3 Recovery key import restores history of an encrypted room that predates the link.
- D4 Room upgrade produces read-only old conversation + system messages.

---

## 22. Open questions & risks (confirm or spike early)

1. **Pure-PHP crypto viability** – spike Megolm decrypt throughput and Olm session setup in week 1 of phase 2. Keep `CryptoBackendInterface` so vodozemac-FFI can replace it.
2. **Message ordering with backfill** (§7.6) – confirm timestamp ordering doesn't break `lastKnownMessageId`/`lastReadMessage`/unread semantics in unchanged mobile apps.
3. **Cron latency** – measure with foreground sync; if unacceptable, prioritise the HPB sync worker (§7.9).
4. **`TYPE_GROUP` for DMs** – verify clients' rendering of a two-person group with `isDirect` hint is acceptable (a new room type would break unchanged clients).
5. **`matrix` actor type in mobile apps** – confirm unknown `actorType` strings degrade gracefully; fallback is to emit the `federated_users` shape for external members (one constant).
6. **Synapse rate limits** for many accounts from one IP – document `rc_*`; consider AS-based sync sooner for large installs.
7. **HTML→markdown fidelity** – tables/colours are lossy; acceptable.
8. **Plaintext storage of E2EE rooms** – deliberate; security review should confirm the admin `allow_e2ee` policy toggle is sufficient for regulated deployments.
9. **`secret` rotation** breaks links – acceptable with `rekey`.
10. **Matrix retention (MSC1763) vs Talk expiration** – ignored in v1.
11. **Device keys must be published before anything else** – every code path that touches the
    Olm account (bootstrap, key top-up, first send) must guarantee the device keys are on the
    homeserver, otherwise other clients silently ignore the device (verification requests,
    key shares). Guarded in `Machine::publishKeys()`; keep it that way when adding paths.
12. **Library boundary temptation** – Talk-specific needs (e.g. Talk markdown flavour, unread semantics) must stay in `OCA\Talk\Matrix`; review PRs for Nextcloud-isms leaking into the library.
13. **Matterbridge coexistence** – a Talk room bridged via Matterbridge to a Matrix room a user also has linked would double-show; detect via `talk_bridges` and warn in admin UI.

---

## 23. Key files to touch (orientation for Claude Code)

**Library (`apps/spreed/matrix-client/` → later `nextcloud/matrix-client-php`)**
```
composer.json              # name nextcloud/matrix-client-php, psr-4 Nextcloud\Matrix\ → src/
src/Client.php             # façade
src/Http/                  # PSR-18 wrapper, retry/backoff, error mapping (MatrixException, RateLimited, UnknownToken)
src/Api/                   # Login, Sync, Rooms, Media, Keys, ToDevice, Receipts, Typing, Account data
src/Model/                 # Event, StateEvent, RoomState, Member, PowerLevels, SyncBatch, MediaFile…
src/Sync/Syncer.php        # /sync loop + filter management, emits SyncBatch
src/Room/NameCalculator.php, src/Room/Capabilities.php (spec-level: what the room/HS supports)
src/Crypto/{Canonical,Olm,Megolm,Attachment,Store,CryptoBackendInterface,PhpBackend}.php
src/Crypto/Verification/   # SAS flow
src/Crypto/Backup/         # room-key backup, SSSS, recovery key
src/Html/                  # formatted_body sanitiser, markdown↔HTML helpers (generic part)
src/Store/{StoreInterface,MemoryStore,PdoStore}.php
src/Contracts/             # HttpClientInterface, ClockInterface, SecretEncryptorInterface, RandomInterface
tests/                     # vectors/, unit/, integration/ (Synapse via docker compose)
examples/echo-bot.php
```

**spreed glue and touched files**

- `lib/Room.php` (`OBJECT_TYPE_MATRIX`, `isMatrixRoom()`), `lib/Model/Attendee.php` (`ACTOR_MATRIX`), `lib/Participant.php`
- `lib/Chat/ChatManager.php` (source flag, timestamp ordering branch), `lib/Chat/Parser/*` (decrypt listener, matrix rich objects), `lib/Chat/ReactionManager.php`, `lib/Chat/Notifier.php`, `lib/Chat/MessageParser.php`
- `lib/Controller/{ChatController,RoomController,ParticipantController,ReactionController,AvatarController,FederationController}.php` – Matrix branches / 405 guards
- `lib/Service/{RoomService,ParticipantService,AvatarService,ThreadService}.php`
- `lib/Federation/FederationManager.php` + invitation model – reuse for Matrix invites
- `lib/Capabilities.php`, `lib/Settings/Admin/AdminSettings.php`, `lib/Settings/Personal.php`
- `lib/BackgroundJob/` – `MatrixSync`, `MatrixBackfill`, `MatrixCleanup`
- `lib/Listener/UserDeletedListener.php`
- `lib/Migration/Version25000Date…Matrix*.php`
- `appinfo/routes/routesMatrix*.php`, `appinfo/info.xml` (commands, jobs)
- New: `lib/Matrix/**` – `Adapter/{HttpClient,Store,SecretEncryptor,Clock}.php` (library interface implementations), `Account/`, `Ingestion/`, `Mapping/`, `Media/`, `Capabilities/`, `Lifecycle/`, `Send/`; `lib/Command/Matrix/*`; `composer.json` gains `nextcloud/matrix-client-php`
- Frontend: `src/constants.ts`, `src/services/matrixService.ts`, `src/stores/matrix.ts`, `src/components/{AdminSettings,Settings}/Matrix*.vue`, guards in `ConversationSettings`, `LeftSidebar`, `MessagesList`, `NewMessage`, `RightSidebar`, new `MessagePart/MatrixMedia.vue`
- Tests: `tests/php/Matrix/**`, `tests/integration/features/matrix/*.feature` (Behat; add a Synapse service to the integration workflow), `.github/workflows/integration-matrix.yml`
- Docs: `docs/matrix.md`, `docs/capabilities.md`, `docs/conversation.md`, `docs/constants.md`, `docs/chat.md`
