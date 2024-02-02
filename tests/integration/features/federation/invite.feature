Feature: federation/invite
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Federation is disabled
    Given the following "spreed" app config is set
      | federation_enabled | no |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds remote "participant2" to room "room" with 501 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Invite an invalid user
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds remote "invalid-user" to room "room" with 404 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Accepting an invite
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds remote "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
    And force run "OCA\Talk\BackgroundJob\RemoveEmptyRooms" background jobs
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 0     |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 3    | LOCAL        | room        |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 400 (v1)
      | error | state |
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 1     |
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | federated_users | participant2@http://localhost:8180 | federated_user_added | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
    # Remove a remote user after they joined
    When user "participant1" removes remote "participant2" from room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
    Then user "participant2" is participant of the following rooms (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | users         | participant1 | federated_user_removed  | You removed {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | federated_users | participant2@http://localhost:8180 | federated_user_added | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Declining an invite
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds remote "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 0     |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    And user "participant2" declines invite to room "room" of server "LOCAL" with 200 (v1)
    And user "participant2" declines invite to room "room" of server "LOCAL" with 404 (v1)
      | error | invitation |
    And user "participant2" has the following invitations (v1)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | federated_users | participant2@http://localhost:8180 | federated_user_removed | {federated_user} declined the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Remove remote user before they accept
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds remote "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
    And force run "OCA\Talk\BackgroundJob\RemoveEmptyRooms" background jobs
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 0     |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    When user "participant1" removes remote "participant2" from room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
    Then user "participant2" is participant of the following rooms (v4)
    Then user "participant2" has the following notifications
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | users         | participant1 | federated_user_removed  | You removed {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8180","server":"http:\/\/localhost:8180"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Authenticate as a federation user
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds remote "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 0     |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 1     |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    Then user "federation/participant2" gets room "room" with 200 (v4)
    Then user "federation/participant2" joins room "room" with 200 (v4)
    And user "federation/participant2" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room |federated_users | participant2@http://localhost:8180 | participant2@http://localhost:8180 | Message 1   | []                |               |

  Scenario: Federate conversation meta data
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds remote "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state |
      | LOCAL           | room        | 0     |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | name | type |
      | room | room | 2    |
    And user "participant1" renames room "room" to "Federated room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | name           | type |
      | room | Federated room | 2    |
