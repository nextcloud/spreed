# Signaling messages

This contains the schema of signaling messages used in Nextcloud Talk.

## General

### Sender property (external signaling)

```
{
    sessionid = #STRING#,
    type = #STRING#,
    userid = #STRING,
}
```

## Offer

### External signaling

| field           | type    | Description                        |
|-----------------|---------|------------------------------------|
| `roomType`      | string  | "video" or "screen"                |
| `sid`           | string  | external signaling server >= 0.5.0 |

```
{
    "type": "message",
    "message": {
        "sender": {
            ...
        },
        "data": {
            "to": #STRING#,
            "from":  #STRING#,
            "type": "offer",
            "roomType": #STRING#,
            "payload": {
                "type": "offer",
                "sdp": #STRING#,
            },
            "sid": #STRING#,
        },
    },
}
```

### Internal signaling

| field           | type    | Description           |
|-----------------|---------|-----------------------|
| `roomType`      | string  | "video" or "screen"   |
| `nick`          | string  | optional              |

```
{
    "type": "message",
    "data": {
        "to": #STRING#,
        "sid": #STRING#,
        "roomType": #STRING#,
        "type": "offer",
        "payload": {
            "type": "offer",
            "sdp": #STRING#,
            "nick": #STRING#,
        },
        "from": #STRING#,
    },
}
```

## Candidate

### External signaling

| field      | type   | Description                        |
| ---------- | ------ | ---------------------------------- |
| `roomType` | string | "video" or "screen"                |
| `sid`      | string | external signaling server >= 0.5.0 |

```
{
    "type": "message",
    "message": {
        "sender": {
            ...
        },
        "data": {
            "to": #STRING#,
            "from":  #STRING#,
            "type": "candidate",
            "roomType": #STRING#,
            "payload": {
                "candidate": {
                    "candidate": #STRING#,
                    "sdpMid": #STRING#,
                    "sdpMLineIndex": #INTEGER#,
                },
            },
            "sid": #STRING#,
        },
    },
}
```

### Internal signaling

| field      | type   | Description                        |
| ---------- | ------ | ---------------------------------- |
| `roomType` | string | "video" or "screen"                |

```
{
    "type": "message",
    "data": {
        "to": #STRING#,
        "sid": #STRING#,
        "roomType": #STRING#,
        "type": "candidate",
        "payload": {
            "candidate": {
                "candidate": #STRING#,
                "sdpMid": #STRING#,
                "sdpMLineIndex": #INTEGER#,
            },
        },
        "from": #STRING#,
    },
}
```

## Update all participants

```
{
    "type": "event",
    "event": {
        "target": "participants",
        "type": "update",
        "update": {
            "roomid": #STRING#,
            "incall": 0,
            "all": true,
        },
    },
}
```

## Update participants

| field                    | type    | Description |
| ------------------------ | ------- | ----------- |
| `userId`                 | string  | optional    |
| `nextcloudSessionId`     | string  | optional    |
| `internal`               | boolean | optional    |
| `participantPermissions` | integer | Talk >= 13  |

 Note that `userId` in participants->update comes from the Nextcloud server, so it is `userId`; in other messages, like room->join, it comes directly from the external signaling server, so it is `userId` instead.

 ```
{
    "type": "event",
    "event": {
        "target": "participants",
        "type": "update",
        "update": {
            "roomid": #INTEGER#,
            "users": [
                {
                    "inCall": #INTEGER#,
                    "lastPing": #INTEGER#,
                    "sessionId": #STRING#,
                    "participantType": #INTEGER#,
                    "userId": #STRING#,
                    "nextcloudSessionId": #STRING#,
                    "internal": #BOOLEAN#,
                    "participantPermissions": #INTEGER#,
                },
                ...
            ],
        },
    },
}
```

 ## Users in room

| field                    | type    | Description                               |
| ------------------------ | ------- | ----------------------------------------- |
| `userId`                 | string  | Always included, although it can be empty |
| `participantPermissions` | integer | Talk >= 13                                |

Internal signaling only

```
{
    "type": "usersInRoom",
    "data": [
        {
            "inCall": #INTEGER#,
            "lastPing": #INTEGER#,
            "roomId": #INTEGER#,
            "sessionId": #STRING#,
            "userId": #STRING#,
            "participantPermissions": #INTEGER#,
        },
        ...
    ],
}
```

## Raise hand

Needs `raise-hand` capability

| field                    | type    | Description                       |
| ------------------------ | ------- | --------------------------------- |
| `state`                  | boolean | 0 - hand lowered; 1 - hand raised |

### Internal signaling

```
{
    "type": "message",
    "message": {
        "sender": {
            ...
        },
        "data": {
            "to": #STRING#,
            "sid": #STRING#,
            "roomType": "video",
            "type": "raiseHand",
            "payload": {
                "state": #BOOLEAN#,
                "timestamp": #LONG#,
            },
            "from": #STRING#,
        },
    },
}
```

### External signaling

```
{
    "type": "message",
    "data": {
        "to": #STRING#,
        "sid": #STRING#,
        "roomType": "video",
        "type": "raiseHand",
        "payload": {
            "state": #BOOLEAN#,
            "timestamp": #LONG#,
        },
        "from": #STRING#,
    },
}
```

## Unshare screen

### External signaling

```
{
    "type": "message",
    "message": {
        "sender": {
            ...
        },
        "data": {
            "roomType": "screen",
            "type": "unshareScreen",
            "from": #STRING#,
        },
    },
}
```

### Internal signaling

```
{
    "type": "message",
    "data": {
        "to": #STRING#,
        "sid": #STRING#,
        "broadcaster": #STRING#,
        "roomType": "screen",
        "type": "unshareScreen",
        "from": #STRING#,
    },
}
```

## Switchto

Needs `breakout-rooms-v1` capability and external signaling server >= 1.1.0

External signaling only

```
{
    "type": "event",
    "event": {
        "target": "room",
        "type": "switchto",
        "switchto": {
            "roomid": #STRING#,
        },
    },
}
```