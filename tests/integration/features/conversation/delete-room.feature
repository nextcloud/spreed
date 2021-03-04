Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is not participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    When user "participant1" deletes room "room" with 200
    Then user "participant1" is not participant of room "room" (v4)

  Scenario: Moderator deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" deletes room "room" with 200
    Then user "participant1" is not participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)

  Scenario: User deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" deletes room "room" with 403
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)

  Scenario: Stranger deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)
    When user "participant2" deletes room "room" with 404
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)
