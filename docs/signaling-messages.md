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

External signaling only

| field    | type    | Description        |
| -------- | ------- | ------------------ |
| `roomid` | string  | conversation token |

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

External signaling only

| field                    | type    | Description        |
| ------------------------ | ------- | ------------------ |
| `roomid`                 | string  | conversation token |
| `userId`                 | string  | optional           |
| `nextcloudSessionId`     | string  | optional           |
| `internal`               | boolean | optional           |
| `participantPermissions` | integer | Talk >= 13         |

Note that `userId` in participants->update comes from the Nextcloud server, so it is `userId`; in other messages, like room->join, it comes directly from the external signaling server, so it is `userid` instead.

 ```
{
    "type": "event",
    "event": {
        "target": "participants",
        "type": "update",
        "update": {
            "roomid": #STRING#,
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

Internal signaling only

| field                    | type    | Description                               |
| ------------------------ | ------- | ----------------------------------------- |
| `roomId`                 | integer | Internal room id (not conversation token) |
| `userId`                 | string  | Always included, although it can be empty |
| `participantPermissions` | integer | Talk >= 13                                |

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

### External signaling

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

### Internal signaling

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

External signaling only

Needs `breakout-rooms-v1` capability and external signaling server >= 1.1.0

| field    | type    | Description        |
| -------- | ------- | ------------------ |
| `roomid` | string  | conversation token |

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

## Call reactions

### External signaling

| field      | type   | Description                               |
| ---------- | ------ | ----------------------------------------- |
| `roomType` | string | "video" or "screen"                       |
| `reaction` | string | Single emoji which is shown as a reaction |
| `sid`      | string | external signaling server >= 0.5.0        |

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
            "roomType": #STRING#,
            "type": "reaction",
            "payload" :{
                "reaction": #STRING#
            }
        }
    }
}
```

### Internal signaling

| field      | type   | Description                               |
| ---------- | ------ | ----------------------------------------- |
| `roomType` | string | "video" or "screen"                       |
| `reaction` | string | Single emoji which is shown as a reaction |

```
{
    "to": #STRING#,
    "sid": #STRING#,
    "roomType": #STRING#,
    "type": "reaction",
    "payload": {
        "reaction": #STRING#
    },
    "from": #STRING#
}
```
