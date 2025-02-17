Feature: federation/join-leave

  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    And user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: join a group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    When using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    Then using server "LOCAL"
    And user "participant1" is participant of room "room" (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | participantType | sessionIds                            |
      | users           | participant1               | 1               | [SESSION,]                            |
      | federated_users | participant2@{$REMOTE_URL} | 3               | [SESSION#participant2@{$REMOTE_URL},] |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | participantType | sessionIds                            |
      | federated_users | participant1@{$LOCAL_URL} | 1               | [SESSION,]                            |
      | users           | participant2              | 3               | [SESSION#participant2@{$REMOTE_URL},] |

  Scenario: join a group room again without leaving it first
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    When user "participant2" joins room "LOCAL::room" with 200 (v4)
    Then using server "LOCAL"
    And user "participant1" is participant of room "room" (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | participantType | sessionIds                            |
      | users           | participant1               | 1               | [SESSION,]                            |
      | federated_users | participant2@{$REMOTE_URL} | 3               | [SESSION#participant2@{$REMOTE_URL},SESSION#participant2@{$REMOTE_URL},] |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | participantType | sessionIds                            |
      | federated_users | participant1@{$LOCAL_URL} | 1               | [SESSION,]                            |
      | users           | participant2              | 3               | [SESSION#participant2@{$REMOTE_URL},SESSION#participant2@{$REMOTE_URL},] |

  Scenario: leave a group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | participantType | sessionIds                            |
      | users           | participant1               | 1               | [SESSION,]                            |
      | federated_users | participant2@{$REMOTE_URL} | 3               | [SESSION#participant2@{$REMOTE_URL},] |
    And using server "REMOTE"
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                    | participantType | sessionIds                            |
      | federated_users | participant1@{$LOCAL_URL}  | 1               | [SESSION,]                            |
      | users           | participant2               | 3               | [SESSION#participant2@{$REMOTE_URL},] |
    When user "participant2" leaves room "LOCAL::room" with 200 (v4)
    Then using server "LOCAL"
    And user "participant1" is participant of room "room" (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | participantType | sessionIds |
      | users           | participant1               | 1               | [SESSION,] |
      | federated_users | participant2@{$REMOTE_URL} | 3               | []         |
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | participantType | sessionIds |
      | federated_users | participant1@{$LOCAL_URL} | 1               | [SESSION,] |
      | users           | participant2              | 3               | []         |
