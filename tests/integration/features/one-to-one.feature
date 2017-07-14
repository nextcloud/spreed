Feature: one-to-one
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms
    Then user "participant2" is participant of the following rooms

  Scenario: User1 invites user2 to a one2one room and leaves it
    When user "participant1" creates room "room1"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 1    | 1               | participant1, participant2 |
    And user "participant2" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 1    | 1               | participant1, participant2 |
    And user "participant1" leaves room "room1"
    Then user "participant1" is participant of the following rooms
    And user "participant2" is participant of the following rooms

  Scenario: User1 invites user2 to a one2one room and deletes it
    When user "participant1" creates room "room2"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room2 | 1    | 1               | participant1, participant2 |
    And user "participant2" is participant of the following rooms
      | id    | type | participantType | participants |
      | room2 | 1    | 1               | participant1, participant2 |
    And user "participant1" deletes room "room2"
    Then user "participant1" is participant of the following rooms
    And user "participant2" is participant of the following rooms

  Scenario: User1 invites user2 to a one2one room and removes user2
    When user "participant1" creates room "room3"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room3 | 1    | 1               | participant1, participant2 |
    And user "participant2" is participant of the following rooms
      | id    | type | participantType | participants |
      | room3 | 1    | 1               | participant1, participant2 |
    And user "participant1" removes "participant2" from room "room3"
    Then user "participant1" is participant of the following rooms
    And user "participant2" is participant of the following rooms
