Feature: federation/lobby

  Background:
    Given user "participant1" exists
    And user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: set lobby state
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room name |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room name | 2    | LOCAL        | room        |
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | lobbyState |
      | 1          |

  Scenario: reset lobby state
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    When user "participant1" sets lobby state for room "room" to "non moderators" for 5 seconds with 200 (v4)
    And user "participant2" is participant of room "LOCAL::room" (v4)
      | lobbyState |
      | 1          |
    And wait for 10 second
    Then user "participant2" is participant of room "LOCAL::room" (v4)
      | lobbyState |
      | 0          |
