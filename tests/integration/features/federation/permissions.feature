Feature: federation/permissions

  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And user "participant3" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    And user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: set participant permissions
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Given using server "LOCAL"
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Given using server "LOCAL"
    When user "participant1" sets permissions for "participant2@{$REMOTE_URL}" in room "room" to "S" with 200 (v4)
    Given using server "REMOTE"
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | permissions | attendeePermissions |
      | CS          | CS                  |
    Then user "participant3" is participant of room "LOCAL::room" (v4)
      | permissions | attendeePermissions |
      | SJAVPM      | D                  |

  Scenario: set default permissions
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Given using server "LOCAL"
    When user "participant1" sets default permissions for room "room" to "LM" with 200 (v4)
    Given using server "REMOTE"
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | defaultPermissions | attendeePermissions | permissions |
      | CLM                | D                   | CLM         |

  Scenario: set default permissions before federated user accepts invitation
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    Given using server "LOCAL"
    When user "participant1" sets default permissions for room "room" to "LM" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | defaultPermissions | attendeePermissions | permissions |
      | CLM                | D                   | CLM         |

  Scenario: set default permissions before inviting federated user
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    When user "participant1" sets default permissions for room "room" to "M" with 200 (v4)
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | defaultPermissions | attendeePermissions | permissions |
      | CM                 | D                   | CM          |

  Scenario: set default permissions before inviting federated user again
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" declines invite to room "room" of server "LOCAL" with 200 (v1)
    Given using server "LOCAL"
    When user "participant1" sets default permissions for room "room" to "M" with 200 (v4)
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | defaultPermissions | attendeePermissions | permissions |
      | CM                 | D                   | CM          |

  Scenario: set participant permissions after setting conversation permissions and then invite another federated user
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Given using server "LOCAL"
    And user "participant1" sets default permissions for room "room" to "AVP" with 200 (v4)
    And user "participant1" sets permissions for "participant2@{$REMOTE_URL}" in room "room" to "S" with 200 (v4)
    When user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name      | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | permissions |
      | CS          |
    And user "participant3" is participant of room "LOCAL::room" (v4)
      | permissions |
      | CAVP        |
