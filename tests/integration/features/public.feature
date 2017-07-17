Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms
    Then user "participant2" is participant of the following rooms
    Then user "participant3" is participant of the following rooms

  Scenario: User1 creates a public room and user2 and user3 are not part of it
    Given user "participant1" creates room "room1"
      | roomType | 3 |
    Then user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 3    | 1               | participant1 |
    And user "participant2" is not participant of room "room1"
    And user "participant3" is not participant of room "room1"

  Scenario: User1 creates a public room, invites user2 and user3 is not part of it
    Given user "participant1" creates room "room2"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room2" with 200
    Then user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room2 | 3    | 1               | participant1, participant2 |
    And user "participant2" is participant of the following rooms
      | id    | type | participantType | participants |
      | room2 | 3    | 3               | participant1, participant2 |
    And user "participant3" is not participant of room "room2"

  Scenario: User1 creates a public room and leaves it
    Given user "participant1" creates room "room3"
      | roomType | 3 |
    And user "participant1" is participant of room "room3"
    And user "participant2" is not participant of room "room3"
    And user "participant3" is not participant of room "room3"
    When user "participant1" leaves room "room3" with 200
    Then user "participant1" is not participant of room "room3"

  Scenario: User1 creates a public room and deletes themself
    Given user "participant1" creates room "room4"
      | roomType | 3 |
    And user "participant1" is participant of room "room4"
    And user "participant2" is not participant of room "room4"
    And user "participant3" is not participant of room "room4"
    When user "participant1" removes "participant1" from room "room4" with 403
    Then user "participant1" is participant of room "room4"

  Scenario: User1 creates a public room and deletes it
    Given user "participant1" creates room "room5"
      | roomType | 3 |
    And user "participant1" is participant of room "room5"
    And user "participant2" is not participant of room "room5"
    And user "participant3" is not participant of room "room5"
    When user "participant1" deletes room "room5" with 200
    Then user "participant1" is not participant of room "room5"

  # Rename
  # Invite user and promote and demote
  # Moderator add user
  # Moderator delete user
  # Moderator rename
  # Moderator public/private
