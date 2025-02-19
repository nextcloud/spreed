Feature: federation/invite
  Background:
    Given using server "REMOTE"
    Given user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    Given using server "LOCAL"
    Given user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: Federation is disabled
    Given the following "spreed" app config is set
      | federation_enabled | no |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 501 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Invite an invalid user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "invalid-user" to room "room" with 404 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Accepting an invite
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id   | name           | type |
    Then last response has federation invites header set to "1"
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And force run "OCA\Talk\BackgroundJob\RemoveEmptyRooms" background jobs
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then last response has federation invites header set to "NULL"
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 400 (v1)
      | error | state |
    And user "participant2" declines invite to room "room" of server "LOCAL" with 400 (v1)
      | error | state |
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 1     | participant1@LOCAL | participant1-displayname |
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | federated_users | participant2@REMOTE | federated_user_added | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    # Remove a remote user after they joined
    When user "participant1" removes remote "participant2" from room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
    Then user "participant2" is participant of the following rooms (v4)
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | users         | participant1 | federated_user_removed  | You removed {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | federated_users | participant2@REMOTE | federated_user_added  | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Invite user with wrong casing
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "PARTICIPANT2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Given using server "REMOTE"
    And force run "OCA\Talk\BackgroundJob\RemoveEmptyRooms" background jobs
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       | localCloudId |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname | participant2@REMOTE |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 400 (v1)
      | error | state |
    And user "participant2" declines invite to room "room" of server "LOCAL" with 400 (v1)
      | error | state |
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 1     | participant1@LOCAL | participant1-displayname |
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | federated_users | participant2@REMOTE | federated_user_added | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    # Remove a remote user after they joined
    When user "participant1" removes remote "participant2" from room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
    Then user "participant2" is participant of the following rooms (v4)
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | users         | participant1 | federated_user_removed  | You removed {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | federated_users | participant2@REMOTE | federated_user_added | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Declining an invite
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    And user "participant2" declines invite to room "room" of server "LOCAL" with 200 (v1)
    And user "participant2" declines invite to room "room" of server "LOCAL" with 404 (v1)
      | error | invitation |
    And user "participant2" has the following invitations (v1)
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | federated_users | participant2@REMOTE | federated_user_removed | {federated_user} declined the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Remove remote user before they accept
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Given using server "REMOTE"
    And force run "OCA\Talk\BackgroundJob\RemoveEmptyRooms" background jobs
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    Given using server "LOCAL"
    When user "participant1" removes remote "participant2" from room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
    Then user "participant2" is participant of the following rooms (v4)
    Then user "participant2" has the following notifications
    Given using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | users         | participant1 | federated_user_removed  | You removed {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: User leaves after accepting
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | participantType |
      | users           | participant1               | 1               |
      | federated_users | participant2@{$REMOTE_URL} | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | participantType |
      | users           | participant1               | 1               |
      | federated_users | participant2@{$REMOTE_URL} | 3               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | federated_user_added | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2-displayname","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And force run "OCA\Talk\BackgroundJob\RemoveEmptyRooms" background jobs
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                           | message                                                                     |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname invited you to a federated conversation | @participant1-displayname invited you to join room on http://localhost:8080 |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 1     | participant1@LOCAL | participant1-displayname |
    # Remote user removes themselves after they joined
    And user "participant2" removes themselves from room "LOCAL::room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
    Then user "participant2" is participant of the following rooms (v4)
    And using server "LOCAL"
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage           | message                      | messageParameters |
      | room | federated_users | participant2@REMOTE | federated_user_removed | {federated_user} declined the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"}} |
      | room | federated_users | participant2@REMOTE | federated_user_added | {federated_user} accepted the invitation | {"actor":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"}} |
      | room | users         | participant1 | federated_user_added    | You invited {federated_user} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"federated_user":{"type":"user","id":"participant2","name":"participant2@localhost:8280","server":"http:\/\/localhost:8280","mention-id":"federated_user\/participant2@http:\/\/localhost:8280"}} |
      | room | users         | participant1 | conversation_created    | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Federate conversation meta data
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | name | type |
      | LOCAL::room | room | 2    |
    Given using server "LOCAL"
    And user "participant1" renames room "room" to "Federated room" with 200 (v4)
    Given using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id          | name           | type |
      | LOCAL::room | Federated room | 2    |

  Scenario: Allow accessing conversation and room avatars for invited users
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    When as user "participant2"
    Then the room "LOCAL::room" has an avatar with 200
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    When as user "participant2"
    Then the room "LOCAL::room" has an avatar with 200
    And user "participant2" removes themselves from room "LOCAL::room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
    When as user "participant2"
    Then the room "LOCAL::room" has an avatar with 404
