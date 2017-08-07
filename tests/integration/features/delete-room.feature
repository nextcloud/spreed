Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner deletes
    Given user "participant1" creates room "room"
      | roomType | 3 |
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    And user "participant2" is not participant of room "room"
    And user "participant3" is not participant of room "room"
    When user "participant1" deletes room "room" with 200
    Then user "participant1" is not participant of room "room"

  Scenario: Moderator deletes
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1, participant2 |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" deletes room "room" with 200
    Then user "participant1" is not participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: User deletes
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1, participant2 |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" deletes room "room" with 403
    Then user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"

  Scenario: Stranger deletes
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
    When user "participant2" deletes room "room" with 404
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: User1 creates a public room and deletes themself
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
    And user "participant3" is not participant of room "room"
    When user "participant1" removes "participant1" from room "room" with 403
    Then user "participant1" is participant of room "room"
