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
      | source          | id                         | label                       |
      | calls           | all                        | room                        |
      | federated_users | participant2@{$REMOTE_URL} | participant2@localhost:8180 |
      | users           | participant3               | participant3-displayname    |
    And user "participant2" gets the following candidate mentions in room "LOCAL::room" for "" with 200
      | source          | id                       | label                    |
      | calls           | all                      | room                     |
      | federated_users | participant1@{$BASE_URL} | participant1-displayname |
      | federated_users | participant3@{$BASE_URL} | participant3-displayname |

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
      | source          | id                         | label                       |
      | calls           | all                        | room                        |
      | federated_users | participant2@{$REMOTE_URL} | participant2@localhost:8180 |
      | federated_users | participant3@{$REMOTE_URL} | participant3@localhost:8180 |
    And user "participant2" gets the following candidate mentions in room "LOCAL::room" for "" with 200
      | source          | id                       | label                    |
      | calls           | all                      | room                     |
      | federated_users | participant1@{$BASE_URL} | participant1-displayname |
      | users           | participant3             | participant3-displayname |

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
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} | Message deleted by you |
      | room | users           | participant1               | participant1-displayname | Message deleted by you    | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |                        |
    When next message request has the following parameters set
      | timeout                  | 0         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room | actorType       | actorId                  | actorDisplayName         | message                   | messageParameters | parentMessage             |
      | room | users           | participant2             | participant2-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}}                | Message deleted by author |
      | room | federated_users | participant1@{$BASE_URL} | participant1-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |                           |

  Scenario: Error handling of chatting (posting a too long message)
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
    And user "participant2" sends message "413 Payload Too Large" to room "LOCAL::room" with 413
