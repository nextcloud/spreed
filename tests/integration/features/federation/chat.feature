Feature: federation/chat
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Get mention suggestions (translating local users to federated users)
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    And user "participant1" gets the following candidate mentions in room "room" for "" with 200
      | source          | id                         | label                    | mentionId                                  |
      | calls           | all                        | room                     | all                                        |
      | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | federated_user/participant2@{$REMOTE_URL}  |
      | users           | participant3               | participant3-displayname | participant3                               |
    And user "participant2" gets the following candidate mentions in room "LOCAL::room" for "" with 200
      | source          | id                       | label                    | mentionId    |
      | calls           | all                      | room                     | all          |
      | federated_users | participant1@{$BASE_URL} | participant1-displayname | participant1 |
      | federated_users | participant3@{$BASE_URL} | participant3-displayname | participant3 |

  Scenario: Get mention suggestions (translating federated users of the same server to local users)
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    And user "participant1" gets the following candidate mentions in room "room" for "" with 200
      | source          | id                         | label                    | mentionId                                 |
      | calls           | all                        | room                     | all                                       |
      | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | federated_user/participant2@{$REMOTE_URL} |
      | federated_users | participant3@{$REMOTE_URL} | participant3-displayname | federated_user/participant3@{$REMOTE_URL} |
    And user "participant2" gets the following candidate mentions in room "LOCAL::room" for "" with 200
      | source          | id                       | label                    | mentionId                                 |
      | calls           | all                      | room                     | all                                       |
      | federated_users | participant1@{$BASE_URL} | participant1-displayname | participant1                              |
      | users           | participant3             | participant3-displayname | federated_user/participant3@{$REMOTE_URL} |

  Scenario: Basic chatting including posting, getting, editing and deleting
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant2" sends reply "Message 1-1" on message "Message 1" to room "LOCAL::room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType       | actorId                    | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message 1-1 | []                | Message 1     |
      | room | users           | participant1               | participant1-displayname | Message 1   | []                |               |
    When next message request has the following parameters set
      | timeout                  | 0         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room | actorType       | actorId                  | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users           | participant2             | participant2-displayname | Message 1-1 | []                | Message 1     |
      | room | federated_users | participant1@{$BASE_URL} | participant1-displayname | Message 1   | []                |               |
    And user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    And user "participant2" edits message "Message 1-1" in room "LOCAL::room" to "Message 1-1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType       | actorId                    | actorDisplayName         | message              | messageParameters | parentMessage      | lastEditActorType | lastEditActorId            | lastEditActorDisplayName |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message 1-1 - Edit 1 | []                | Message 1 - Edit 1 | federated_users   | participant2@{$REMOTE_URL} | participant2-displayname |
      | room | users           | participant1               | participant1-displayname | Message 1 - Edit 1   | []                |                    | users             | participant1               | participant1-displayname |
    When next message request has the following parameters set
      | timeout                  | 0         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room | actorType       | actorId                  | actorDisplayName         | message              | messageParameters | parentMessage      | lastEditActorType | lastEditActorId          | lastEditActorDisplayName |
      | room | users           | participant2             | participant2-displayname | Message 1-1 - Edit 1 | []                | Message 1 - Edit 1 | users             | participant2             | participant2-displayname |
      | room | federated_users | participant1@{$BASE_URL} | participant1-displayname | Message 1 - Edit 1   | []                |                    | federated_users   | participant1@{$BASE_URL} | participant1-displayname |
    And user "participant1" deletes message "Message 1 - Edit 1" from room "room" with 200
    And user "participant2" deletes message "Message 1-1 - Edit 1" from room "LOCAL::room" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType       | actorId                    | actorDisplayName         | message                   | messageParameters | parentMessage          |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}"}} | Message deleted by you |
      | room | users           | participant1               | participant1-displayname | Message deleted by you    | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                          |                        |
    When next message request has the following parameters set
      | timeout                  | 0         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room | actorType       | actorId                  | actorDisplayName         | message                   | messageParameters | parentMessage             |
      | room | users           | participant2             | participant2-displayname | Message deleted by you    | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"}}                        | Message deleted by author |
      | room | federated_users | participant1@{$BASE_URL} | participant1-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","server":"{$BASE_URL}"}} |                           |

  Scenario: Read marker checking
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 2    | UNKNOWN_MESSAGE      | 0              | 0             | 0                   |
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sends message "Message 2" to room "room" with 201
    When user "participant2" marks room "LOCAL::room" as unread with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 2    | Message 1            | 1              | 0             | 0                   |
    When user "participant2" reads message "NULL" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 2    | Message 2            | 0              | 0             | 0                   |
    When user "participant2" reads message "Message 1" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 2    | Message 1            | 1              | 0             | 0                   |
    When user "participant2" reads message "Message 2" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 2    | Message 2            | 0              | 0             | 0                   |

  Scenario: Error handling of chatting (posting a too long message)
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    And user "participant2" sends message "413 Payload Too Large" to room "LOCAL::room" with 413

  Scenario: Mentioning a federated user triggers a notification for them
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "LOCAL::room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    And user "participant1" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "room" with 201
    And user "participant1" sends message 'Hi @all bye' to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | room/Hi @all bye         | participant1-displayname mentioned everyone in conversation room       | Hi room bye |
      | spreed | chat        | room/Hi @"federated_user/participant2@{$REMOTE_URL}" bye | participant1-displayname mentioned you in conversation room | Hi @participant2-displayname bye |
      | spreed | chat        | room/Message 1-1         | participant1-displayname replied to your message in conversation room  | Message 1-1 |

  Scenario: Mentioning a federated user as a guest also triggers a notification for them
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 3    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    When user "guest" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | room/Hi @"federated_user/participant2@{$REMOTE_URL}" bye | A guest mentioned you in conversation room | Hi @participant2-displayname bye |

  Scenario: Mentioning a federated user as a federated user that is a local user to the mentioned one also triggers a notification for them
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 3    |
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 3    | LOCAL        | room        |
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type |
      | room | 3    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    When user "participant3" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "LOCAL::room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | room/Hi @"federated_user/participant2@{$REMOTE_URL}" bye | participant3-displayname mentioned you in conversation room | Hi @participant2-displayname bye |

  Scenario: Mentioning and replying to self does not do notifications
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 3    |
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 3    | LOCAL        | room        |
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type |
      | room | 3    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    When user "participant2" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "LOCAL::room" with 201
    And user "participant2" sends message "Message 1" to room "LOCAL::room" with 201
    When user "participant2" sends reply "Message 1-1" on message "Message 1" to room "LOCAL::room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |

  Scenario: Reaction on federated chat messages
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "LOCAL::room" with 201
    And user "participant1" react with "🚀" on message "Message 1" to room "room" with 201
      | actorType       | actorId                  | actorDisplayName         | reaction |
      | users           | participant1             | participant1-displayname | 🚀       |
    And user "participant2" react with "🚀" on message "Message 1" to room "LOCAL::room" with 201
      | actorType       | actorId                  | actorDisplayName         | reaction |
      | federated_users | participant1@{$BASE_URL} | participant1-displayname | 🚀       |
      | users           | participant2             | participant2-displayname | 🚀       |
    And user "participant1" retrieve reactions "all" of message "Message 1" in room "room" with 200
      | actorType       | actorId                    | actorDisplayName         | reaction |
      | users           | participant1               | participant1-displayname | 🚀       |
      | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | 🚀       |
