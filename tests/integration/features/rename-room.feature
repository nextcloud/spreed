Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner renames
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    When user "participant1" renames room "room" to "new name" with 200
    Then user "participant1" is participant of room "room"

  Scenario: Moderator renames
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of room "room"
    And user "participant1" promotes "participant2" in room "room" with 200
    When user "participant2" renames room "room" to "new name" with 200

  Scenario: User renames
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of room "room"
    When user "participant2" renames room "room" to "new name" with 403

  Scenario: Stranger renames
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
    When user "participant2" renames room "room" to "new name" with 404


  # Make private
