# Talk occ commands

## talk:bot:install

Install a new bot on the server

### Usage

* `talk:bot:install [--output [OUTPUT]] [--no-setup] [-f|--feature FEATURE] [--] <name> <secret> <url> [<description>]`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `name` | The name under which the messages will be posted (min. 1 char, max. 64 chars) | yes | no | *Required* |
| `secret` | Secret used to validate API calls (min. 40 chars, max. 128 chars) | yes | no | *Required* |
| `url` | Webhook endpoint to post messages to (max. 4000 chars) | yes | no | *Required* |
| `description` | Optional description shown in the admin settings (max. 4000 chars) | no | no | `NULL` |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |
| `--no-setup` | Prevent moderators from setting up the bot in a conversation | no | no | no | `false` |
| `--feature\|-f` | Specify the list of features for the bot - webhook: The bot receives posted chat messages as webhooks - response: The bot can post messages and reactions as a response - event: The bot reads posted messages from local events - reaction: The bot is notified about adding and removing of reactions - none: When all features should be disabled for the bot | yes | yes | yes | *Required* |

## talk:bot:list

List all installed bots of the server or a conversation

### Usage

* `talk:bot:list [--output [OUTPUT]] [--] [<token>]`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Conversation token to limit the bot list for | no | no | `NULL` |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:bot:remove

Remove a bot from a conversation

### Usage

* `talk:bot:remove [--output [OUTPUT]] [--] <bot-id> [<token>...]`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `bot-id` | The ID of the bot to remove in a conversation | yes | no | *Required* |
| `token` | Conversation tokens to remove bot up for | no | yes | `array ()` |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:bot:state

Change the state or feature list for a bot

### Usage

* `talk:bot:state [--output [OUTPUT]] [-f|--feature FEATURE] [--] <bot-id> <state>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `bot-id` | Bot ID to change the state for | yes | no | *Required* |
| `state` | New state for the bot (0 = disabled, 1 = enabled, 2 = no setup via GUI) | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |
| `--feature\|-f` | Specify the list of features for the bot - webhook: The bot receives posted chat messages as webhooks - response: The bot can post messages and reactions as a response - event: The bot reads posted messages from local events - reaction: The bot is notified about adding and removing of reactions - none: When all features should be disabled for the bot | yes | yes | yes | *Required* |

## talk:bot:setup

Add a bot to a conversation

### Usage

* `talk:bot:setup [--output [OUTPUT]] [--] <bot-id> [<token>...]`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `bot-id` | The ID of the bot to set up in a conversation | yes | no | *Required* |
| `token` | Conversation tokens to set the bot up for | no | yes | `array ()` |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:bot:uninstall

Uninstall a bot from the server

### Usage

* `talk:bot:uninstall [--output [OUTPUT]] [--url URL] [--] [<id>]`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `id` | The ID of the bot | no | no | `NULL` |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |
| `--url` | The URL of the bot (required when no ID is given, ignored otherwise) | yes | yes | no | *Required* |

## talk:monitor:calls

Prints a list with conversations that have an active call as well as their participant count

### Usage

* `talk:monitor:calls [--output [OUTPUT]]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:active-calls

Allows you to check if calls are currently in process

### Usage

* `talk:active-calls [--output [OUTPUT]]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:monitor:room

Prints the number of attendees, active sessions and participant in the call.

### Usage

* `talk:monitor:room [--output [OUTPUT]] [--separator SEPARATOR] [--] <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to monitor | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |
| `--separator` | Separator for the CSV list when output=csv is used | yes | yes | no | *Required* |

## talk:phone-number:add

Add a mapping entry to map a phone number to an account

### Usage

* `talk:phone-number:add [-f|--force] [--] <phone> <account>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `phone` | Phone number that will be called | yes | no | *Required* |
| `account` | Account to be added to the conversation | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--force\|-f` | Force the number to the given account even when it is assigned already | no | no | no | `false` |

## talk:phone-number:find

Find a phone number or the phone number of an account

### Usage

* `talk:phone-number:find [--phone PHONE] [--account ACCOUNT]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--phone` | Phone number to search for | yes | yes | no | *Required* |
| `--account` | Account to get number(s) for | yes | yes | no | *Required* |

## talk:phone-number:import

Import a CSV list (format: "number","account") for SIP dial-in

### Usage

* `talk:phone-number:import [--reset] [-f|--force]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--reset` | Delete all phone numbers before importing | no | no | no | `false` |
| `--force\|-f` | Force the numbers to the given account even when they are assigned already | no | no | no | `false` |

## talk:phone-number:remove-account

Remove mapping entries by account

### Usage

* `talk:phone-number:remove-account <account>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `account` | Account to remove all mapping entries for | yes | no | *Required* |

## talk:phone-number:remove

Remove a mapping entry by phone number

### Usage

* `talk:phone-number:remove <phone>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `phone` | Phone number to remove the mapping entry for | yes | no | *Required* |

## talk:recording:consent

List all matching consent that were given to be audio and video recorded during a call (requires administrator or moderator configuration)

### Usage

* `talk:recording:consent [--output [OUTPUT]] [--token TOKEN] [--actor-type ACTOR-TYPE] [--actor-id ACTOR-ID]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |
| `--token` | Limit to the given conversation | yes | yes | no | *Required* |
| `--actor-type` | Limit to the given actor (only valid when --actor-id is also provided) | yes | yes | no | *Required* |
| `--actor-id` | Limit to the given actor (only valid when --actor-type is also provided) | yes | yes | no | *Required* |

## talk:room:add

Adds users to a room

### Usage

* `talk:room:add [--user USER] [--group GROUP] [--] <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to add users to | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--user` | Invites the given users to the room | yes | yes | yes | *Required* |
| `--group` | Invites all members of the given groups to the room | yes | yes | yes | *Required* |

## talk:room:create

Create a new room

### Usage

* `talk:room:create [--description DESCRIPTION] [--user USER] [--group GROUP] [--public] [--readonly] [--listable LISTABLE] [--password PASSWORD] [--owner OWNER] [--moderator MODERATOR] [--message-expiration MESSAGE-EXPIRATION] [--] <name>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `name` | The name of the room to create | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--description` | The description of the room to create | yes | yes | no | *Required* |
| `--user` | Invites the given users to the room to create | yes | yes | yes | *Required* |
| `--group` | Invites all members of the given group to the room to create | yes | yes | yes | *Required* |
| `--public` | Creates the room as public room if set | no | no | no | `false` |
| `--readonly` | Creates the room with read-only access only if set | no | no | no | `false` |
| `--listable` | Creates the room with the given listable scope | yes | yes | no | *Required* |
| `--password` | Protects the room to create with the given password | yes | yes | no | *Required* |
| `--owner` | Sets the given user as owner of the room to create | yes | yes | no | *Required* |
| `--moderator` | Promotes the given users to moderators | yes | yes | yes | *Required* |
| `--message-expiration` | Seconds to expire a message after sent. If zero will disable the expire message duration. | yes | yes | no | *Required* |

## talk:room:delete

Deletes a room

### Usage

* `talk:room:delete <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to delete | yes | no | *Required* |

## talk:room:demote

Demotes participants of a room to regular users

### Usage

* `talk:room:demote <token> <participant>...`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room in which users should be demoted | yes | no | *Required* |
| `participant` | Demotes the given participants of the room to regular users | yes | yes | *Required* |

## talk:room:promote

Promotes participants of a room to moderators

### Usage

* `talk:room:promote <token> <participant>...`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room in which users should be promoted | yes | no | *Required* |
| `participant` | Promotes the given participants of the room to moderators | yes | yes | *Required* |

## talk:room:remove

Remove users from a room

### Usage

* `talk:room:remove <token> <participant>...`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to remove users from | yes | no | *Required* |
| `participant` | Removes the given participants from the room | yes | yes | *Required* |

## talk:room:update

Updates a room

### Usage

* `talk:room:update [--name NAME] [--description DESCRIPTION] [--public PUBLIC] [--readonly READONLY] [--listable LISTABLE] [--password PASSWORD] [--owner OWNER] [--message-expiration MESSAGE-EXPIRATION] [--] <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | The token of the room to update | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--name` | Sets a new name for the room | yes | yes | no | *Required* |
| `--description` | Sets a new description for the room | yes | yes | no | *Required* |
| `--public` | Modifies the room to be a public room (value 1) or private room (value 0) | yes | yes | no | *Required* |
| `--readonly` | Modifies the room to be read-only (value 1) or read-write (value 0) | yes | yes | no | *Required* |
| `--listable` | Modifies the room's listable scope | yes | yes | no | *Required* |
| `--password` | Sets a new password for the room; pass an empty value to remove password protection | yes | yes | no | *Required* |
| `--owner` | Sets the given user as owner of the room; pass an empty value to remove the owner | yes | yes | no | *Required* |
| `--message-expiration` | Seconds to expire a message after sent. If zero will disable the expire message duration. | yes | yes | no | *Required* |

## talk:signaling:add

Add an external signaling server.

### Usage

* `talk:signaling:add [--verify] [--] <server> <secret>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | A server string, ex. wss://signaling.example.org | yes | no | *Required* |
| `secret` | A shared secret string. | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--verify` | Validate SSL certificate if set. | no | no | no | `false` |

## talk:signaling:delete

Remove an existing signaling server.

### Usage

* `talk:signaling:delete <server>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | An external signaling server string, ex. wss://signaling.example.org | yes | no | *Required* |

## talk:signaling:list

List external signaling servers.

### Usage

* `talk:signaling:list [--output [OUTPUT]]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:stun:add

Add a new STUN server.

### Usage

* `talk:stun:add <server>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | A domain name and port number separated by the colons, ex. stun.nextcloud.com:443 | yes | no | *Required* |

## talk:stun:delete

Remove an existing STUN server.

### Usage

* `talk:stun:delete <server>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | A domain name and port number separated by the colons, ex. stun.nextcloud.com:443 | yes | no | *Required* |

## talk:stun:list

List STUN servers.

### Usage

* `talk:stun:list [--output [OUTPUT]]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:turn:add

Add a TURN server.

### Usage

* `talk:turn:add [--secret SECRET] [--generate-secret] [--] <schemes> <server> <protocols>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `schemes` | Schemes, can be turn or turns or turn,turns. | yes | no | *Required* |
| `server` | A domain name, ex. turn.nextcloud.com | yes | no | *Required* |
| `protocols` | Protocols, can be udp or tcp or udp,tcp. | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--secret` | A shard secret string | yes | yes | no | *Required* |
| `--generate-secret` | Generate secret if set. | no | no | no | `false` |

## talk:turn:delete

Remove an existing TURN server.

### Usage

* `talk:turn:delete <schemes> <server> <protocols>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `schemes` | Schemes, can be turn or turns or turn,turns | yes | no | *Required* |
| `server` | A domain name, ex. turn.nextcloud.com | yes | no | *Required* |
| `protocols` | Protocols, can be udp or tcp or udp,tcp | yes | no | *Required* |

## talk:turn:list

List TURN servers.

### Usage

* `talk:turn:list [--output [OUTPUT]]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | `'plain'` |

## talk:user:remove

Remove a user from all their rooms

### Usage

* `talk:user:remove [--user USER] [--private-only]`

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--user` | Remove the given users from all rooms | yes | yes | yes | *Required* |
| `--private-only` | Only remove the user from private rooms, retaining membership in public and open conversations as well as one-to-ones | no | no | no | `false` |

## talk:user:transfer-ownership

Adds the destination-user with the same participant type to all (not one-to-one) conversations of source-user

### Usage

* `talk:user:transfer-ownership [--include-non-moderator] [--remove-source-user] [--] <source-user> <destination-user>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `source-user` | Owner of conversations which shall be moved | yes | no | *Required* |
| `destination-user` | User who will be the new owner of the conversations | yes | no | *Required* |

| Options | Description | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|---|
| `--include-non-moderator` | Also include conversations where the source-user is a normal user | no | no | no | `false` |
| `--remove-source-user` | Remove the source-user from the conversations | no | no | no | `false` |

