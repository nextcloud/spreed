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
    And user "participant1" is participant of the following unordered rooms (v4)
      | id    | name                     | type | participantType |
      | room1 | participant2-displayname | 2    | 1               |
      | room2 | room                     | 3    | 1               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant1" sees the following attendees in room "room2" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Remove a user after there was a missed call
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
    Then user "participant1" leaves call "room" with 200 (v4)
    Then user "participant1" leaves room "room" with 200 (v4)
    And invoking occ with "talk:user:remove --user participant2"
    And the command output contains the text "Users successfully removed from all rooms"
    Then the command was successful
    And user "participant2" is participant of the following rooms (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id   | name                     | type | participantType |
      | room | participant2-displayname | 2    | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | users         | participant1 | call_tried           | You tried to call {user}     | {"user":{"type":"highlight","id":"deleted_user","name":"participant2-displayname"}} |
      | room | users         | participant1 | call_left            | You left the call            | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
      | room | users         | participant1 | call_started         | You started a call           | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
