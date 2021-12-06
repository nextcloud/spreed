Feature: User remove

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Remove a user from all their rooms
    Given user "participant1" creates room "room1" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant1" creates room "room2" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room2" with 200 (v4)
    And invoking occ with "talk:user:remove --user participant2"
    And the command output contains the text "Users successfully removed from all rooms"
    Then the command was successful
    And user "participant2" is participant of the following rooms (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id    | name                     | type | participantType |
      | room1 | participant2-displayname | 2    | 1               |
      | room2 | room                     | 3    | 1               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant1" sees the following attendees in room "room2" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
