Feature: scaling/attachments
  Background:
    Given user "participant1" exists
    And user "participant2" exists

  Scenario: Conversation list with attachments in many conversations
    When user "participant1" creates 25 rooms (v4)
      | roomType               | 2            |
      | roomName               | IDENTIFIER   |
      | participants[users][0] | participant2 |
    Then user "participant2" is participant of the following unordered rooms (v4)
      | name   | type | participantType |
      | room1  | 2    | 3               |
      | room2  | 2    | 3               |
      | room3  | 2    | 3               |
      | room4  | 2    | 3               |
      | room5  | 2    | 3               |
      | room6  | 2    | 3               |
      | room7  | 2    | 3               |
      | room8  | 2    | 3               |
      | room9  | 2    | 3               |
      | room10 | 2    | 3               |
      | room11 | 2    | 3               |
      | room12 | 2    | 3               |
      | room13 | 2    | 3               |
      | room14 | 2    | 3               |
      | room15 | 2    | 3               |
      | room16 | 2    | 3               |
      | room17 | 2    | 3               |
      | room18 | 2    | 3               |
      | room19 | 2    | 3               |
      | room20 | 2    | 3               |
      | room21 | 2    | 3               |
      | room22 | 2    | 3               |
      | room23 | 2    | 3               |
      | room24 | 2    | 3               |
      | room25 | 2    | 3               |
    And user "participant1" shares "welcome.txt" with 25 rooms
    Given enable query.log
    And note query.log: After sharing attachments to 25 rooms
    Then user "participant2" is participant of the following unordered rooms (v4)
      | name   | type | participantType |
      | room1  | 2    | 3               |
      | room2  | 2    | 3               |
      | room3  | 2    | 3               |
      | room4  | 2    | 3               |
      | room5  | 2    | 3               |
      | room6  | 2    | 3               |
      | room7  | 2    | 3               |
      | room8  | 2    | 3               |
      | room9  | 2    | 3               |
      | room10 | 2    | 3               |
      | room11 | 2    | 3               |
      | room12 | 2    | 3               |
      | room13 | 2    | 3               |
      | room14 | 2    | 3               |
      | room15 | 2    | 3               |
      | room16 | 2    | 3               |
      | room17 | 2    | 3               |
      | room18 | 2    | 3               |
      | room19 | 2    | 3               |
      | room20 | 2    | 3               |
      | room21 | 2    | 3               |
      | room22 | 2    | 3               |
      | room23 | 2    | 3               |
      | room24 | 2    | 3               |
      | room25 | 2    | 3               |
    And disable query.log
