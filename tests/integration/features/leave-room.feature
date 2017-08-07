Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner leaves
    Given user "participant1" creates room "room"
      | roomType | 3 |
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    And user "participant2" is not participant of room "room"
    And user "participant3" is not participant of room "room"
    When user "participant1" leaves room "room" with 200
    Then user "participant1" is not participant of room "room"

  Scenario: Moderator leaves
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1, participant2 |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" leaves room "room" with 200
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: User leaves
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1, participant2 |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" leaves room "room" with 200
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: Stranger leaves
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
    When user "participant2" leaves room "room" with 404
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
