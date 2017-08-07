Feature: one-to-one
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms
    Then user "participant2" is participant of the following rooms
    Then user "participant3" is participant of the following rooms

  Scenario: User1 invites user2 to a one2one room and user3 is not part of it
    When user "participant1" creates room "room1"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 1    | 1               | participant1, participant2 |
    And user "participant2" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 1    | 1               | participant1, participant2 |
    And user "participant3" is participant of the following rooms
    And user "participant1" is participant of room "room1"
    And user "participant2" is participant of room "room1"
    And user "participant3" is not participant of room "room1"

  Scenario: User1 invites user2 to a one2one room and leaves it
    Given user "participant1" creates room "room2"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room2"
    And user "participant2" is participant of room "room2"
    When user "participant1" leaves room "room2" with 200
    Then user "participant1" is not participant of room "room2"
    And user "participant2" is not participant of room "room2"

  Scenario: User1 invites user2 to a one2one room and deletes it
    Given user "participant1" creates room "room3"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room3"
    And user "participant2" is participant of room "room3"
    When user "participant1" deletes room "room3" with 200
    Then user "participant1" is not participant of room "room3"
    And user "participant2" is not participant of room "room3"

  Scenario: User1 invites user2 to a one2one room and removes user2
    Given user "participant1" creates room "room4"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room4"
    And user "participant2" is participant of room "room4"
    When user "participant1" removes "participant2" from room "room4" with 200
    Then user "participant1" is not participant of room "room4"
    And user "participant2" is not participant of room "room4"

  Scenario: User1 invites user2 to a one2one room and renames it
    Given user "participant1" creates room "room5"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room5"
    And user "participant2" is participant of room "room5"
    When user "participant1" renames room "room5" to "new name" with 405

  Scenario: User1 invites user2 to a one2one room and make it public
    Given user "participant1" creates room "room6"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room6"
    And user "participant2" is participant of room "room6"
    When user "participant1" makes room "room6" public with 200
    Then user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room6 | 3    | 1               | participant1, participant2 |

  Scenario: User1 invites user2 to a one2one room and invites user3
    Given user "participant1" creates room "room7"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room7"
    And user "participant2" is participant of room "room7"
    And user "participant3" is not participant of room "room7"
    When user "participant1" adds "participant3" to room "room7" with 200
    Then user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room7 | 2    | 1               | participant1, participant2, participant3 |
    And user "participant3" is participant of room "room7"
    Then user "participant3" is participant of the following rooms
      | id    | type | participantType | participants |
      | room7 | 2    | 3               | participant1, participant2, participant3 |

  Scenario: User1 invites user2 to a one2one room and promote user2 to moderator
    Given user "participant1" creates room "room8"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room8"
    And user "participant2" is participant of room "room8"
    When user "participant1" promotes "participant2" in room "room8" with 412

  Scenario: User1 invites user2 to a one2one room and demote user2 to moderator
    Given user "participant1" creates room "room9"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room9"
    And user "participant2" is participant of room "room9"
    When user "participant1" demotes "participant2" in room "room9" with 412

  Scenario: User1 invites user2 to a one2one room and promote non-invited user
    Given user "participant1" creates room "room10"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room10"
    And user "participant3" is not participant of room "room10"
    When user "participant1" promotes "participant3" in room "room10" with 404

  Scenario: User1 invites user2 to a one2one room and demote non-invited user
    Given user "participant1" creates room "room11"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room11"
    And user "participant3" is not participant of room "room11"
    When user "participant1" demotes "participant3" in room "room11" with 404
