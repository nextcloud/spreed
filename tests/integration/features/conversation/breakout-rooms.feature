Feature: conversation/breakout-rooms
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: Teacher creates breakout rooms
    Given user "participant1" creates room "class room" (v4)
      | roomType | 3 |
      | roomName | class room |
    And user "participant1" adds user "participant2" to room "class room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "class room" with 200 (v4)
    And user "participant1" adds user "participant4" to room "class room" with 200 (v4)
    And user "participant1" sees the following attendees in room "class room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
      | users      | participant3 | 3               |
      | users      | participant4 | 3               |
    When user "participant1" creates 3 manual breakout rooms for "class room" with 200 (v1)
      | users::participant2 | 0 |
      | users::participant3 | 1 |
      | users::participant4 | 2 |
    Then user "participant1" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 1     |
      | 3    | Room 2     |
      | 3    | Room 3     |
    Then user "participant2" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 1     |
    Then user "participant3" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 2     |
    Then user "participant4" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 3     |

  Scenario: Co-teachers are promoted and removed in all breakout rooms
    Given user "participant1" creates room "class room" (v4)
      | roomType | 3 |
      | roomName | class room |
    And user "participant1" adds user "participant2" to room "class room" with 200 (v4)
    And user "participant1" sees the following attendees in room "class room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
    And user "participant1" creates 3 manual breakout rooms for "class room" with 200 (v1)
      | users::participant2 | 0 |
    And user "participant1" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 1     |
      | 3    | Room 2     |
      | 3    | Room 3     |
    And user "participant2" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 1     |
    When user "participant1" promotes "participant2" in room "class room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 1     |
      | 3    | Room 2     |
      | 3    | Room 3     |
    And user "participant1" sees the following attendees in room "Room 1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 2               |
    And user "participant1" sees the following attendees in room "Room 2" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 2               |
    And user "participant1" sees the following attendees in room "Room 3" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 2               |
    When user "participant1" demotes "participant2" in room "class room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
    And user "participant1" sees the following attendees in room "Room 1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant1" sees the following attendees in room "Room 2" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant1" sees the following attendees in room "Room 3" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Can not nest breakout rooms
    When user "participant1" creates room "class room" (v4)
      | roomType | 3 |
      | roomName | class room |
    And user "participant1" creates 3 manual breakout rooms for "class room" with 200 (v1)
    And user "participant1" is participant of the following rooms (v4)
      | type | name       |
      | 3    | class room |
      | 3    | Room 1     |
      | 3    | Room 2     |
      | 3    | Room 3     |
    And user "participant1" creates 3 manual breakout rooms for "Room 1" with 400 (v1)

  Scenario: Can not create breakout rooms in one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" creates 3 manual breakout rooms for "one-to-one room" with 400 (v1)
