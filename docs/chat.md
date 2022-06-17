# Chat API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 13

All calls to OCS endpoints require the `OCS-APIRequest` header to be set to `true`.

## Receive chat messages of a conversation

* Method: `GET`
* Endpoint: `/chat/{token}`
* Data:

    field | type | Description
    ---|---|---
    `lookIntoFuture` | int | `1` Poll and wait for new message or `0` get history of a conversation
    `limit` | int | Number of chat messages to receive (100 by default, 200 at most)
    `lastKnownMessageId` | int | Serves as an offset for the query. The lastKnownMessageId for the next page is available in the `X-Chat-Last-Given` header.
    `lastCommonReadId` | int | Send the last `X-Chat-Last-Common-Read` header you got, if you are interested in updates of the common read value. A 304 response does not allow custom headers and otherwise the server can not know if your value is modified or not.
    `timeout` | int | `$lookIntoFuture = 1` only, Number of seconds to wait for new messages (30 by default, 60 at most)
    `setReadMarker` | int | `1` to automatically set the read timer after fetching the messages, use `0` when your client calls `Mark chat as read` manually. (Default: `1`)
    `includeLastKnown` | int | `1` to include the last known message as well (Default: `0`)

* Response:
    - Status code:
        + `200 OK`
        + `304 Not Modified` When there were no older/newer messages
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Given` | int | Offset (lastKnownMessageId) for the next page.
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability and when lastCommonReadId was sent)

    - Data:
        Array of messages, each message has at least:

        field | type | Description
        ---|---|---
        `id` | int | ID of the comment
        `token` | string | Conversation token
        `actorType` | string | See [Constants - Actor types of chat messages](constants.md#actor-types-of-chat-messages)
        `actorId` | string | Actor id of the message author
        `actorDisplayName` | string | Display name of the message author
        `timestamp` | int | Timestamp in seconds and UTC time zone
        `systemMessage` | string | empty for normal chat message or the type of the system message (untranslated)
        `messageType` | string | Currently known types are `comment`, `comment_deleted`, `system` and `command`
        `isReplyable` | bool | True if the user can post a reply to this message (only available with `chat-replies` capability)
        `referenceId` | string | A reference string that was given while posting the message to be able to identify a sent message again (only available with `chat-reference-id` capability)
        `message` | string | Message string with placeholders (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
        `messageParameters` | array | Message parameters for `message` (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
        `parent` | array | **Optional:** See `Parent data` below
        `reactions` | array | **Optional:** An array map with relation between reaction emoji and total count of reactions with this emoji
        `reactionsSelf` | array | **Optional:** When the user reacted this is the list of emojis the user reacted with

#### Parent data

* When deleted:

    field | type | Description
    ---|---|---
    `id` | int | ID of the parent comment
    `deleted` | bool | `true` when the parent is deleted

* Regular message:

    Full message array as shown above, but `parent` will never be set for a parent message.


## Sending a new chat message

* Method: `POST`
* Endpoint: `/chat/{token}`
* Data:

    field | type | Description
    ---|---|---
    `message` | string | The message the user wants to say
    `actorDisplayName` | string | Guest display name (ignored for logged in users)
    `replyTo` | int | The message ID this message is a reply to (only allowed for messages from the same conversation and when the message type is not `system` or `command`)
    `referenceId` | string | A reference string to be able to identify the message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability)
    `silent` | bool | If sent silent the message will not create chat notifications even for mentions (only available with `silent-send` capability)

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case of any other error
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
        + `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (or 1000 until Nextcloud 16.0.1, check the `spreed => config => chat => max-length` capability for the limit)

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)

    - Data:
        The full message array of the new message, as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## Share a rich object to the chat

See [OCP\RichObjectStrings\Definitions](https://github.com/nextcloud/server/blob/master/lib/public/RichObjectStrings/Definitions.php) for more details on supported rich objects and required data.

* Required capability: `rich-object-sharing`
* Method: `POST`
* Endpoint: `/chat/{token}/share`
* Data:

    field | type | Description
    ---|---|---
    `objectType` | string | The object type
    `objectId` | string | The object id
    `metaData` | string | JSON encoded array of the rich objects data
    `actorDisplayName` | string | Guest display name (ignored for logged in users)
    `referenceId` | string | A reference string to be able to identify the message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability)

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case the meta data is invalid
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
        + `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (or 1000 until Nextcloud 16.0.1, check the `spreed => config => chat => max-length` capability for the limit)

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)

    - Data:
        The full message array of the new message, as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## Share a file to the chat

* Method: `POST`
* Endpoint: `ocs/v2.php/apps/files_sharing/api/v1/shares`
* Data:

    field | type | Description
    ---|---|---
    `shareType` | int | `10` means share to a conversation
    `shareWith` | string | The token of the conversation to share the file to
    `path` | string | The file path inside the user's root to share
    `referenceId` | string | A reference string to be able to identify the generated chat message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability)
    `talkMetaData` | string | JSON encoded array of the meta data

* `talkMetaData` array:

    field | type | Description
    ---|---|---
    `messageType` | string | A message type to show the message in different styles. Currently known: `voice-message` and `comment`

* Response: [See official OCS Share API docs](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-share-api.html?highlight=sharing#create-a-new-share)

## List overview of items shared into a chat

* Required capability: `rich-object-list-media`
* Method: `GET`
* Endpoint: `/chat/{token}/share/overview`
* Data:

  field | type | Description
  ---|---|---
  `limit` | int  | Number of chat messages with shares you want to get

* Response:
    - Note: if a file was shared multiple times it will be returned multiple times
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:
        + An array per item type
            - Array of messages as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## List items of type shared in a chat

* Required capability: `rich-object-list-media`
* Method: `GET`
* Endpoint: `/chat/{token}/share`
* Data:

  field | type | Description
  ---|---|---
  `objectType` | string | One of the [Constants - Shared item types](constants.md#shared-item-types)
  `lastKnownMessageId` | int  | Serves as an offset for the query. The lastKnownMessageId for the next page is available in the `X-Chat-Last-Given` header.
  `limit` | int  | Number of chat messages with shares you want to get

* Response:
    - Note: if a file was shared multiple times it will be returned multiple times
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

      field | type | Description
      ---|---|---
      `X-Chat-Last-Given` | int | Offset (lastKnownMessageId) for the next page.

    - Data:
      Array of messages as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## Clear chat history

* Required capability: `clear-history`
* Method: `DELETE`
* Endpoint: `/chat/{token}`

* Response:
    - Status code:
        + `200 OK` - When deleting was successful
        + `202 Accepted` - When deleting was successful but Matterbridge is enabled so the message was leaked to other services
        + `403 Forbidden` When the user is not a moderator
        + `404 Not Found` When the conversation could not be found for the participant

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)

    - Data:
        The full message array of the new system message "You cleared the history of the conversation", as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)
        When rendering this message the client should also remove all messages from any cache/storage of the device.


## Deleting a chat message

* Required capability: `delete-messages` - `rich-object-delete` indicates if shared objects can be deleted from the chat
* Method: `DELETE`
* Endpoint: `/chat/{token}/{messageId}`

* Response:
    - Status code:
        + `200 OK` - When deleting was successful
        + `202 Accepted` - When deleting was successful but Matterbridge is enabled so the message was leaked to other services
        + `400 Bad Request` The message is already older than 6 hours
        + `403 Forbidden` When the message is not from the current user and the user not a moderator
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation or chat message could not be found for the participant
        + `405 Method Not Allowed` When the message is not a normal chat message
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)

    - Data:
        The full message array of the new system message "You deleted a message", as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)
        The parent message is the object of the deleted message with the replaced text "Message deleted by you".
        This message should **NOT** be displayed to the user but instead be used to remove the original message from any cache/storage of the device.


## Mark chat as read

* Required capability: `chat-read-marker`
* Method: `POST`
* Endpoint: `/chat/{token}/read`
* Data:

    field | type | Description
    ---|---|---
    `lastReadMessage` | int | The last read message ID

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant,
        or the participant is a guest.

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)


## Mark chat as unread

* Required capability: `chat-unread`
* Method: `DELETE`
* Endpoint: `/chat/{token}/read`

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant,
        or the participant is a guest.

    - Header:

        field | type | Description
        ---|---|---
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)


## Get mention autocomplete suggestions

* Method: `GET`
* Endpoint: `/chat/{token}/mentions`
* Data:

    field | type | Description
    ---|---|---
    `search` | string | Search term for name suggestions (should at least be 1 character)
    `limit` | int | Number of suggestions to receive (20 by default)
    `includeStatus` | bool | Whether the user status information also needs to be loaded

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:
        Array of suggestions, each suggestion has at least:

        field | type | Description
        ---|---|---
        `id` | string | The user id which should be sent as `@<id>` in the message (user ids that contain spaces as well as guest ids need to be wrapped in double-quotes when sending in a message: `@"space user"` and `@"guest/random-string"`)
        `label` | string | The displayname of the user
        `source` | string | The type of the user, currently only `users`, `guests` or `calls` (for mentioning the whole conversation
        `status` | string | Optional: Only available with `includeStatus=true` and for users with a set status
        `statusIcon` | string | Optional: Only available with `includeStatus=true` and for users with a set status
        `statusMessage` | string | Optional: Only available with `includeStatus=true` and for users with a set status

## System messages

* `conversation_created` - {actor} created the conversation
* `conversation_renamed` - {actor} renamed the conversation from "foo" to "bar"
* `description_set` - {actor} set the description
* `description_removed` - {actor} removed the description
* `call_started` - {actor} started a call
* `call_joined` - {actor} joined the call
* `call_left` - {actor} left the call
* `call_ended` - Call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration 30:23)
* `call_ended_everyone` - {user1} ended the call with {user2}, {user3}, {user4} and {user5} (Duration 30:23)
* `call_missed` - You missed a call from {user}
* `call_tried` - You tried to call {user}
* `read_only_off` - {actor} unlocked the conversation
* `read_only` - {actor} locked the conversation
* `listable_none` - {actor} limited the conversation to the current participants
* `listable_users` - {actor} opened the conversation accessible to registered users
* `listable_all` - {actor} opened the conversation accessible to registered and guest app users
* `lobby_timer_reached` - The conversation is now open to everyone
* `lobby_none` - {actor} opened the conversation to everyone
* `lobby_non_moderators` - {actor} restricted the conversation to moderators
* `guests_allowed` - {actor} allowed guests in the conversation
* `guests_disallowed` - {actor} disallowed guests in the conversation
* `password_set` - {actor} set a password for the conversation
* `password_removed` - {actor} removed the password for the conversation
* `user_added` - {actor} added {user} to the conversation
* `user_removed` - {actor} removed {user} from the conversation
* `group_added` - {actor} added group {group} to the conversation
* `group_removed` - {actor} removed group {group} from the conversation
* `circle_added` - {actor} added circle {circle} to the conversation
* `circle_removed` - {actor} removed circle {circle} from the conversation
* `moderator_promoted` - {actor} promoted {user} to moderator
* `moderator_demoted` - {actor} demoted {user} from moderator
* `guest_moderator_promoted` - {actor} promoted {user} to moderator
* `guest_moderator_demoted` - {actor} demoted {user} from moderator
* `message_deleted` - Message deleted by {actor} (Should not be shown to the user)
* `history_cleared` - {actor} cleared the history of the conversation
* `file_shared` - {file}
* `object_shared` - {object}
* `matterbridge_config_added` - {actor} set up Matterbridge to synchronize this conversation with other chats
* `matterbridge_config_edited` - {actor} updated the Matterbridge configuration
* `matterbridge_config_removed` - {actor} removed the Matterbridge configuration
* `matterbridge_config_enabled` - {actor} started Matterbridge
* `matterbridge_config_disabled` - {actor} stopped Matterbridge
* `reaction` - {reaction}
* `reaction_deleted` - Reaction deleted by author (replacement of `reaction` after the action has been performed)
* `reaction_revoked` - {actor} deleted a reaction (the action that will replace `reaction` with a `reaction_deleted` message)

