# Chat API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 13

## Receive chat messages of a conversation

!!! note

    Due to the structure of the `lastMessage.reactions` array the response is not valid in XML.
    It is therefor recommended to use `format=json` or send the `Accept: application/json` header,
    to receive a JSON response.

* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/chat/{token}`
* Data:

| field                     | type | Description                                                                                                                                                                                                                             |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `lookIntoFuture`          | int  | `1` Poll and wait for new message or `0` get history of a conversation                                                                                                                                                                  |
| `limit`                   | int  | Number of chat messages to receive (100 by default, 200 at most)                                                                                                                                                                        |
| `lastKnownMessageId`      | int  | Serves as an offset for the query. The lastKnownMessageId for the next page is available in the `X-Chat-Last-Given` header.                                                                                                             |
| `lastCommonReadId`        | int  | Send the last `X-Chat-Last-Common-Read` header you got, if you are interested in updates of the common read value. A 304 response does not allow custom headers and otherwise the server can not know if your value is modified or not. |
| `timeout`                 | int  | `$lookIntoFuture = 1` only, Number of seconds to wait for new messages (30 by default, 60 at most)                                                                                                                                      |
| `setReadMarker`           | int  | `1` to automatically set the read timer after fetching the messages, use `0` when your client calls `Mark chat as read` manually. (Default: `1`)                                                                                        |
| `includeLastKnown`        | int  | `1` to include the last known message as well (Default: `0`)                                                                                                                                                                            |
| `noStatusUpdate`          | int  | When the user status should not be automatically set to online set to 1 (default 0)                                                                                                                                                     |
| `markNotificationsAsRead` | int  | `0` to not mark notifications as read (Default: `1`, only available with `chat-keep-notifications` capability)                                                                                                                          |

* Response:
    - Status code:
        + `200 OK`
        + `304 Not Modified` When there were no older/newer messages
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

| field                     | type | Description                                                                                                                                                                                                                                        |
|---------------------------|------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Given`       | int  | Offset (lastKnownMessageId) for the next page.                                                                                                                                                                                                     |
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability and when lastCommonReadId was sent) |

    - Data:
        Array of messages, each message has at least:

| field                      | type     | Description                                                                                                                                                                                                                               |
|----------------------------|----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `id`                       | int      | ID of the comment                                                                                                                                                                                                                         |
| `token`                    | string   | Conversation token                                                                                                                                                                                                                        |
| `actorType`                | string   | See [Constants - Actor types of chat messages](constants.md#actor-types-of-chat-messages)                                                                                                                                                 |
| `actorId`                  | string   | Actor id of the message author                                                                                                                                                                                                            |
| `actorDisplayName`         | string   | Display name of the message author (can be empty for type `deleted_users` and `guests`)                                                                                                                                                   |
| `timestamp`                | int      | Timestamp in seconds and UTC time zone                                                                                                                                                                                                    |
| `systemMessage`            | string   | empty for normal chat message or the type of the system message (untranslated)                                                                                                                                                            |
| `messageType`              | string   | Currently known types are `comment`, `comment_deleted`, `system` and `command`                                                                                                                                                            |
| `isReplyable`              | bool     | True if the user can post a reply to this message (only available with `chat-replies` capability)                                                                                                                                         |
| `referenceId`              | string   | A reference string that was given while posting the message to be able to identify a sent message again (only available with `chat-reference-id` capability)                                                                              |
| `message`                  | string   | Message string with placeholders (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))                                                                                                                              |
| `messageParameters`        | array    | Message parameters for `message` (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))                                                                                                                              |
| `expirationTimestamp`      | int      | Unix time stamp when the message expires and show be removed from the clients UI without further note or warning (only available with `message-expiration` capability)                                                                    |
| `parent`                   | array    | **Optional:** See `Parent data` below                                                                                                                                                                                                     |
| `reactions`                | int[]    | **Optional:** An array map with relation between reaction emoji and total count of reactions with this emoji                                                                                                                              |
| `reactionsSelf`            | string[] | **Optional:** When the user reacted this is the list of emojis the user reacted with                                                                                                                                                      |
| `markdown`                 | bool     | **Optional:** Whether the message should be rendered as markdown or shown as plain text                                                                                                                                                   |
| `lastEditActorType`        | string   | **Optional:** Actor type of the last editing author - See [Constants - Actor types of chat messages](constants.md#actor-types-of-chat-messages) (only available with `edit-messages` capability and when the message was actually edited) |
| `lastEditActorId`          | string   | **Optional:** Actor id of the last editing author (only available with `edit-messages` capability and when the message was actually edited)                                                                                               |
| `lastEditActorDisplayName` | string   | **Optional:** Display name of the last editing author (only available with `edit-messages` capability and when the message was actually edited) (can be empty for type `deleted_users` and `guests`)                                      |
| `lastEditTimestamp`        | int      | **Optional:** Unix time stamp when the message was last edited (only available with `edit-messages` capability and when the message was actually edited)                                                                                  |
| `silent`                   | bool     | **Optional:** Whether the message was sent silently (only available with `silent-send-state` capability)                                                                                                                                  |

#### Parent data

* When deleted:

| field     | type | Description                       |
|-----------|------|-----------------------------------|
| `id`      | int  | ID of the parent comment          |
| `deleted` | bool | `true` when the parent is deleted |

* Regular message:

    Full message array as shown above, but `parent` will never be set for a parent message.

## Get context of a message

!!! note

    Due to the structure of the `lastMessage.reactions` array the response is not valid in XML.
    It is therefor recommended to use `format=json` or send the `Accept: application/json` header,
    to receive a JSON response.

* Required capability: `chat-get-context`
* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/chat/{token}/{messageId}/context`
* Data:

| field                | type | Description                                                                         |
|----------------------|------|-------------------------------------------------------------------------------------|
| `limit`              | int  | Number of chat messages to receive into each direction (50 by default, 100 at most) |

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

| field                     | type | Description                                                                                                                                                                                                                                        |
|---------------------------|------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Given`       | int  | Offset (lastKnownMessageId) for the next page when getting more history.                                                                                                                                                                           |
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability and when lastCommonReadId was sent) |

    - Data: See array definition in `Receive chat messages of a conversation`

## Sending a new chat message

* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/chat/{token}`
* Data:

| field              | type   | Description                                                                                                                                                             |
|--------------------|--------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `message`          | string | The message the user wants to say                                                                                                                                       |
| `actorDisplayName` | string | Guest display name (ignored for logged in users)                                                                                                                        |
| `replyTo`          | int    | The message ID this message is a reply to (only allowed for messages from the same conversation and when the message type is not `system` or `command`)                 |
| `referenceId`      | string | A reference string to be able to identify the message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability) |
| `silent`           | bool   | If sent silent the message will not create chat notifications even for mentions (only available with `silent-send` capability)                                          |

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case of any other error
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
        + `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (or 1000 until Nextcloud 16.0.1, check the `spreed => config => chat => max-length` capability for the limit)
        + `429 Too Many Requests` When a guest mentioned other participants too often (50 mention messages per day)

    - Header:

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

    - Data:
        The full message array of the new message, as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## Share a rich object to the chat

See [OCP\RichObjectStrings\Definitions](https://github.com/nextcloud/server/blob/master/lib/public/RichObjectStrings/Definitions.php) for more details on supported rich objects and required data.

* Required capability: `rich-object-sharing`
* Method: `POST`
* Endpoint: `/chat/{token}/share`
* Data:

| field              | type   | Description                                                                                                                                                             |
|--------------------|--------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `objectType`       | string | The object type                                                                                                                                                         |
| `objectId`         | string | The object id                                                                                                                                                           |
| `metaData`         | string | JSON encoded array of the rich objects data                                                                                                                             |
| `actorDisplayName` | string | Guest display name (ignored for logged in users)                                                                                                                        |
| `referenceId`      | string | A reference string to be able to identify the message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability) |

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case the meta data is invalid
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
        + `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (or 1000 until Nextcloud 16.0.1, check the `spreed => config => chat => max-length` capability for the limit)

    - Header:

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

    - Data:
        The full message array of the new message, as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## Share a file to the chat

* Method: `POST`
* Endpoint: `ocs/v2.php/apps/files_sharing/api/v1/shares`
* Data:

| field          | type   | Description                                                                                                                                                                            |
|----------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `shareType`    | int    | `10` means share to a conversation                                                                                                                                                     |
| `shareWith`    | string | The token of the conversation to share the file to                                                                                                                                     |
| `path`         | string | The file path inside the user's root to share                                                                                                                                          |
| `referenceId`  | string | A reference string to be able to identify the generated chat message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability) |
| `talkMetaData` | string | JSON encoded array of the meta data                                                                                                                                                    |

    - `talkMetaData` array:

| field         | type   | Description                                                                                                                                                             |
|---------------|--------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `messageType` | string | A message type to show the message in different styles. Currently known: `voice-message` and `comment`                                                                  |
| `caption`     | string | A caption message that should be shown together with the shared file (only available with `media-caption` capability)                                                   |
| `replyTo`     | int    | The message ID this caption message is a reply to (only allowed for messages from the same conversation and when the message type is not `system` or `command`)         |
| `silent`      | bool   | If sent silent the message will not create chat notifications even for mentions (only available with `media-caption` capability, yes `media-caption` not `silent-send`) |

* Response: [See official OCS Share API docs](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-share-api.html?highlight=sharing#create-a-new-share)

## List overview of items shared into a chat

* Required capability: `rich-object-list-media`
* Method: `GET`
* Endpoint: `/chat/{token}/share/overview`
* Data:

| field   | type | Description                                         |
|---------|------|-----------------------------------------------------|
| `limit` | int  | Number of chat messages with shares you want to get |

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

| field                | type   | Description                                                                                                                 |
|----------------------|--------|-----------------------------------------------------------------------------------------------------------------------------|
| `objectType`         | string | One of the [Constants - Shared item types](constants.md#shared-item-types)                                                  |
| `lastKnownMessageId` | int    | Serves as an offset for the query. The lastKnownMessageId for the next page is available in the `X-Chat-Last-Given` header. |
| `limit`              | int    | Number of chat messages with shares you want to get                                                                         |

* Response:
    - Note: if a file was shared multiple times it will be returned multiple times
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

| field               | type | Description                                    |
|---------------------|------|------------------------------------------------|
| `X-Chat-Last-Given` | int  | Offset (lastKnownMessageId) for the next page. |

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

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

    - Data:
        The full message array of the new system message "You cleared the history of the conversation", as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)
        When rendering this message the client should also remove all messages from any cache/storage of the device.


## Deleting a chat message

* Required capability: `delete-messages` - `rich-object-delete` indicates if shared objects can be deleted from the chat
* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/chat/{token}/{messageId}`

* Response:
    - Status code:
        + `200 OK` - When deleting was successful
        + `202 Accepted` - When deleting was successful, but a bot or Matterbridge is configured, so the information can be replicated to other services
        + `400 Bad Request` The message is already older than 6 hours
        + `403 Forbidden` When the message is not from the current user and the user not a moderator
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation or chat message could not be found for the participant
        + `405 Method Not Allowed` When the message is not a normal chat message
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

    - Data:
        The full message array of the new system message "You deleted a message", as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)
        The parent message is the object of the deleted message with the replaced text "Message deleted by you".
        This message should **NOT** be displayed to the user but instead be used to remove the original message from any cache/storage of the device.


## Editing a chat message

* Required capability: `edit-messages`
* Federation capability: `federation-v1`
* Method: `PUT`
* Endpoint: `/chat/{token}/{messageId}`
* Data:

| field              | type   | Description                       |
|--------------------|--------|-----------------------------------|
| `message`          | string | The message the user wants to say |

* Response:
    - Status code:
        + `200 OK` - When editing was successful
        + `202 Accepted` - When editing was successful, but a bot or Matterbridge is configured, so the information can be replicated to other services
        + `400 Bad Request` The message is already older than 24 hours or another reason why editing is not okay
        + `403 Forbidden` When the message is not from the current user and the user not a moderator
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation or chat message could not be found for the participant
        + `405 Method Not Allowed` When the message is not a normal chat message
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

    - Data:
        The full message array of the new system message "You edited a message", as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)
        The parent message is the object of the edited message with the new content.
        This message should **NOT** be displayed to the user but instead be used to update the original message from any cache/storage of the device.

## Set reminder for chat message

* Required capability: `remind-me-later`
* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/chat/{token}/{messageId}/reminder`
* Data:

| field       | type | Description                                                                                                                                         |
|-------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| `timestamp` | int  | Timestamp when the notification should be triggered. Preferable options for 6pm today, 8am tomorrow, Saturday 8am and Monday 8am should be offered. |

* Response:
    - Status code:
        + `201 Created`
        + `401 Unauthorized` when the user is not logged in
        + `404 Not Found` When the message could not be found in the room
        + `404 Not Found` When the room could not be found for the participant,
          or the participant is a guest.
    - Data:
        Array with the details of the reminder

| field       | type   | Description                                  |
|-------------|--------|----------------------------------------------|
| `userId`    | string | The user id of the user                      |
| `token`     | string | The token of the conversation of the message |
| `messageId` | int    | The message id this reminder is for          |
| `timestamp` | int    | The timestamp when the reminder is triggered |

## Get reminder for chat message

* Required capability: `remind-me-later`
* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/chat/{token}/{messageId}/reminder`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in
        + `404 Not Found` When the message could not be found in the room
        + `404 Not Found` When the room could not be found for the participant,
          or the participant is a guest.
        + `404 Not Found` When the user has no reminder for this message
    - Data:
        Array with the details of the reminder

| field       | type   | Description                                  |
|-------------|--------|----------------------------------------------|
| `userId`    | string | The user id of the user                      |
| `token`     | string | The token of the conversation of the message |
| `messageId` | int    | The message id this reminder is for          |
| `timestamp` | int    | The timestamp when the reminder is triggered |

## Delete reminder for chat message

* Required capability: `remind-me-later`
* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/chat/{token}/{messageId}/reminder`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in
        + `404 Not Found` When the message could not be found in the room
        + `404 Not Found` When the room could not be found for the participant,
          or the participant is a guest.

## Mark chat as read

* Required capability: `chat-read-marker`
* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/chat/{token}/read`
* Data:

| field             | type     | Description                                                          |
|-------------------|----------|----------------------------------------------------------------------|
| `lastReadMessage` | int/null | The last read message ID (Optional with `chat-read-last` capability) |

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant,
        or the participant is a guest.

    - Data in case of `200 OK`:
        + **Without** `federation-v1` capability empty
        + **With** `federation-v1` capability, see array definition in [Get user´s conversations](conversation.md#get-user-s-conversations)

    - Header:

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

## Mark chat as unread

* Required capability: `chat-unread`
* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/chat/{token}/read`

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant,
        or the participant is a guest.

    - Data in case of `200 OK`:
        + **Without** `federation-v1` capability empty
        + **With** `federation-v1` capability, see array definition in [Get user´s conversations](conversation.md#get-user-s-conversations)

    - Header:

| field                     | type | Description                                                                                                                                                                                                     |
|---------------------------|------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Chat-Last-Common-Read` | int  | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability) |

## Get mention autocomplete suggestions

* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/chat/{token}/mentions`
* Data:

| field           | type   | Description                                                       |
|-----------------|--------|-------------------------------------------------------------------|
| `search`        | string | Search term for name suggestions (should at least be 1 character) |
| `limit`         | int    | Number of suggestions to receive (20 by default)                  |
| `includeStatus` | bool   | Whether the user status information also needs to be loaded       |

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:
        Array of suggestions, each suggestion has at least:

| field           | type   | Description                                                                                                                                                                                                                                                               |
|-----------------|--------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `mentionId`     | string | Optional: Only available with `federation-v1` capability - the identifier which should be sent as `@<id>` in the message (ids that contain spaces or slashes need to be wrapped in double-quotes when sending in a message: `@"space user"` and `@"guest/random-string"`) |
| `id`            | string | The id of the participant to mention. Note: while this should not be inserted into the message field when `mentionId` is present, it still has to be used to load e.g. avatar and user status of users that are from the same proxy server.                               |
| `label`         | string | The display name of the mention option                                                                                                                                                                                                                                    |
| `source`        | string | The type of the mention option, currently only `users`, `federated_users`, `group`, `guests` or `calls` (for mentioning the whole conversation)                                                                                                                           |
| `status`        | string | Optional: Only available with `includeStatus=true` and for users with a set status                                                                                                                                                                                        |
| `statusIcon`    | string | Optional: Only available with `includeStatus=true` and for users with a set status                                                                                                                                                                                        |
| `statusMessage` | string | Optional: Only available with `includeStatus=true` and for users with a set status                                                                                                                                                                                        |
| `details`       | string | Optional: Only provided for the "Everyone" option and can be used as a subline directly                                                                                                                                                                                   |

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
* `listable_all` - {actor} opened the conversation accessible to registered users and users created with the Guests app
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
* `message_edited` - Message edited by {actor} (Should not be shown to the user)
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
* Creating a poll is an `object_shared` with a poll object
* `poll_voted` - Someone voted on the poll {poll}
* `poll_closed` - {actor} closed the poll {poll}
* `message_expiration_enabled` - {actor} set the message expiration to 3 hours
* `message_expiration_disabled` - {actor} disabled message expiration
* `breakout_rooms_started` - {actor} started breakout rooms
* `breakout_rooms_stopped` - {actor} stopped breakout rooms
* `recording_started` - {actor} started a video recording
* `recording_stopped` - {actor} stopped a video recording
* `audio_recording_started` - {actor} started an audio recording
* `audio_recording_stopped` - {actor} stopped an audio recording
* `avatar_set` - {actor} set the conversation avatar
* `avatar_removed` - {actor} removed the conversation avatar
* `federated_user_added` - {actor} invited {federated_user} / {federated_user} accepted the invitation
* `federated_user_removed` - {actor} removed {federated_user} / {federated_user} declined the invitation
