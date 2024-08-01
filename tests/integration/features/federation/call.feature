Feature: federation/call

  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    And user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: join call
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
      | flags | 3 |
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 3        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 3      |
      | users           | participant2              | 0      |
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
      | flags | 7 |
    Then using server "LOCAL"
    And user "participant1" is participant of room "room" (v4)
      | callFlag |
      | 7        |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | inCall |
      | users           | participant1               | 3      |
      | federated_users | participant2@{$REMOTE_URL} | 7      |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 7        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 3      |
      | users           | participant2              | 7      |

  Scenario: update call flags
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" joins call "LOCAL::room" with 200 (v4)
      | flags | 7 |
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 7        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 7      |
    When user "participant2" updates call flags in room "LOCAL::room" to "1" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" is participant of room "room" (v4)
      | callFlag |
      | 7        |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | inCall |
      | users           | participant1               | 0      |
      | federated_users | participant2@{$REMOTE_URL} | 1      |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 7        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 1      |

  Scenario: leave call
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
      | flags | 3 |
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" joins call "LOCAL::room" with 200 (v4)
      | flags | 7 |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 3      |
      | users           | participant2              | 7      |
    When user "participant2" leaves call "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" leaves call "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
      | callFlag |
      | 0        |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | inCall |
      | users           | participant1               | 0      |
      | federated_users | participant2@{$REMOTE_URL} | 0      |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 0        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 0      |

  Scenario: Host ends call for everyone
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
      | flags | 3 |
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" joins call "LOCAL::room" with 200 (v4)
      | flags | 7 |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 3      |
      | users           | participant2              | 7      |
    And using server "LOCAL"
    And user "participant1" ends call "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
      | callFlag |
      | 0        |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | inCall |
      | users           | participant1               | 0      |
      | federated_users | participant2@{$REMOTE_URL} | 0      |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 0        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 0      |

  Scenario: normal call notification for federated user
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | LOCAL::room | A group call has started in room |

  Scenario: normal call notification for federated user is cleared when joining
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | LOCAL::room | A group call has started in room |
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: missed call notification for federated user
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    When user "participant1" leaves call "room" with 200 (v4)
    Then using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                         |
      | spreed | call        | LOCAL::room | You missed a group call in room |

  Scenario: silent call does not trigger call notification for federated users
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                          |

  Scenario: silent call by federated user does not trigger call notification
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
      | silent | true |
    Then using server "LOCAL"
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: missed silent call by federated user does not trigger call notification
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" joins call "LOCAL::room" with 200 (v4)
      | silent | true |
    When user "participant2" leaves call "LOCAL::room" with 200 (v4)
    Then using server "LOCAL"
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
