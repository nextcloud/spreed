Feature: User remove

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Remove a user from all their rooms
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And invoking occ with "talk:user:remove --user participant2"
    And the command output contains the text "Users successfully removed from all rooms"
    Then the command was successful
    And user "participant2" is participant of the following rooms (v4)
    And user "participant1" is participant of room "room" (v4)
