# Matrix integration

## Concept

Users link their Matrix account in the Talk settings. From then on the Nextcloud server acts as a Matrix client of that account: it logs in as an additional device, syncs with the homeserver in the background and mirrors the Matrix rooms as conversations with the object type `matrix`. Matrix users without a Nextcloud account take part as attendees of the type `matrix`.

Nothing is federated between servers: changes made in Talk are sent to the homeserver by the account of the user, changes on Matrix arrive with the next sync. Messages are always sent to the homeserver first and only shown in Talk once it accepted them.

## Configuration

1. Enable the integration in the Talk administration settings (section Matrix) and optionally limit it to groups. Alternatively use the app config values `matrix_enabled` and `matrix_allowed_groups`.
2. Add the homeservers users may link accounts on, in the administration settings or with `occ talk:matrix-homeserver:add`. The client API URL is discovered with `.well-known/matrix/client` and checked with `occ talk:matrix-homeserver:test`.
3. Make sure that:
   * background jobs run with cron, the accounts are synced every 30 seconds and while a Matrix conversation is open,
   * the server can reach the homeservers (homeservers in the local network need `allow_local_remote_servers`),
   * the PHP extension sodium is available, otherwise end-to-end encrypted rooms stay read-only.

Users then link their account in the Talk settings with their Matrix user and password. For encrypted rooms they also verify the server with emoji from another Matrix device and can restore older keys with the recovery key of their key backup.

Accounts are listed, synced and unlinked with the `occ talk:matrix-account:*` commands, see [Occ commands](occ.md).

## Homeserver requirements

* Client-Server API `v3` with password login (`m.login.password`), homeservers that only offer single sign-on can not be linked
* Authenticated media (spec v1.11) is used when available, with a fallback to the older media API
* Threads (spec v1.4) and intentional mentions (`m.mentions`, spec v1.7) degrade to replies and plain names on older homeservers and clients

## Supported features

* Messages with formatting, replies, threads, edits, deletions, reactions and mentions in both directions
* Attachments in both directions, with previews of images, and locations from Matrix
* Read markers and read receipts, notification levels synced with the push rules of the account
* Room name, description and avatar, avatars of Matrix users
* Inviting, removing, promoting and demoting members, leaving rooms, accepting and declining invites
* Creating rooms and direct chats and joining rooms by their address
* Earlier messages of new conversations and of gaps in the timeline (up to 50)
* End-to-end encrypted rooms, including verification of the server, key requests to other devices of the user and restoring keys from the key backup

## Known missing features

* Typing indicators
* Calls
* Sharing polls, Deck cards and other rich objects
* Moderation without a Matrix equivalent (lobby, permissions, banning, …) is refused with `406 Not Acceptable`
* Loading messages older than the initially loaded history
* Creating end-to-end encrypted rooms from Talk
* Recovery passphrases (only the recovery key) and uploading keys to the key backup
* Spaces

## End-to-end encryption

The server is the end of the end-to-end encryption: it decrypts the messages of encrypted rooms and stores them unencrypted in the database, like all other chat messages. Keys and sessions are stored encrypted with the instance secret. Users should only verify the server when they trust it with their messages.
