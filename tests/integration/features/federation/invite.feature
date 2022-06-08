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
    And user "participant2" has the following invitations (v1)
      | remote_server | remote_token |
      | LOCAL         | room         |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                                      |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname shared room room on http://localhost:8080 with you |
    And user "participant2" accepts invite to room "room" of server "LOCAL" (v1)
    And user "participant2" has the following invitations (v1)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
      | federated_users | participant2 | 3               |

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
    And user "participant2" has the following invitations (v1)
      | remote_server | remote_token |
      | LOCAL         | room         |
    Then user "participant2" has the following notifications
      | app    | object_type       | object_id              | subject                                                                      |
      | spreed | remote_talk_share | INVITE_ID(LOCAL::room) | @participant1-displayname shared room room on http://localhost:8080 with you |
    And user "participant2" declines invite to room "room" of server "LOCAL" (v1)
    And user "participant2" has the following invitations (v1)
    When user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType       | actorId      | participantType |
      | users           | participant1 | 1               |
