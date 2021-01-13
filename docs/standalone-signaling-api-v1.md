# External signaling API

This document gives a rough overview on the API version 1.0 of the Spreed
signaling server. Clients can use the signaling server to send realtime
messages between different users / sessions.

The API describes the various messages that can be sent by a client or the
server to join rooms or distribute events between clients.

Depending on the server implementation, clients can use WebSockets (preferred)
or COMET (i.e. long-polling) requests to communicate with the signaling server.

For WebSockets, only the API described in this document is necessary. For COMET,
an extension to this API is required to identify a (virtual) connection between
multiple requests. The payload for COMET is the messages as described below.

See [Internal signaling API](internal-signaling.md) for the API of the regular PHP backend.


## Request

    {
      "id": "unique-request-id",
      "type": "the-request-type",
      "the-request-type": {
        ...object defining the request...
      }
    }

Example:

    {
      "id": "123-abc",
      "type": "samplemessage",
      "samplemessage": {
        "foo": "bar",
        "baz": 1234
      }
    }


## Response

    {
      "id": "unique-request-id-from-request-if-present",
      "type": "the-response-type",
      "the-response-type": {
        ...object defining the response...
      }
    }

Example:

    {
      "id": "123-abc",
      "type": "sampleresponse",
      "sampleresponse": {
        "hello": "world!"
      }
    }


## Errors

The server can send error messages as a response to any request the client has
sent.

Message format:

    {
      "id": "unique-request-id-from-request-if-present",
      "type": "error",
      "error": {
        "code": "the-internal-message-id",
        "message": "human-readable-error-message",
        "details": {
          ...optional additional details...
        }
      }
    }


## Backend requests

For some messages, the signaling server has to perform a request to the
Nextcloud backend (e.g. to validate the user authentication). The backend
must be able to verify the request to make sure it is coming from a valid
signaling server.

Also the Nextcloud backend can send requests to the signaling server to notify
about events related to a room or user (e.g. a user is no longer invited to
a room). Here the signaling server must be able to verify the request to check
if it is coming from a valid Nextcloud instance.

Therefore all backend requests, either from the signaling server or vice versa
must contain two additional HTTP headers:

- `Spreed-Signaling-Random`: Random string of at least 32 bytes.
- `Spreed-Signaling-Checksum`: SHA256-HMAC of the random string and the request
  body, calculated with a shared secret. The shared secret is configured on
  both sides, so the checksum can be verified.
- `Spreed-Signaling-Backend`: Base URL of the Nextcloud server performing the
  request.

### Example

- Request body: `{"type":"auth","auth":{"version":"1.0","params":{"hello":"world"}}}`
- Random: `afb6b872ab03e3376b31bf0af601067222ff7990335ca02d327071b73c0119c6`
- Shared secret: `MySecretValue`
- Calculated checksum: `3c4a69ff328299803ac2879614b707c807b4758cf19450755c60656cac46e3bc`


## Establish connection

This must be the first request by a newly connected client and is used to
authenticate the connection. No other messages can be sent without a successful
`hello` handshake.

Message format (Client -> Server):

    {
      "id": "unique-request-id",
      "type": "hello",
      "hello": {
        "version": "the-protocol-version-must-be-1.0",
        "auth": {
          "url": "the-url-to-the-auth-backend",
          "params": {
            ...object containing auth params...
          }
        }
      }
    }

Message format (Server -> Client):

    {
      "id": "unique-request-id-from-request",
      "type": "hello",
      "hello": {
        "sessionid": "the-unique-session-id",
        "resumeid": "the-unique-resume-id",
        "userid": "the-user-id-for-known-users",
        "version": "the-protocol-version-must-be-1.0",
        "server": {
          "features": ["optional", "list, "of", "feature", "ids"],
          ...additional information about the server...
        }
      }
    }


### Backend validation

The server validates the connection request against the passed auth backend
(needs to make sure the passed url / hostname is in a whitelist). It performs
a POST request and passes the provided `params` as JSON payload in the body
of the request.

Message format (Server -> Auth backend):

    {
      "type": "auth",
      "auth": {
        "version": "the-protocol-version-must-be-1.0",
        "params": {
          ...object containing auth params from hello request...
        }
      }
    }

If the auth params are valid, the backend returns information about the user
that is connecting (as JSON response).

Message format (Auth backend -> Server):

    {
      "type": "auth",
      "auth": {
        "version": "the-protocol-version-must-be-1.0",
        "userid": "the-user-id-for-known-users",
        "user": {
          ...additional data of the user...
        }
      }
    }

Anonymous connections that are not mapped to a user in Nextcloud will have an
empty or omitted `userid` field in the response. If the connection can not be
authorized, the backend returns an error and the hello request will be rejected.


### Error codes

- `unsupported-version`: The requested version is not supported.
- `auth-failed`: The session could not be authenticated.
- `too-many-sessions`: Too many sessions exist for this user id.
- `invalid_backend`: The requested backend URL is not supported.
- `invalid_client_type`: The [client type](#client-types) is not supported.
- `invalid_token`: The passed token is invalid (can happen for
  [client type `internal`](#client-type-internal)).


### Client types

In order to support clients with different functionality on the server, an
optional `type` can be specified in the `auth` struct when connecting to the
server. If no `type` is present, the default value `client` will be used and
a regular "user" client is created internally.

Message format (Client -> Server):

    {
      "id": "unique-request-id",
      "type": "hello",
      "hello": {
        "version": "the-protocol-version-must-be-1.0",
        "auth": {
          "type": "the-client-type",
          ...other attributes depending on the client type...
          "params": {
            ...object containing auth params...
          }
        }
      }
    }

The key `params` is required for all client types, other keys depend on the
`type` value.


#### Client type `client` (default)

For the client type `client` (which is the default if no `type` is given), the
URL to the backend server for this client must be given as described above.

This client type must be supported by all server implementations of the
signaling protocol.


#### Client type `internal`

"Internal" clients are used for connections from internal services where the
connection doesn't map to a user (or session) in Nextcloud.

These clients can skip some internal validations, e.g. they can join any room,
even if they have not been invited (which is not possible as the client doesn't
map to a user). This client type is not required to be supported by server
implementations of the signaling protocol, but some additional services might
not work without "internal" clients.

To authenticate the connection, the `params` struct must contain keys `random`
(containing any random string of at least 32 bytes) and `token` containing the
SHA-256 HMAC of `random` with a secret that is shared between the signaling
server and the service connecting to it.


## Resuming sessions

If a connection was interrupted for a client, the server may decide to keep the
session alive for a short time, so the client can reconnect and resume the
session.

In this case, no complete `hello` handshake is required and a client can use
a shorter `hello` request. On success, the session will resume as if no
interruption happened, i.e. the client will stay in his room and will get all
messages from the time the interruption happened.

Message format (Client -> Server):

    {
      "id": "unique-request-id",
      "type": "hello",
      "hello": {
        "version": "the-protocol-version-must-be-1.0",
        "resumeid": "the-resume-id-from-the-original-hello-response"
      }
    }

Message format (Server -> Client):

    {
      "id": "unique-request-id-from-request",
      "type": "hello",
      "hello": {
        "sessionid": "the-unique-session-id",
        "version": "the-protocol-version-must-be-1.0"
      }
    }

If the session is no longer valid (e.g. because the resume was too late), the
server will return an error and a normal `hello` handshake has to be performed.


### Error codes

- `no_such_session`: The session id is no longer valid.


## Releasing sessions

By default, the signaling server tries to maintain the session so clients can
resume it in case of intermittent connection problems.

To support cases where a client wants to close the connection and release all
session data, he can send a `bye` message so the server knows he doesn't need
to keep data for resuming.

Message format (Client -> Server):

    {
      "id": "unique-request-id",
      "type": "bye",
      "bye": {}
    }

Message format (Server -> Client):

    {
      "id": "unique-request-id-from-request",
      "type": "bye",
      "bye": {}
    }

After the `bye` has been confirmed, the session can no longer be used.


## Join room

After joining the room through the PHP backend, the room must be changed on the
signaling server, too.

Message format (Client -> Server):

    {
      "id": "unique-request-id",
      "type": "room",
      "room": {
        "roomid": "the-room-id",
        "sessionid": "the-nextcloud-session-id"
      }
    }

- The client can ask about joining a room using this request.
- The session id received from the PHP backend must be passed as `sessionid`.
- The `roomid` can be empty to leave the room.
- A session can only be connected to one room, i.e. joining a room will leave
  the room currently in.

Message format (Server -> Client):

    {
      "id": "unique-request-id-from-request",
      "type": "room",
      "room": {
        "roomid": "the-room-id",
        "properties": {
          ...additional room properties...
        }
      }
    }

- Sent to confirm a request from the client.
- The `roomid` will be empty if the client is no longer in a room.
- Can be sent without a request if the server moves a client to a room / out of
  the current room or the properties of a room change.


### Backend validation

Rooms are managed by the Nextcloud backend, so the signaling server has to
verify that a room exists and a user is allowed to join it.

Message format (Server -> Room backend):

    {
      "type": "room",
      "room": {
        "version": "the-protocol-version-must-be-1.0",
        "roomid": "the-room-id",
        "userid": "the-user-id-for-known-users",
        "sessionid": "the-nextcloud-session-id",
        "action": "join-or-leave"
      }
    }

The `userid` is empty or omitted for anonymous sessions that don't belong to a
user in Nextcloud.

Message format (Room backend -> Server):

    {
      "type": "room",
      "room": {
        "version": "the-protocol-version-must-be-1.0",
        "roomid": "the-room-id",
        "properties": {
          ...additional room properties...
        }
      }
    }

If the room does not exist or can not be joined by the given (or anonymous)
user, the backend returns an error and the room request will be rejected.


### Error codes

- `no_such_room`: The requested room does not exist or the user is not invited
  to the room.


## Leave room

To leave a room, a [join room](#join-room) message must be sent with an empty
`roomid` parameter.


## Room events

When users join or leave a room, the server generates events that are sent to
all sessions in that room. Such events are also sent to users joining a room
as initial list of users in the room. Multiple user joins/leaves can be batched
into one event to reduce the message overhead.

Message format (Server -> Client, user(s) joined):

    {
      "type": "event"
      "event": {
        "target": "room",
        "type": "join",
        "join": [
          ...list of session objects that joined the room...
        ]
      }
    }

Room event session object:

    {
      "sessionid": "the-unique-session-id",
      "userid": "the-user-id-for-known-users",
      "user": {
        ...additional data of the user as received from the auth backend...
      }
    }

Message format (Server -> Client, user(s) left):

    {
      "type": "event"
      "event": {
        "target": "room",
        "type": "leave",
        "leave": [
          ...list of session ids that left the room...
        ]
      }
    }

Message format (Server -> Client, user(s) changed):

    {
      "type": "event"
      "event": {
        "target": "room",
        "type": "change",
        "change": [
          ...list of sessions that have changed...
        ]
      }
    }


## Room list events

When users are invited to rooms or are disinvited from them, they get notified
so they can update the list of available rooms.

Message format (Server -> Client, invited to room):

    {
      "type": "event"
      "event": {
        "target": "roomlist",
        "type": "invite",
        "invite": [
          "roomid": "the-room-id",
          "properties": [
            ...additional room properties...
          ]
        ]
      }
    }

Message format (Server -> Client, disinvited from room):

    {
      "type": "event"
      "event": {
        "target": "roomlist",
        "type": "disinvite",
        "disinvite": [
          "roomid": "the-room-id"
        ]
      }
    }


Message format (Server -> Client, room updated):

    {
      "type": "event"
      "event": {
        "target": "roomlist",
        "type": "update",
        "update": [
          "roomid": "the-room-id",
          "properties": [
            ...additional room properties...
          ]
        ]
      }
    }


## Participants list events

When the list of participants or flags of a participant in a room changes, an
event is triggered by the server so clients can update their UI accordingly or
trigger actions like starting calls with other peers.

Message format (Server -> Client, participants change):

    {
      "type": "event"
      "event": {
        "target": "participants",
        "type": "update",
        "update": [
          "roomid": "the-room-id",
          "users": [
            ...list of changed participant objects...
          ]
        ]
      }
    }

If a participant has the `inCall` flag set, he has joined the call of the room
and a WebRTC peerconnection should be established if the local client is also
in the call. In that case the participant information will contain properties
for both the signaling session id (`sessionId`) and the Nextcloud session id
(`nextcloudSessionId`).


## Room messages

The server can notify clients about events that happened in a room. Currently
such messages are only sent out when chat messages are posted to notify clients
they should load the new messages.

Message format (Server -> Client, chat messages available):

    {
      "type": "event"
      "event": {
        "target": "room",
        "type": "message",
        "message": {
          "roomid": "the-room-id",
          "data": {
            "type": "chat",
            "chat": {
              "refresh": true
            }
          }
        }
      }
    }


## Sending messages between clients

Messages between clients are sent realtime and not stored by the server, i.e.
they are only delivered if the recipient is currently connected. This also
applies to rooms, where only sessions currently in the room will receive the
messages, but not if they join at a later time.

Use this for establishing WebRTC connections between peers, i.e. sending offers,
answers and candidates.

Message format (Client -> Server, to other sessions):

    {
      "id": "unique-request-id",
      "type": "message",
      "message": {
        "recipient": {
          "type": "session",
          "sessionid": "the-session-id-to-send-to"
        },
        "data": {
          ...object containing the data to send...
        }
      }
    }

Message format (Client -> Server, to all sessions of a user):

    {
      "id": "unique-request-id",
      "type": "message",
      "message": {
        "recipient": {
          "type": "user",
          "userid": "the-user-id-to-send-to"
        },
        "data": {
          ...object containing the data to send...
        }
      }
    }

Message format (Client -> Server, to all sessions in the same room):

    {
      "id": "unique-request-id",
      "type": "message",
      "message": {
        "recipient": {
          "type": "room"
        },
        "data": {
          ...object containing the data to send...
        }
      }
    }

Message format (Server -> Client, receive message)

    {
      "type": "message",
      "message": {
        "sender": {
          "type": "the-type-when-sending",
          "sessionid": "the-session-id-of-the-sender",
          "userid": "the-user-id-of-the-sender"
        },
        "data": {
          ...object containing the data of the message...
        }
      }
    }

- The `userid` is omitted if a message was sent by an anonymous user.


# Internal signaling server API

The signaling server provides an internal API that can be called from Nextcloud
to trigger events from the server side.


## Rooms API

The base URL for the rooms API is `/api/vi/room/<roomid>`, all requests must be
sent as `POST` request with proper checksum headers as described above.


### New users invited to room

This can be used to notify users that they are now invited to a room.

Message format (Backend -> Server)

    {
      "type": "invite"
      "invite" {
        "userids": [
          ...list of user ids that are now invited to the room...
        ],
        "alluserids": [
          ...list of all user ids that invited to the room...
        ],
        "properties": [
          ...additional room properties...
        ]
      }
    }


### Users no longer invited to room

This can be used to notify users that they are no longer invited to a room.

Message format (Backend -> Server)

    {
      "type": "disinvite"
      "disinvite" {
        "userids": [
          ...list of user ids that are no longer invited to the room...
        ],
        "alluserids": [
          ...list of all user ids that still invited to the room...
        ]
      }
    }


### Room updated

This can be used to notify about changes to a room. The room properties are the
same as described in section "Join room" above.

Message format (Backend -> Server)

    {
      "type": "update"
      "update" {
        "userids": [
          ...list of user ids that are invited to the room...
        ],
        "properties": [
          ...additional room properties...
        ]
      }
    }


### Room deleted

This can be used to notify about a deleted room. All sessions currently
connected to the room will leave the room.

Message format (Backend -> Server)

    {
      "type": "delete"
      "delete" {
        "userids": [
          ...list of user ids that were invited to the room...
        ]
      }
    }


### Participants changed

This can be used to notify about changed participants.

Message format (Backend -> Server)

    {
      "type": "participants"
      "participants" {
        "changed": [
          ...list of users that were changed...
        ],
        "users": [
          ...list of users in the room...
        ]
      }
    }


### In call state of participants changed

This can be used to notify about participants that changed their `inCall` flag.

Message format (Backend -> Server)

    {
      "type": "incall"
      "incall" {
        "incall": new-incall-state,
        "changed": [
          ...list of users that were changed...
        ],
        "users": [
          ...list of users in the room...
        ]
      }
    }


### Send an arbitrary room message

This can be used to send arbitrary messages to participants in a room. It is
currently used to notify about new chat messages.

Message format (Backend -> Server)

    {
      "type": "message"
      "message" {
        "data": {
          ...arbitrary object to sent to clients...
        }
      }
    }
