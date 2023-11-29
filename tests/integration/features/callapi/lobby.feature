Feature: callapi/lobby
  Background:
    Given user "participant1" exists
    And user "participant2" exists

  Scenario: Participant1 calls without lobby
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                                             |
      | spreed | call        | room      | A group call has started in room |

  Scenario: Participant1 calls while participant2 is blocked by the lobby
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications

  Scenario: Participant1 calls while participant2 is moderator
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                                             |
      | spreed | call        | room      | A group call has started in room |
