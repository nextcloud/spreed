# Bots and Webhooks

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`: (requires the `bots-v1` capability - available since Nextcloud 27.1)

Webhook based bots are available with the Nextcloud 27.1 compatible Nextcloud Talk 17.1 release as a first version

!!! note

    For security reasons bots can only be added via the
    command line. `./occ  talk:bot:install --help` gives you
    a short overview of the required arguments, but they are
    also explained in the [OCC documentation](occ.md#talkbotinstall).

---

## Signing and Verifying Requests

Messages are signed using the shared secret that is specified when installing a bot on the server.
Create a HMAC with SHA256 over the `RANDOM` header and the request body using the shared secret.
Only when the `SIGNATURE` matches the request should be accepted and handled.

**Sample PHP code:**

```php
$digest = hash_hmac('sha256', $_SERVER['HTTP_X_NEXTCLOUD_TALK_RANDOM'] . file_get_contents('php://input'), $secret);

if (!hash_equals($digest, strtolower($_SERVER['HTTP_X_NEXTCLOUD_TALK_SIGNATURE']))) {
    exit;
}
```

## Receiving chat messages

Bot receives all the chat messages following the same signature/verification method.

### Headers

| Header                            | Content type        | Description                                          |
|-----------------------------------|---------------------|------------------------------------------------------|
| `HTTP_X_NEXTCLOUD_TALK_SIGNATURE` | `[a-f0-9]{64}`      | SHA265 signature of the body                         |
| `HTTP_X_NEXTCLOUD_TALK_RANDOM`    | `[A-Za-z0-9+\]{64}` | Random string used when signing the body             |
| `HTTP_X_NEXTCLOUD_TALK_BACKEND`   | URI                 | Base URL of the Nextcloud server sending the message |

### Content

The content format follows the [Activity Streams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/).

#### Sample chat message

```json
{
    "type": "Create",
    "actor": {
        "type": "Person",
        "id": "users/ada-lovelace",
        "name": "Ada Lovelace"
    },
    "object": {
        "type": "Note",
        "id": "1567",
        "name": "message",
        "content": "{\"message\":\"hi {mention-call1} !\",\"parameters\":{\"mention-call1\":{\"type\":\"call\",\"id\":\"n3xtc10ud\",\"name\":\"world\",\"call-type\":\"group\",\"icon-url\":\"https:\\/\\/nextcloud.local\\/ocs\\/v2.php\\/apps\\/spreed\\/api\\/v1\\/room\\/n3xtc10ud\\/avatar\"}}}",
        "mediaType": "text/markdown"
    },
    "target": {
        "type": "Collection",
        "id": "n3xtc10ud",
        "name": "world"
    }
}
```

#### Explanation

| Path                      | Description                                                                                                                                                                                                                                                                 |
|---------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| actor.id                  | One of the known [attendee types](constants.md#attendee-types) followed by the `/` slash character and a unique identifier within the given type. For users it is the Nextcloud user ID, for guests and email invited guests a random hash value.                           |
| actor.name                | The display name of the attendee sending the message.                                                                                                                                                                                                                       |
| actor.talkParticipantType | *Optional:* 🆕 Added in Talk 21. If applicable the attendee's [participant type](constants.md#participant-types) is provided. However this field can not be provided in some cases, e.g. bots.                                                                              |
| object.id                 | The message ID of the given message on the origin server. It can be used to react or reply to the given message.                                                                                                                                                            |
| object.name               | For normal written messages `message`, otherwise one of the known [system message identifiers](chat.md#system-messages).                                                                                                                                                    |
| object.content            | A JSON encoded dictionary with a `message` and `parameters` key. The message can include placeholders and the [Rich Object parameters](https://github.com/nextcloud/server/blob/master/lib/public/RichObjectStrings/Definitions.php) are rendered into it in the chat view. |
| object.mediaType          | `text/markdown` when the message should be interpreted as Markdown. Otherwise `text/plain`.                                                                                                                                                                                 |
| object.inReplyTo          | *Optional:* 🆕 Added in Talk 21. If applicable the parent message which was quoted in this message                                                                                                                                                                          |
| object.inReplyTo.actor    | *Optional:* 🆕 Added in Talk 21. Same data as the `actor`                                                                                                                                                                                                                   |
| object.inReplyTo.object   | *Optional:* 🆕 Added in Talk 21. Same data as the `object` (but never contains `inReplyTo`)                                                                                                                                                                                 |
| target.id                 | The token of the conversation in which the message was posted. It can be used to react or reply to the given message.                                                                                                                                                       |
| target.name               | The name of the conversation in which the message was posted.                                                                                                                                                                                                               |

## Receiving reaction added

🆕 Added in Talk 21. Bots with the `reaction` feature receive special hooks when a reaction was added to a chat message.

### Headers

| Header                            | Content type        | Description                                          |
|-----------------------------------|---------------------|------------------------------------------------------|
| `HTTP_X_NEXTCLOUD_TALK_SIGNATURE` | `[a-f0-9]{64}`      | SHA265 signature of the body                         |
| `HTTP_X_NEXTCLOUD_TALK_RANDOM`    | `[A-Za-z0-9+\]{64}` | Random string used when signing the body             |
| `HTTP_X_NEXTCLOUD_TALK_BACKEND`   | URI                 | Base URL of the Nextcloud server sending the message |

### Content

The content format follows the [Activity Streams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/).

#### Sample reaction added message

```json
{
    "type": "Like",
    "actor": {
        "type": "Person",
        "id": "users/ada-lovelace",
        "name": "Ada Lovelace"
    },
    "object": {
        "type": "Note",
        "id": "1567",
        "name": "message",
        "content": "{\"message\":\"hi {mention-call1} !\",\"parameters\":{\"mention-call1\":{\"type\":\"call\",\"id\":\"n3xtc10ud\",\"name\":\"world\",\"call-type\":\"group\",\"icon-url\":\"https:\\/\\/nextcloud.local\\/ocs\\/v2.php\\/apps\\/spreed\\/api\\/v1\\/room\\/n3xtc10ud\\/avatar\"}}}",
        "mediaType": "text/markdown"
    },
    "target": {
        "type": "Collection",
        "id": "n3xtc10ud",
        "name": "world"
    },
    "content": "\ud83d\ude06"
}
```

#### Explanation

| Path                      | Description                                                                                                                                                                                                                                                                 |
|---------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| actor.id                  | One of the known [attendee types](constants.md#attendee-types) followed by the `/` slash character and a unique identifier within the given type. For users it is the Nextcloud user ID, for guests and email invited guests a random hash value.                           |
| actor.name                | The display name of the attendee sending the message.                                                                                                                                                                                                                       |
| actor.talkParticipantType | *Optional:* If applicable the attendee's [participant type](constants.md#participant-types) is provided. However this field can not be provided in some cases, e.g. bots.                                                                                 |
| object.id                 | The message ID of the given message on the origin server. It can be used to react or reply to the given message.                                                                                                                                                            |
| object.name               | For normal written messages `message`, otherwise one of the known [system message identifiers](chat.md#system-messages).                                                                                                                                                    |
| object.content            | A JSON encoded dictionary with a `message` and `parameters` key. The message can include placeholders and the [Rich Object parameters](https://github.com/nextcloud/server/blob/master/lib/public/RichObjectStrings/Definitions.php) are rendered into it in the chat view. |
| object.mediaType          | `text/markdown` when the message should be interpreted as Markdown. Otherwise `text/plain`.                                                                                                                                                                                 |
| target.id                 | The token of the conversation in which the message was posted. It can be used to react or reply to the given message.                                                                                                                                                       |
| target.name               | The name of the conversation in which the message was posted.                                                                                                                                                                                                               |
| content                   | The reaction emoji that was added                                                                                                                                                                                                                                           |

## Receiving reaction removed

🆕 Added in Talk 21. Bots with the `reaction` feature receive special hooks when a reaction was added to a chat message.

### Headers

| Header                            | Content type        | Description                                          |
|-----------------------------------|---------------------|------------------------------------------------------|
| `HTTP_X_NEXTCLOUD_TALK_SIGNATURE` | `[a-f0-9]{64}`      | SHA265 signature of the body                         |
| `HTTP_X_NEXTCLOUD_TALK_RANDOM`    | `[A-Za-z0-9+\]{64}` | Random string used when signing the body             |
| `HTTP_X_NEXTCLOUD_TALK_BACKEND`   | URI                 | Base URL of the Nextcloud server sending the message |

### Content

The content format follows the [Activity Streams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/).

#### Sample reaction removed message

```json
{
    "type": "Undo",
    "actor": {
        "type": "Person",
        "id": "users/ada-lovelace",
        "name": "Ada Lovelace"
    },
    "object": {
        "type": "Like",
        "actor": {
            "type": "Person",
            "id": "users/ada-lovelace",
            "name": "Ada Lovelace"
        },
        "object": {
            "type": "Note",
            "id": "1567",
            "name": "message",
            "content": "{\"message\":\"hi {mention-call1} !\",\"parameters\":{\"mention-call1\":{\"type\":\"call\",\"id\":\"n3xtc10ud\",\"name\":\"world\",\"call-type\":\"group\",\"icon-url\":\"https:\\/\\/nextcloud.local\\/ocs\\/v2.php\\/apps\\/spreed\\/api\\/v1\\/room\\/n3xtc10ud\\/avatar\"}}}",
            "mediaType": "text/markdown"
        },
        "target": {
            "type": "Collection",
            "id": "n3xtc10ud",
            "name": "world"
        },
        "content": "\ud83d\ude06"
    },
    "target": {
        "type": "Collection",
        "id": "n3xtc10ud",
        "name": "world"
    }
}
```

#### Explanation

| Path                      | Description                                                                                                                                                                                                                                       |
|---------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| actor.id                  | One of the known [attendee types](constants.md#attendee-types) followed by the `/` slash character and a unique identifier within the given type. For users it is the Nextcloud user ID, for guests and email invited guests a random hash value. |
| actor.name                | The display name of the attendee sending the message.                                                                                                                                                                                             |
| actor.talkParticipantType | *Optional:* If applicable the attendee's [participant type](constants.md#participant-types) is provided. However this field can not be provided in some cases, e.g. bots.                                                       |
| object                    | Full hook content when the reaction was added                                                                                                                                                                                                     |
| object.content            | The reaction emoji that was removed                                                                                                                                                                                                               |
| target.id                 | The token of the conversation in which the message was posted. It can be used to react or reply to the given message.                                                                                                                             |
| target.name               | The name of the conversation in which the message was posted.                                                                                                                                                                                     |

## Bot added in a chat

When the bot is added to a chat, the server sends a request to the bot, informing it of the event. The same signature/verification method is applied.

### Headers

| Header                            | Content type        | Description                                          |
|-----------------------------------|---------------------|------------------------------------------------------|
| `HTTP_X_NEXTCLOUD_TALK_SIGNATURE` | `[a-f0-9]{64}`      | SHA265 signature of the body                         |
| `HTTP_X_NEXTCLOUD_TALK_RANDOM`    | `[A-Za-z0-9+\]{64}` | Random string used when signing the body             |
| `HTTP_X_NEXTCLOUD_TALK_BACKEND`   | URI                 | Base URL of the Nextcloud server sending the message |

### Content

The content format follows the [Activity Streams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/).

#### Sample request

```json
{
    "type": "Join",
    "actor": {
        "type": "Application",
        "id": "bots/bot-a78f46c5c203141b247554e180e1aa3553d282c6",
        "name": "Bot123"
    },
    "object": {
        "type": "Collection",
        "id": "n3xtc10ud",
        "name": "world"
    }
}
```

#### Explanation

| Path        | Description                                                                                                                                              |
|-------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| actor.id    | Bot's [actor type](constants.md#actor-types-of-chat-messages) followed by the `/` slash character and a bot's unique sha1 identifier with `bot-` prefix. |
| actor.name  | The display name of the bot.                                                                                                                             |
| object.id   | The token of the conversation in which the bot was added.                                                                                                |
| object.name | The name of the conversation in which the bot was added.                                                                                                 |

## Bot removed from a chat

When the bot is removed from a chat, the server sends a request to the bot, informing it of the event. The same signature/verification method is applied.

### Headers

| Header                            | Content type        | Description                                          |
|-----------------------------------|---------------------|------------------------------------------------------|
| `HTTP_X_NEXTCLOUD_TALK_SIGNATURE` | `[a-f0-9]{64}`      | SHA265 signature of the body                         |
| `HTTP_X_NEXTCLOUD_TALK_RANDOM`    | `[A-Za-z0-9+\]{64}` | Random string used when signing the body             |
| `HTTP_X_NEXTCLOUD_TALK_BACKEND`   | URI                 | Base URL of the Nextcloud server sending the message |

### Content

The content format follows the [Activity Streams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/).

#### Sample request

```json
{
    "type": "Leave",
    "actor": {
        "type": "Application",
        "id": "bots/bot-a78f46c5c203141b247554e180e1aa3553d282c6",
        "name": "Bot123"
    },
    "object": {
        "type": "Collection",
        "id": "n3xtc10ud",
        "name": "world"
    }
}
```

#### Explanation

| Path        | Description                                                                                                                                              |
|-------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| actor.id    | Bot's [actor type](constants.md#actor-types-of-chat-messages) followed by the `/` slash character and a bot's unique sha1 identifier with `bot-` prefix. |
| actor.name  | The display name of the bot.                                                                                                                             |
| object.id   | The token of the conversation from which the bot was removed.                                                                                            |
| object.name | The name of the conversation from which the bot was removed.                                                                                             |

## Sending a chat message

Bots can also send message. On the sending process the same signature/verification method is applied.

* Required capability: `bots-v1`
* Method: `POST`
* Endpoint: `/bot/{token}/message`
* Header:

| Name                             | Description                                                     |
|----------------------------------|-----------------------------------------------------------------|
| `X-Nextcloud-Talk-Bot-Random`    | The random value used when signing the request                  |
| `X-Nextcloud-Talk-Bot-Signature` | The signature to validate the request comes from an enabled bot |
| `OCS-APIRequest`                 | Needs to be set to `true` to access the ocs/vX.php endpoint     |

* Data:

| field              | type   | Description                                                                                                                                             |
|--------------------|--------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `message`          | string | The message the user wants to say                                                                                                                       |
| `replyTo`          | int    | The message ID this message is a reply to (only allowed for messages from the same conversation and when the message type is not `system` or `command`) |
| `referenceId`      | string | A reference string to be able to identify the message again in a "get messages" request, should be a random sha256                                      |
| `silent`           | bool   | If sent silent the message will not create chat notifications even for users                                                                            |

* Response:
    - Status code:
        + `201 Created` When the message was posted successfully
        + `400 Bad Request` When the provided replyTo parameter is invalid or the message is empty
        + `401 Unauthenticated` When the bot could not be verified for the conversation
        + `404 Not Found` When the conversation could not be found
        + `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (or 1000 until Nextcloud 16.0.1, check the `spreed => config => chat => max-length` capability for the limit)
        + `429 Too Many Requests` When `401 Unauthenticated` was triggered too often

#### Sample bash script:

```bash
#!/bin/bash

NC_URL="https://nextcloud.example.tld/" #  The URL of the Nextcloud instance (e.g., "https://nextcloud.example.com")
TOKEN="12345678" # The token of the conversation
SECRET="53CR3T" # Shared secret that is specified when installing a bot
MESSAGE=$1 # Pass the message as first argument

# Generate a random header and signature
RANDOM_HEADER=$(openssl rand -hex 32)
MESSAGE_TO_SIGN="${RANDOM_HEADER}${MESSAGE}"
SIGNATURE=$(echo -n "${MESSAGE_TO_SIGN}" | openssl dgst -sha256 -hmac "${SECRET}" | cut -d' ' -f2)

# Send the message
curl -X POST "${NC_URL}/ocs/v2.php/apps/spreed/api/v1/bot/${TOKEN}/message" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "OCS-APIRequest: true" \
  -H "X-Nextcloud-Talk-Bot-Random: ${RANDOM_HEADER}" \
  -H "X-Nextcloud-Talk-Bot-Signature: ${SIGNATURE}" \
  -d '{"message":"'"${MESSAGE}"'"}'
```

## Reacting to a chat message

Bots can also react to a message. The same signature/verification method is applied.

* Required capability: `bots-v1`
* Method: `POST`
* Endpoint: `/bot/{token}/reaction/{messageId}`
* Header:

| Name                             | Description                                                     |
|----------------------------------|-----------------------------------------------------------------|
| `X-Nextcloud-Talk-Bot-Random`    | The random value used when signing the request                  |
| `X-Nextcloud-Talk-Bot-Signature` | The signature to validate the request comes from an enabled bot |
| `OCS-APIRequest`                 | Needs to be set to `true` to access the ocs/vX.php endpoint     |

* Data:

| field      | type   | Description    |
|------------|--------|----------------|
| `reaction` | string | A single emoji |

* Response:
    - Status code:
        + `201 Created` When the reaction was created successfully
        + `400 Bad Request` When the provided emoji was invalid
        + `401 Unauthenticated` When the bot could not be verified for the conversation
        + `404 Not Found` When the conversation or message could not be found
        + `429 Too Many Requests` When `401 Unauthenticated` was triggered too often

## Delete a reaction

Bots can also remove their previous reaction from a message. The same signature/verification method is applied.

* Required capability: `bots-v1`
* Method: `DELETE`
* Endpoint: `/bot/{token}/reaction/{messageId}`
* Header:

| Name                             | Description                                                     |
|----------------------------------|-----------------------------------------------------------------|
| `X-Nextcloud-Talk-Bot-Random`    | The random value used when signing the request                  |
| `X-Nextcloud-Talk-Bot-Signature` | The signature to validate the request comes from an enabled bot |
| `OCS-APIRequest`                 | Needs to be set to `true` to access the ocs/vX.php endpoint     |

* Data:

| field      | type   | Description    |
|------------|--------|----------------|
| `reaction` | string | A single emoji |

* Response:
    - Status code:
        + `200 OK` When the reaction was deleted successfully
        + `400 Bad Request` When the provided emoji was invalid
        + `401 Unauthenticated` When the bot could not be verified for the conversation
        + `404 Not Found` When the conversation or message could not be found
        + `429 Too Many Requests` When `401 Unauthenticated` was triggered too often

## Adaptive Cards

🆕 Added in Talk 19 (Nextcloud 29).

Bots can send interactive Adaptive Cards to request structured input from users. Adaptive Cards are platform-agnostic UI snippets defined in JSON that enable rich, interactive experiences.

### Sending an Adaptive Card

Bots send Adaptive Cards in chat messages using the `adaptivecard` message parameter:

```json
{
    "message": "Please fill out this form",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "unique-card-id-123",
            "bot-name": "Survey Bot",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "TextBlock",
                        "text": "Quick Feedback",
                        "size": "Large",
                        "weight": "Bolder"
                    },
                    {
                        "type": "Input.ChoiceSet",
                        "id": "rating",
                        "label": "How would you rate this meeting?",
                        "isRequired": true,
                        "choices": [
                            {"title": "⭐ Poor", "value": "1"},
                            {"title": "⭐⭐ Fair", "value": "2"},
                            {"title": "⭐⭐⭐ Good", "value": "3"},
                            {"title": "⭐⭐⭐⭐ Great", "value": "4"},
                            {"title": "⭐⭐⭐⭐⭐ Excellent", "value": "5"}
                        ]
                    },
                    {
                        "type": "Input.Text",
                        "id": "comments",
                        "label": "Additional comments (optional)",
                        "isMultiline": true,
                        "maxLength": 500
                    }
                ],
                "actions": [
                    {
                        "type": "Action.Submit",
                        "title": "Submit Feedback"
                    }
                ]
            }
        }
    }
}
```

### Receiving Card Submissions

When a user submits an Adaptive Card, all enabled bots receive a webhook with type `adaptivecard_submit`:

```json
{
    "type": "adaptivecard_submit",
    "actor": {
        "type": "Person",
        "id": "users/alice",
        "name": "Alice Smith"
    },
    "target": {
        "type": "Collection",
        "id": "token123",
        "name": "Project Discussion"
    },
    "card": {
        "id": "unique-card-id-123",
        "values": {
            "rating": "4",
            "comments": "Great discussion, very productive!"
        }
    }
}
```

The webhook uses the same HMAC-SHA256 signature verification as regular bot messages.

### Adaptive Cards Resources

- **Visual Designer**: [https://adaptivecards.io/designer/](https://adaptivecards.io/designer/)
- **Schema Explorer**: [https://adaptivecards.io/explorer/](https://adaptivecards.io/explorer/)
- **Official Documentation**: [https://adaptivecards.io/](https://adaptivecards.io/)

Adaptive Cards support various input types (text, date, time, numbers, choice sets, toggles), layout containers (columns, fact sets, tables), images, and action buttons (submit, open URL, show nested cards).

### Nextcloud-Specific Extensions

Talk supports custom actions in the `x-nextcloud` namespace for future extensions:

- `x-nextcloud.startCall` - Initiate a video/audio call
- `x-nextcloud.shareFile` - Open file picker
- `x-nextcloud.mentionUser` - Mention a participant
- `x-nextcloud.createPoll` - Launch poll creator

These are currently placeholders for Phase 3 implementation.

## Nextcloud apps as a bot

🆕 Added in Talk 21.

Instead of being webhook based, bots can also listen to the [`OCA\Talk\Events\BotInvokeEvent` PHP events](events.md#bot-invoke) and get invoked with the same content.
The event allows to `addAnswer()` and `addReaction()` which will then be posted and reacted as the bot.
The advantage is that a web request can be saved, reducing the load on the system.

When installing the bot specify `nextcloudapp://$APPID` as the bot URL, together with the `4` as a [Bot feature flag](constants.md#bot-features).

## Changelog

### Nextcloud 29 / Talk 19 - TBD 2025
- Added Adaptive Cards support for rich, interactive bot experiences
- Bots can send cards with forms, buttons, layouts, and interactive elements
- User submissions sent to bots via `adaptivecard_submit` webhook type

### Nextcloud 31 / Talk 21 - February 2025
- Added direct support for Nextcloud apps as bots. A new feature flag `events` which indicates that a bot will utilize the `OCA\Talk\Events\BotInvokeEvent` event listed in [PHP events](events.md#bot-invoke) rather than being invoked via a webhook.
- Added new feature flag `reaction` which allows to get invoked for added and removed reactions
- In hooks that are replies the new optional field `object.inReplyTo` contains the actor and content of the parent chat message

### Nextcloud 27.1 / Talk 17.1 - September 2023
- Initial version 
