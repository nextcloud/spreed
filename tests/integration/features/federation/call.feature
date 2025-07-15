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
    Then user "participant2" checks call notification for "LOCAL::room" with 201 (v4)
    And using server "LOCAL"
    And user "participant1" joins call "room" with 200 (v4)
      | flags | 3 |
    And using server "REMOTE"
    Then user "participant2" checks call notification for "LOCAL::room" with 200 (v4)
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 3        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 3      |
      | users           | participant2              | 0      |
    Then user "participant2" checks call notification for "LOCAL::room" with 200 (v4)
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
      | flags | 7 |
    Then user "participant2" checks call notification for "LOCAL::room" with 404 (v4)
    Then using server "LOCAL"
    And user "participant1" is participant of room "room" (v4)
      | callFlag |
      | 7        |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId                    | inCall |
      | users           | participant1               | 3      |
      | federated_users | participant2@{$REMOTE_URL} | 7      |
    And user "participant1" sees 2 peers in call "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 7        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 3      |
      | users           | participant2              | 7      |
    And user "participant2" sees 2 peers in call "LOCAL::room" with 200 (v4)

  Scenario: update call flags
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
    And user "participant1" sees 1 peers in call "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 7        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 1      |
    And user "participant2" sees 1 peers in call "LOCAL::room" with 200 (v4)

  Scenario: leave call
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
    And user "participant1" sees 0 peers in call "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 0        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 0      |
    And user "participant2" sees 0 peers in call "LOCAL::room" with 200 (v4)

  Scenario: Host ends call for everyone
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
    And user "participant1" sees 0 peers in call "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | callFlag |
      | 0        |
    And user "participant2" sees the following attendees in room "LOCAL::room" with 200 (v4)
      | actorType       | actorId                   | inCall |
      | federated_users | participant1@{$LOCAL_URL} | 0      |
      | users           | participant2              | 0      |
    And user "participant2" sees 0 peers in call "LOCAL::room" with 200 (v4)

  Scenario: normal call notification for federated user
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
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | LOCAL::room | A group call has started in room |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    When user "participant1" ends call "room" with 200 (v4)
    Then using server "REMOTE"
    And user "participant2" has the following notifications

  Scenario: normal call notification for federated user is cleared when joining
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
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    Then user "participant2" checks call notification for "LOCAL::room" with 201 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    And using server "REMOTE"
    Then user "participant2" checks call notification for "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    When user "participant1" leaves call "room" with 200 (v4)
    Then using server "REMOTE"
    Then user "participant2" checks call notification for "LOCAL::room" with 201 (v4)
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                         |
      | spreed | call        | LOCAL::room | You missed a group call in room |

  Scenario: silent call does not trigger call notification for federated users
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
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
      | silent | true |
    Then using server "LOCAL"
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Turning off call notifications also works
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
    And user "participant2" sets call notifications to disabled for room "LOCAL::room" (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: missed silent call by federated user does not trigger call notification
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
    And user "participant2" joins call "LOCAL::room" with 200 (v4)
      | silent | true |
    When user "participant2" leaves call "LOCAL::room" with 200 (v4)
    Then using server "LOCAL"
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Recording consent required by admin for federated conversation/users
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 1 |
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId    | inviterDisplayName       |
      | LOCAL           | room       | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 400 (v4)
      | recordingConsent | 0 |
    And user "participant1" joins call "room" with 200 (v4)
      | recordingConsent | 1 |
    And using server "REMOTE"
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" joins call "LOCAL::room" with 400 (v4)
      | recordingConsent | 0 |
    And user "participant2" joins call "LOCAL::room" with 200 (v4)
      | recordingConsent | 1 |

  Scenario: Cannot enable recording consent during a call for federated conversation/users
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 2 |
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId    | inviterDisplayName       |
      | LOCAL           | room       | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    When user "participant1" sets the recording consent to 1 for room "room" with 400 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room | 0                |

  Scenario: Enable recording consent after leaving call for federated conversation/users
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 2 |
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId    | inviterDisplayName       |
      | LOCAL           | room       | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    And user "participant1" leaves call "room" with 200 (v4)
    When user "participant1" sets the recording consent to 1 for room "room" with 200 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room | 1                |
    And user "participant1" joins call "room" with 400 (v4)
    And user "participant1" joins call "room" with 200 (v4)
      | recordingConsent | 1 |

  Scenario: Disable recording consent during a call for federated conversation/users
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 2 |
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId    | inviterDisplayName       |
      | LOCAL           | room       | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    When user "participant1" sets the recording consent to 0 for room "room" with 200 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room | 0                |
    When user "participant1" leaves call "room" with 200 (v4)

  Scenario: Resend call notification for federated user
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
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | LOCAL::room | A group call has started in room |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
    When user "participant2" leaves call "LOCAL::room" with 200 (v4)
    And user "participant2" has the following notifications
    And using server "LOCAL"
    Then user "participant1" loads attendees attendee ids in room "room" (v4)
    Then user "participant1" pings federated_user "participant2@REMOTE" to join call "room" with 200 (v4)
    Then using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | LOCAL::room | A group call has started in room |

  Scenario: Resend call notification as a federated user
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
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    When user "participant2" joins call "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | room        | A group call has started in room |
    And user "participant1" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    When user "participant1" leaves call "room" with 200 (v4)
    And user "participant1" has the following notifications
    Then using server "REMOTE"
    Then user "participant2" loads attendees attendee ids in room "LOCAL::room" (v4)
    Then user "participant2" pings federated_user "participant1@LOCAL" to join call "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "participant1" has the following notifications
      | app    | object_type | object_id   | subject                          |
      | spreed | call        | room        | A group call has started in room |
