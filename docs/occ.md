# Talk occ commands

 * [talk:command:add](#talkcommandadd)
 * [talk:command:add-samples](#talkcommandadd-samples)
 * [talk:command:delete](#talkcommanddelete)
 * [talk:command:list](#talkcommandlist)
 * [talk:command:update](#talkcommandupdate)
 * [talk:developer:update-docs](#talkdeveloperupdate-docs)
 * [talk:monitor:calls](#talkmonitorcalls)
 * [talk:active-calls](#talkactive-calls)
 * [talk:monitor:room](#talkmonitorroom)
 * [talk:room:add](#talkroomadd)
 * [talk:room:create](#talkroomcreate)
 * [talk:room:delete](#talkroomdelete)
 * [talk:room:demote](#talkroomdemote)
 * [talk:room:promote](#talkroompromote)
 * [talk:room:remove](#talkroomremove)
 * [talk:room:update](#talkroomupdate)
 * [talk:signaling:add](#talksignalingadd)
 * [talk:signaling:delete](#talksignalingdelete)
 * [talk:signaling:list](#talksignalinglist)
 * [talk:stun:add](#talkstunadd)
 * [talk:stun:delete](#talkstundelete)
 * [talk:stun:list](#talkstunlist)
 * [talk:turn:add](#talkturnadd)
 * [talk:turn:delete](#talkturndelete)
 * [talk:turn:list](#talkturnlist)
 * [talk:user:remove](#talkuserremove)
 * [talk:user:transfer-ownership](#talkusertransfer-ownership)
## talk:command:add

Add a new command

### Usage

* `talk:command:add <cmd> <name> <script> <response> <enabled>`

### Arguments

#### `cmd`

The command as used in the chat "/help" => "help"

* Is required: yes
* Is array: no
* Default: `NULL`

#### `name`

Name of the user posting the response

* Is required: yes
* Is array: no
* Default: `NULL`

#### `script`

Script to execute (Must be using absolute paths only)

* Is required: yes
* Is array: no
* Default: `NULL`

#### `response`

Who should see the response: 0 - No one, 1 - User, 2 - All

* Is required: yes
* Is array: no
* Default: `NULL`

#### `enabled`

Who can use this command: 0 - Disabled, 1 - Moderators, 2 - Users, 3 - Guests

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:command:add-samples

Adds some sample commands: /wiki, …

### Usage

* `talk:command:add-samples`


## talk:command:delete

Remove an existing command

### Usage

* `talk:command:delete <command-id>`

### Arguments

#### `command-id`

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:command:list

List all available commands

### Usage

* `talk:command:list [--output [OUTPUT]] [--] [<app>]`

### Arguments

#### `app`

Only list the commands of a specific app, "custom" to list all custom commands

* Is required: no
* Is array: no
* Default: `NULL`

### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

## talk:command:update

Add a new command

### Usage

* `talk:command:update <command-id> <cmd> <name> <script> <response> <enabled>`

### Arguments

#### `command-id`

* Is required: yes
* Is array: no
* Default: `NULL`

#### `cmd`

The command as used in the chat "/help" => "help"

* Is required: yes
* Is array: no
* Default: `NULL`

#### `name`

Name of the user posting the response

* Is required: yes
* Is array: no
* Default: `NULL`

#### `script`

Script to execute (Must be using absolute paths only)

* Is required: yes
* Is array: no
* Default: `NULL`

#### `response`

Who should see the response: 0 - No one, 1 - User, 2 - All

* Is required: yes
* Is array: no
* Default: `NULL`

#### `enabled`

Who can use this command: 0 - Disabled, 1 - Moderators, 2 - Users, 3 - Guests

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:developer:update-docs

Update documentation of commands

### Usage

* `talk:developer:update-docs`


## talk:monitor:calls

Prints a list with conversations that have an active call as well as their participant count

### Usage

* `talk:monitor:calls [--output [OUTPUT]]`
### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

## talk:active-calls

Allows you to check if calls are currently in process

### Usage

* `talk:active-calls [--output [OUTPUT]]`
### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

## talk:monitor:room

Prints a list with conversations that have an active call as well as their participant count

### Usage

* `talk:monitor:room [--output [OUTPUT]] [--separator SEPARATOR] [--] <token>`

### Arguments

#### `token`

Token of the room to monitor

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

#### `--separator`

Separator for the CSV list when output=csv is used

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `','`

## talk:room:add

Adds users to a room

### Usage

* `talk:room:add [--user USER] [--group GROUP] [--] <token>`

### Arguments

#### `token`

Token of the room to add users to

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--user`

Invites the given users to the room

* Accept value: yes
* Is value required: yes
* Is multiple: yes
* Is negatable: no
* Default: `array ()`

#### `--group`

Invites all members of the given groups to the room

* Accept value: yes
* Is value required: yes
* Is multiple: yes
* Is negatable: no
* Default: `array ()`

## talk:room:create

Create a new room

### Usage

* `talk:room:create [--description DESCRIPTION] [--user USER] [--group GROUP] [--public] [--readonly] [--listable LISTABLE] [--password PASSWORD] [--owner OWNER] [--moderator MODERATOR] [--message-expiration MESSAGE-EXPIRATION] [--] <name>`

### Arguments

#### `name`

The name of the room to create

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--description`

The description of the room to create

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--user`

Invites the given users to the room to create

* Accept value: yes
* Is value required: yes
* Is multiple: yes
* Is negatable: no
* Default: `array ()`

#### `--group`

Invites all members of the given group to the room to create

* Accept value: yes
* Is value required: yes
* Is multiple: yes
* Is negatable: no
* Default: `array ()`

#### `--public`

Creates the room as public room if set

* Accept value: no
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `false`

#### `--readonly`

Creates the room with read-only access only if set

* Accept value: no
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `false`

#### `--listable`

Creates the room with the given listable scope

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--password`

Protects the room to create with the given password

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--owner`

Sets the given user as owner of the room to create

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--moderator`

Promotes the given users to moderators

* Accept value: yes
* Is value required: yes
* Is multiple: yes
* Is negatable: no
* Default: `array ()`

#### `--message-expiration`

Seconds to expire a message after sent. If zero will disable the expire message duration.

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

## talk:room:delete

Deletes a room

### Usage

* `talk:room:delete <token>`

### Arguments

#### `token`

Token of the room to delete

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:room:demote

Demotes participants of a room to regular users

### Usage

* `talk:room:demote <token> <participant>...`

### Arguments

#### `token`

Token of the room in which users should be demoted

* Is required: yes
* Is array: no
* Default: `NULL`

#### `participant`

Demotes the given participants of the room to regular users

* Is required: yes
* Is array: yes
* Default: `array ()`

## talk:room:promote

Promotes participants of a room to moderators

### Usage

* `talk:room:promote <token> <participant>...`

### Arguments

#### `token`

Token of the room in which users should be promoted

* Is required: yes
* Is array: no
* Default: `NULL`

#### `participant`

Promotes the given participants of the room to moderators

* Is required: yes
* Is array: yes
* Default: `array ()`

## talk:room:remove

Remove users from a room

### Usage

* `talk:room:remove <token> <participant>...`

### Arguments

#### `token`

Token of the room to remove users from

* Is required: yes
* Is array: no
* Default: `NULL`

#### `participant`

Removes the given participants from the room

* Is required: yes
* Is array: yes
* Default: `array ()`

## talk:room:update

Updates a room

### Usage

* `talk:room:update [--name NAME] [--description DESCRIPTION] [--public PUBLIC] [--readonly READONLY] [--listable LISTABLE] [--password PASSWORD] [--owner OWNER] [--message-expiration MESSAGE-EXPIRATION] [--] <token>`

### Arguments

#### `token`

The token of the room to update

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--name`

Sets a new name for the room

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--description`

Sets a new description for the room

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--public`

Modifies the room to be a public room (value 1) or private room (value 0)

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--readonly`

Modifies the room to be read-only (value 1) or read-write (value 0)

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--listable`

Modifies the room's listable scope

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--password`

Sets a new password for the room; pass an empty value to remove password protection

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--owner`

Sets the given user as owner of the room; pass an empty value to remove the owner

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--message-expiration`

Seconds to expire a message after sent. If zero will disable the expire message duration.

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

## talk:signaling:add

Add an external signaling server.

### Usage

* `talk:signaling:add [--verify] [--] <server> <secret>`

### Arguments

#### `server`

A server string, ex. wss://signaling.example.org

* Is required: yes
* Is array: no
* Default: `NULL`

#### `secret`

A shared secret string.

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--verify`

Validate SSL certificate if set.

* Accept value: no
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `false`

## talk:signaling:delete

Remove an existing signaling server.

### Usage

* `talk:signaling:delete <server>`

### Arguments

#### `server`

An external signaling server string, ex. wss://signaling.example.org

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:signaling:list

List external signaling servers.

### Usage

* `talk:signaling:list [--output [OUTPUT]]`
### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

## talk:stun:add

Add a new STUN server.

### Usage

* `talk:stun:add <server>`

### Arguments

#### `server`

A domain name and port number separated by the colons, ex. stun.nextcloud.com:443

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:stun:delete

Remove an existing STUN server.

### Usage

* `talk:stun:delete <server>`

### Arguments

#### `server`

A domain name and port number separated by the colons, ex. stun.nextcloud.com:443

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:stun:list

List STUN servers.

### Usage

* `talk:stun:list [--output [OUTPUT]]`
### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

## talk:turn:add

Add a TURN server.

### Usage

* `talk:turn:add [--secret SECRET] [--generate-secret] [--] <schemes> <server> <protocols>`

### Arguments

#### `schemes`

Schemes, can be turn or turns or turn,turns.

* Is required: yes
* Is array: no
* Default: `NULL`

#### `server`

A domain name, ex. turn.nextcloud.com

* Is required: yes
* Is array: no
* Default: `NULL`

#### `protocols`

Protocols, can be udp or tcp or udp,tcp.

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--secret`

A shard secret string

* Accept value: yes
* Is value required: yes
* Is multiple: no
* Is negatable: no
* Default: `NULL`

#### `--generate-secret`

Generate secret if set.

* Accept value: no
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `false`

## talk:turn:delete

Remove an existing TURN server.

### Usage

* `talk:turn:delete <schemes> <server> <protocols>`

### Arguments

#### `schemes`

Schemes, can be turn or turns or turn,turns

* Is required: yes
* Is array: no
* Default: `NULL`

#### `server`

A domain name, ex. turn.nextcloud.com

* Is required: yes
* Is array: no
* Default: `NULL`

#### `protocols`

Protocols, can be udp or tcp or udp,tcp

* Is required: yes
* Is array: no
* Default: `NULL`

## talk:turn:list

List TURN servers.

### Usage

* `talk:turn:list [--output [OUTPUT]]`
### Options

#### `--output`

Output format (plain, json or json_pretty, default is plain)

* Accept value: yes
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `'plain'`

## talk:user:remove

Remove a user from all their rooms

### Usage

* `talk:user:remove [--user USER]`
### Options

#### `--user`

Remove the given users from all rooms

* Accept value: yes
* Is value required: yes
* Is multiple: yes
* Is negatable: no
* Default: `array ()`

## talk:user:transfer-ownership

Adds the destination-user with the same participant type to all (not one-to-one) conversations of source-user

### Usage

* `talk:user:transfer-ownership [--include-non-moderator] [--remove-source-user] [--] <source-user> <destination-user>`

### Arguments

#### `source-user`

Owner of conversations which shall be moved

* Is required: yes
* Is array: no
* Default: `NULL`

#### `destination-user`

User who will be the new owner of the conversations

* Is required: yes
* Is array: no
* Default: `NULL`

### Options

#### `--include-non-moderator`

Also include conversations where the source-user is a normal user

* Accept value: no
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `false`

#### `--remove-source-user`

Remove the source-user from the conversations

* Accept value: no
* Is value required: no
* Is multiple: no
* Is negatable: no
* Default: `false`
