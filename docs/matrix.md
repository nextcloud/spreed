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

Users then link their account in the Talk settings, with their Matrix user and password or with the single sign-on of the homeserver (see below). For encrypted rooms they also verify the server with emoji from another Matrix device and can restore older keys with the recovery key of their key backup.

Accounts are listed, synced and unlinked with the `occ talk:matrix-account:*` commands, see [Occ commands](occ.md).

## Homeserver requirements

* Client-Server API `v3` with password login (`m.login.password`) or single sign-on (`m.login.sso` with `m.login.token`)
* Authenticated media (spec v1.11) is used when available, with a fallback to the older media API
* Threads (spec v1.4) and intentional mentions (`m.mentions`, spec v1.7) degrade to replies and plain names on older homeservers and clients

## Single sign-on

When a homeserver offers single sign-on, the Talk settings show a button per identity provider (or one button when the homeserver does not list them). The homeservers that only offer single sign-on show no password field. The login types are asked from the homeserver (`GET /_matrix/client/v3/login`) and reused for an hour, `occ talk:matrix-homeserver:test` asks again.

1. The button opens `/index.php/apps/spreed/matrix/sso/{homeserverId}/start` (`mode=link`, or `mode=relogin` after the homeserver ended the session). Talk keeps a random state in the session of the user and sends the browser to `/_matrix/client/v3/login/sso/redirect[/{idp}]` of the homeserver, with `redirectUrl` pointing to the callback of Talk.
2. The user logs in at the identity provider. Unless the URL of Talk is in the `sso.client_whitelist` of the homeserver, the homeserver asks whether to continue to Talk.
3. The homeserver sends the browser to `/index.php/apps/spreed/matrix/sso/callback?state=…&loginToken=…`. Talk checks the state (once, at most 10 minutes old, same user), exchanges the login token for a device of its own (`m.login.token`) and sends the browser back to Talk, which opens the settings with the result.

The Matrix user is the one the homeserver logged in. It has to belong to the homeserver; when logging in again it has to be the linked user, otherwise the new session is logged out right away. Login tokens are only valid once and for a short time, but they are part of a URL: the access logs of a reverse proxy contain them.

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
