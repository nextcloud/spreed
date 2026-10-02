# Matrix integration for Nextcloud Talk — the prompts that built it

A chronological record of every prompt Frank Karlitschek gave to Claude Code (model: Fable 5 /
Opus 5) to specify and implement the Matrix integration in Talk (spreed), 2026-08-29/31.
Prompts are quoted verbatim, typos included. The whole project — specification interview,
a standalone Matrix client library with an E2EE stack verified against Element's vodozemac,
and the four implementation phases — came from the prompts below; everything else
(architecture, code, tests, debugging, deployments) was done by the agent in response.

---

## 1 · Kick-off and specification interview

> we want to add Matrix support to the Nextcloud Talk app (spreed). The idea is that
> rooms/conversations in Talk can be either native in Talk or Matrix room displayed in Talk.
> The idea is to add a Matrix client to Talk so that Talk can connect for some room to a Matrix
> server like synapse. It should be detected what features are supported in matrix rooms and
> what not. Thew Talk userinterface should then show the features or not. The web interface
> should fully work same as the Nextcloud mobile and desktop apps. If the matrix room is
> encrypted then the encfryption should terminate in the Matrix client in Talk. Can you
> interview me to ask all possible open questions and concerns about this approach sos that you
> can write a specification that can be later handed to Claude Code for implementation?

The agent then ran a structured interview (multiple-choice questions). Questions and the
chosen answers:

| # | Question | Answer chosen |
|---|---|---|
| 1 | Where should the Matrix client (the thing that speaks the Client-Server API to Synapse) live? | **Server-side in Nextcloud (PHP)** — Talk's backend acts as the Matrix client; mobile/desktop apps work unchanged |
| 2 | Who is the Matrix user? How do Nextcloud users map to Matrix accounts? | **One Matrix account per Nextcloud user** |
| 3 | Which Matrix rooms should appear in Talk? | **All rooms of the linked Matrix account** |
| 4 | Which homeservers are in scope? | **Admin-configured homeserver(s) only** |
| 5 | How does a user link their Matrix account? | **Username + password in personal settings** (token stored, password discarded) |
| 6 | How should Talk keep in sync (PHP has no long-lived process)? | **Background-job polling now, appservice/HPB push later** |
| 7 | Where do Olm/Megolm keys live for encrypted rooms? | **In the Nextcloud server, but plaintext never persisted** *(later revised, see prompt 3)* |
| 8 | How should Matrix messages be stored/served? | **Mirror into Talk's own comments/messages tables** |
| 9 | Conflict: "plaintext never persisted" vs "mirror into Talk tables" — what does the row contain? | **Store ciphertext in the Talk row, decrypt on read** *(later revised, see prompt 3)* |
| 10 | How should Olm/Megolm be implemented in PHP? | **Pure-PHP implementation** (sodium/openssl, no binaries) |
| 11 | Device verification / cross-signing? | **Support cross-signing: user signs the Talk device from Element (SAS/emoji)** |
| 12 | Several NC users in the same Matrix room — one conversation or one per user? | **One shared Talk conversation per Matrix room** |
| 13 | Feature detection per room? | **Static matrix on room state + homeserver /versions**, exposed on the conversation object |
| 14 | Audio/video calls in Matrix rooms? | **No calls in v1** |
| 15 | Files and media? | **Both directions** — Matrix media inline, NC file shares uploaded to the media repo |
| 16 | Which message features must round-trip in v1? | **Reactions, edits & deletions, replies & threads, read receipts & typing** (all selected) |
| 17 | Room lifecycle from Talk? | **Full**: join/leave/invites + create rooms & DMs + invite by MXID |
| 18 | How do Matrix-only participants appear? | **New actor type `matrix`** with name + avatar from Matrix |
| 19 | Power levels ↔ Talk roles? | **PL ≥ 50 → Talk moderator; actions enforced Matrix-side** |
| 20 | How much history to import? | **Recent window (200 events / 30 days), lazy-load older on scroll** |
| 21 | Mobile/desktop compatibility level? | **Existing released apps work unchanged; client polish optional** |
| 22 | Notifications? | **Reuse Talk's pipeline; initial level from Matrix push rules** |
| 23 | Unlinking / user deletion? | **Logout device, wipe keys+token; keep shared rooms, else delete** |
| 24 | Admin settings scope? | **All**: homeservers + toggle, group restriction, operational knobs + health, E2EE/upload policies |
| 25 | Rich text & mentions? | **Markdown ↔ HTML conversion + mention mapping** |
| 26 | Testing strategy? | **PHPUnit with mocked HTTP + integration tests against dockerised Synapse** |
| 27 | Delivery phasing? | **Four shippable phases** with acceptance criteria |
| 28 | Where should the spec live? | Repo root *(turned out read-only → placed at `apps/spreed/docs/matrix/SPEC.md`)* |

## 2 · Spec revisions

> i changed my opinion. store the messages unencrypted to make search possible

*(Reverses interview answers 7/9: encrypted rooms are decrypted once at ingestion and stored
as plaintext — full-text search, previews and all clients work; the Nextcloud server is the
trust boundary.)*

> make sure that the matrix client code lived in it's own independent directory so that it can
> be relesed and used independently.

*(Led to the two-layer design: framework-free library `nextcloud/matrix-client-php` in
`matrix-client/` — no `OCP` imports, own CI — and Talk glue in `lib/Matrix/`.)*

## 3 · Phase 1 — linking + unencrypted rooms

> please implement phase 1

> please go on

## 4 · Debugging & polish after the first deployment

> the frontend is broken. can you rebuild?

> look at the screenshot *(screenshot: Talk web page rendered without any CSS — cause: rsync had deployed assets with mode 700, Apache answered 403 for every stylesheet)*

> the beta label looks wrong *(screenshot: the "Beta" chip next to "Matrix rooms" unstyled — the badge styles were scoped to the Federation component)*

> i try to save the groups who can use matrix. but got an error *(cause: OCS controllers must live in `lib/Controller/` for attribute routes to register — all Matrix endpoints were 404)*

> by default the matrix integration should be abailable for everyone. except if it's limited to groups

> i linked my account but no rooms show up *(three real bugs: `matrix` missing in the room-type whitelist, sync token advancing past failed batches, moderator permission updates rejected)*

## 5 · Phase 2 — end-to-end encryption

> implement phase 2

*(Pure-PHP Olm/Megolm/SAS, verified byte-for-byte against Element's vodozemac library;
decrypt-on-ingest with placeholders; key sharing; verification UI.)*

## 6 · UI distinction of Matrix rooms

> how can a matrix room be distinguished from native talk rooms? and how an an encrypted matrix
> room distinguished from an unencrypted? make suggestions?

> i follow your suggestion

*(Avatar badges `[m]`/lock, `Matrix · Encrypted` header chip, composer lock, amber unverified
state with a "Verify device" banner.)*

> i don't see the verification request on other devices *(cause: the device's keys had been
> created by a code path that never uploaded them — other clients ignore verification requests
> from unknown devices; fixed with a device-keys-first guarantee)*

## 7 · Keeping the spec honest

> update the spec with the latest changes. and keep on going this automatically

## 8 · Phases 3 and 4

> implement phase 3

*(Reactions/edits/deletions/threads both ways, read receipts, media with thumbnails,
"Save to Nextcloud", uploads incl. encrypted attachments.)*

> implement phase 4

*(Create rooms/DMs, join by address, public room directory, knock, key-backup restore with
recovery key, Matrix mention autocomplete, `occ talk:matrix:rekey`.)*

## 9 · Final polish

> is there a way to saa the matrix room avatar?

*(Room avatars from `m.room.avatar`, peer avatars for DMs.)*

> is there a way to see the real display name of the room members with avatrars?

*(Avatars of Matrix-only members proxied through the homeserver; the participants API needed
an explicit branch for the `matrix` actor type to return display names.)*

> please list all the prompts that I used to implement and specify this matrix integration.
> including questions and answers for the specification so that I can show the to someone else.
> ideally as markdown

*(This document.)*

---

## Outcome, in numbers

- **~18 prompts** (plus one 28-question interview) → complete integration in 2 days
- Standalone library `matrix-client/`: transport, sync, room state, Olm, Megolm, SAS,
  key backup, attachments — **58 unit tests**, **39 interop checks against vodozemac**
- Talk glue `lib/Matrix/`: 2 migrations (11 tables), sync pipeline, E2EE service,
  6 controllers, 4 occ commands, admin + personal settings UI, room dialog — **17 unit tests**
- Spec (`SPEC.md`, ~740 lines) kept in sync with every decision and lesson
