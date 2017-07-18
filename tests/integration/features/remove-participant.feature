Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

#
# Removing an owner
#
  Scenario: Owner removes owner
    Given user "participant1" creates room "room"
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" makes room "room" public with 200
    And user "participant3" is participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: Moderator removes owner
    Given user "participant1" creates room "room"
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" makes room "room" public with 200
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: User removes owner
    Given user "participant1" creates room "room"
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" makes room "room" public with 200
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: Stranger removes owner
    Given user "participant1" creates room "room"
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" makes room "room" public with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is participant of room "room"

#
# Removing a moderator
#
  Scenario: Owner removes moderator
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes moderator
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: User removes moderator
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: Stranger removes moderator
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is participant of room "room"

#
# Removing a user
#
  Scenario: Owner removes user
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes user
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: User removes user
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: Stranger removes user
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is participant of room "room"

#
# Removing a stranger
#
  Scenario: Owner removes stranger
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant3" is not participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 404
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes stranger
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant1" promotes "participant2" in room "room" with 200
    And user "participant3" is not participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is not participant of room "room"

  Scenario: User removes stranger
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant3" is not participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    And user "participant3" is not participant of room "room"

  Scenario: Stranger removes stranger
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant3" is not participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    And user "participant3" is not participant of room "room"
