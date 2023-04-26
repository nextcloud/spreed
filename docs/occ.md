# Talk occ commands

## talk:command:add

Add a new command

### Usage

* `talk:command:add [--output [OUTPUT]] [--] <cmd> <name> <script> <response> <enabled>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `cmd` | The command as used in the chat "/help" => "help" | yes | no | `NULL` |
| `name` | Name of the user posting the response | yes | no | `NULL` |
| `script` | Script to execute (Must be using absolute paths only) | yes | no | `NULL` |
| `response` | Who should see the response: 0 - No one, 1 - User, 2 - All | yes | no | `NULL` |
| `enabled` | Who can use this command: 0 - Disabled, 1 - Moderators, 2 - Users, 3 - Guests | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:command:add-samples

Adds some sample commands: /wiki, …

### Usage

* `talk:command:add-samples`

## talk:command:delete

Remove an existing command

### Usage

* `talk:command:delete <command-id>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `command-id` |  | yes | no | `NULL` |

## talk:command:list

List all available commands

### Usage

* `talk:command:list [--output [OUTPUT]] [--] [<app>]`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `app` | Only list the commands of a specific app, "custom" to list all custom commands | no | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:command:update

Add a new command

### Usage

* `talk:command:update [--output [OUTPUT]] [--] <command-id> <cmd> <name> <script> <response> <enabled>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `command-id` |  | yes | no | `NULL` |
| `cmd` | The command as used in the chat "/help" => "help" | yes | no | `NULL` |
| `name` | Name of the user posting the response | yes | no | `NULL` |
| `script` | Script to execute (Must be using absolute paths only) | yes | no | `NULL` |
| `response` | Who should see the response: 0 - No one, 1 - User, 2 - All | yes | no | `NULL` |
| `enabled` | Who can use this command: 0 - Disabled, 1 - Moderators, 2 - Users, 3 - Guests | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:monitor:calls

Prints a list with conversations that have an active call as well as their participant count

### Usage

* `talk:monitor:calls [--output [OUTPUT]]`

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:active-calls

Allows you to check if calls are currently in process

### Usage

* `talk:active-calls [--output [OUTPUT]]`

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:monitor:room

Prints the number of attendees, active sessions and participant in the call.

### Usage

* `talk:monitor:room [--output [OUTPUT]] [--separator SEPARATOR] [--] <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to monitor | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |
| `--separator` | Separator for the CSV list when output=csv is used | yes | yes | no | ','` |

## talk:room:add

Adds users to a room

### Usage

* `talk:room:add [--user USER] [--group GROUP] [--] <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to add users to | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--user` | Invites the given users to the room | yes | yes | yes | array ()` |
| `--group` | Invites all members of the given groups to the room | yes | yes | yes | array ()` |

## talk:room:create

Create a new room

### Usage

* `talk:room:create [--description DESCRIPTION] [--user USER] [--group GROUP] [--public] [--readonly] [--listable LISTABLE] [--password PASSWORD] [--owner OWNER] [--moderator MODERATOR] [--message-expiration MESSAGE-EXPIRATION] [--] <name>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `name` | The name of the room to create | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--description` | The description of the room to create | yes | yes | no | NULL` |
| `--user` | Invites the given users to the room to create | yes | yes | yes | array ()` |
| `--group` | Invites all members of the given group to the room to create | yes | yes | yes | array ()` |
| `--public` | Creates the room as public room if set | no | no | no | false` |
| `--readonly` | Creates the room with read-only access only if set | no | no | no | false` |
| `--listable` | Creates the room with the given listable scope | yes | yes | no | NULL` |
| `--password` | Protects the room to create with the given password | yes | yes | no | NULL` |
| `--owner` | Sets the given user as owner of the room to create | yes | yes | no | NULL` |
| `--moderator` | Promotes the given users to moderators | yes | yes | yes | array ()` |
| `--message-expiration` | Seconds to expire a message after sent. If zero will disable the expire message duration. | yes | yes | no | NULL` |

## talk:room:delete

Deletes a room

### Usage

* `talk:room:delete <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to delete | yes | no | `NULL` |

## talk:room:demote

Demotes participants of a room to regular users

### Usage

* `talk:room:demote <token> <participant>...`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room in which users should be demoted | yes | no | `NULL` |
| `participant` | Demotes the given participants of the room to regular users | yes | yes | `array ()` |

## talk:room:promote

Promotes participants of a room to moderators

### Usage

* `talk:room:promote <token> <participant>...`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room in which users should be promoted | yes | no | `NULL` |
| `participant` | Promotes the given participants of the room to moderators | yes | yes | `array ()` |

## talk:room:remove

Remove users from a room

### Usage

* `talk:room:remove <token> <participant>...`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | Token of the room to remove users from | yes | no | `NULL` |
| `participant` | Removes the given participants from the room | yes | yes | `array ()` |

## talk:room:update

Updates a room

### Usage

* `talk:room:update [--name NAME] [--description DESCRIPTION] [--public PUBLIC] [--readonly READONLY] [--listable LISTABLE] [--password PASSWORD] [--owner OWNER] [--message-expiration MESSAGE-EXPIRATION] [--] <token>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `token` | The token of the room to update | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--name` | Sets a new name for the room | yes | yes | no | NULL` |
| `--description` | Sets a new description for the room | yes | yes | no | NULL` |
| `--public` | Modifies the room to be a public room (value 1) or private room (value 0) | yes | yes | no | NULL` |
| `--readonly` | Modifies the room to be read-only (value 1) or read-write (value 0) | yes | yes | no | NULL` |
| `--listable` | Modifies the room's listable scope | yes | yes | no | NULL` |
| `--password` | Sets a new password for the room; pass an empty value to remove password protection | yes | yes | no | NULL` |
| `--owner` | Sets the given user as owner of the room; pass an empty value to remove the owner | yes | yes | no | NULL` |
| `--message-expiration` | Seconds to expire a message after sent. If zero will disable the expire message duration. | yes | yes | no | NULL` |

## talk:signaling:add

Add an external signaling server.

### Usage

* `talk:signaling:add [--verify] [--] <server> <secret>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | A server string, ex. wss://signaling.example.org | yes | no | `NULL` |
| `secret` | A shared secret string. | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--verify` | Validate SSL certificate if set. | no | no | no | false` |

## talk:signaling:delete

Remove an existing signaling server.

### Usage

* `talk:signaling:delete <server>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | An external signaling server string, ex. wss://signaling.example.org | yes | no | `NULL` |

## talk:signaling:list

List external signaling servers.

### Usage

* `talk:signaling:list [--output [OUTPUT]]`

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:stun:add

Add a new STUN server.

### Usage

* `talk:stun:add <server>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | A domain name and port number separated by the colons, ex. stun.nextcloud.com:443 | yes | no | `NULL` |

## talk:stun:delete

Remove an existing STUN server.

### Usage

* `talk:stun:delete <server>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `server` | A domain name and port number separated by the colons, ex. stun.nextcloud.com:443 | yes | no | `NULL` |

## talk:stun:list

List STUN servers.

### Usage

* `talk:stun:list [--output [OUTPUT]]`

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:turn:add

Add a TURN server.

### Usage

* `talk:turn:add [--secret SECRET] [--generate-secret] [--] <schemes> <server> <protocols>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `schemes` | Schemes, can be turn or turns or turn,turns. | yes | no | `NULL` |
| `server` | A domain name, ex. turn.nextcloud.com | yes | no | `NULL` |
| `protocols` | Protocols, can be udp or tcp or udp,tcp. | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--secret` | A shard secret string | yes | yes | no | NULL` |
| `--generate-secret` | Generate secret if set. | no | no | no | false` |

## talk:turn:delete

Remove an existing TURN server.

### Usage

* `talk:turn:delete <schemes> <server> <protocols>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `schemes` | Schemes, can be turn or turns or turn,turns | yes | no | `NULL` |
| `server` | A domain name, ex. turn.nextcloud.com | yes | no | `NULL` |
| `protocols` | Protocols, can be udp or tcp or udp,tcp | yes | no | `NULL` |

## talk:turn:list

List TURN servers.

### Usage

* `talk:turn:list [--output [OUTPUT]]`

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--output` | Output format (plain, json or json_pretty, default is plain) | yes | no | no | 'plain'` |

## talk:user:remove

Remove a user from all their rooms

### Usage

* `talk:user:remove [--user USER]`

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--user` | Remove the given users from all rooms | yes | yes | yes | array ()` |

## talk:user:transfer-ownership

Adds the destination-user with the same participant type to all (not one-to-one) conversations of source-user

### Usage

* `talk:user:transfer-ownership [--include-non-moderator] [--remove-source-user] [--] <source-user> <destination-user>`

| Arguments | Description | Is required | Is array | Default |
|---|---|---|---|---|
| `source-user` | Owner of conversations which shall be moved | yes | no | `NULL` |
| `destination-user` | User who will be the new owner of the conversations | yes | no | `NULL` |

| Options | Accept value | Is value required | Is multiple | Default |
|---|---|---|---|---|
| `--include-non-moderator` | Also include conversations where the source-user is a normal user | no | no | no | false` |
| `--remove-source-user` | Remove the source-user from the conversations | no | no | no | false` |

