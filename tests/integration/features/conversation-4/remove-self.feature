Feature: conversation-2/remove-self
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is not participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    When user "participant1" removes themselves from room "room" with 200 (v4)
    Then user "participant1" is not participant of room "room" (v4)

  Scenario: Moderator removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant2" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 2               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" removes themselves from room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)

  Scenario: Last moderator removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" removes themselves from room "room" with 200 (v4)
    Then user "participant2" gets room "room" with 404 (v4)

  Scenario: User removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" removes themselves from room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)

  Scenario: Self joined user removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 5               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" removes themselves from room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)

  Scenario: Stranger removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)
    When user "participant2" removes themselves from room "room" with 404 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)
