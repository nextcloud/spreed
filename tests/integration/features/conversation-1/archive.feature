Feature: conversation-1/archive
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Archiving and unarchiving
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | isArchived |
      | group room      | room         | 0          |
      | one-to-one room | participant2 | 0          |
    And user "participant1" archives room "one-to-one room" with 200 (v4)
    And user "participant1" archives room "group room" with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | isArchived |
      | group room      | room         | 1          |
      | one-to-one room | participant2 | 1          |
    And user "participant1" unarchives room "one-to-one room" with 200 (v4)
    And user "participant1" unarchives room "group room" with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | isArchived |
      | group room      | room         | 0          |
      | one-to-one room | participant2 | 0          |
