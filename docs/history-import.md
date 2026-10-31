# History import

Apps that move chat history from another system into Talk (for example a migration from Microsoft Teams, or a restore) use the PHP interface `OCA\Talk\Import\IHistoryImporter`. It is an internal API for other server apps, there is no HTTP endpoint. Get it from the server container and check that it exists, older Talk versions don't have it:

```php
if (interface_exists(\OCA\Talk\Import\IHistoryImporter::class)) {
	$importer = \OCP\Server::get(\OCA\Talk\Import\IHistoryImporter::class);
}
```

## What it writes

* **Conversations** (`ensureConversation`): group or one-to-one conversations with their original time as last activity, optionally read-only, linked to a calendar event (`event`) or a file (`file`, the file ID). For an event the importer passes the event's UID and Talk stores its SHA-256 (lowercase hex) as object ID: UIDs are often longer than the 64 characters of the column (Exchange UIDs have well over 100) and may contain `#`, which Talk reads as the `start#end` of its own event conversations. Other apps find the conversation again with `Manager::getRoomByObject('event', hash('sha256', $uid))`; Talk's calendar integration finds it, like any conversation, through its link in the event's location. The importing account is the only participant of a group conversation until the participants are set. A one-to-one conversation is refused when the two users already have one.
* **Participants** (`setParticipants`): the complete list of participants the importer manages. Participants added in Talk are never changed or removed. Each participant can have a role, the time their history starts (`historySince`, see below), and their own state: archived, muted, favorite and the time up to which they read. Read markers start at that time or at the newest message, so imported messages are never unread.
* **Messages** (`importMessages`): text, replies, threads (a post with a title), reactions, edits (the final text with the edit time), membership messages (`user_added`, `user_removed`, `history_shared`) and files. Files are shared into the conversation as room shares of their owner, read-only; the message is one file share message per file with the text as caption of the last one. Authors, reactors and members without an account are placeholder actors of the type `imported` with a stable ID and the name the source showed. They are never participants, can not be mentioned and their messages can not be replied to.
* `finish` sets the last message and last activity of the conversation once, after a round of imports.
* Talk's cleanup jobs never remove a conversation an import created: neither the removal of empty conversations (a one-to-one conversation has no participant until they are set) nor the retention of event conversations, whose imported history is old by nature. Locking inactive conversations (off by default) applies to them too, but counts their inactivity from the time the import last wrote to them (creating them, setting participants, `finish`) instead of from their last message, which is old by nature: the admin's period starts at the switch-over.

Everything is keyed by the importing app and its own refs, so every call is safe to repeat: the same ref with the same content is answered without a write. A ref imported with other content is answered with the conflict `exists`, a message that was edited or deleted in Talk with `changed`; neither is overwritten. Messages can be replaced with a new version (text, edit time, author, time and reactions) as long as nobody changed them in Talk, and removed (`removeMessages`), which shows them as deleted.

## Quiet

The importer writes through the comments manager and the mappers, not through the services of the live chat. None of Talk's chat, participant, reaction, conversation or share events is dispatched, so nobody gets a notification, push notification or email, and no activity, bot, webhook, signaling or federation request happens. Mentions in imported messages are not marked. Creating conversations, changing participants and removing messages is written to the admin audit log.

## History start

A participant with a history start (`history_since` of the attendee) does not see messages from before that time: the chat, message context, search, shared items, threads, reactions, notifications, reference previews and the parents and thread titles of later replies leave them out, and room shares of files from that time are not shared with them. Imports use it for people who were added to a chat without its earlier history.

## Canonical form

`statMessages` answers the SHA-256 of what Talk holds for a message. The importing app computes the same hash from its source to check that a message arrived unchanged. The canonical form is ten values, each JSON-encoded without escaping slashes or non-ASCII characters, joined with a line feed:

1. ref
2. parent ref (`""` for none)
3. actor type (`users` or `imported`)
4. actor ID
5. creation time, RFC 3339 in UTC with seconds (`2026-10-30T09:15:00Z`)
6. text (`""` for membership messages)
7. file IDs, ascending
8. reactions as `[actor type, actor ID, emoji]`, sorted byte-wise
9. membership message kind (`""` for none)
10. members as `[actor type, actor ID]`, sorted byte-wise

The hash is the lowercase hexadecimal SHA-256 of the UTF-8 bytes. Example: the message `msg-1` by user `alice` at `2026-10-30T09:15:00Z` with the text `Hello 👋 <b>& "quoted" a/b`, the files 42 and 7 and the reactions 👍 by placeholder `ph-1` and ❤️ by user `bob` has the hash `bc16b20454234bd447ed8768403d3a25ec7ae72476b8e29a5ba0c54e86cedb59`.
