Feature: scaling/conversations
  Background:
    Given user "participant1" exists

  Scenario: Conversation list with many conversations
    Given enable query.log
    Then user "participant1" is participant of the following unordered rooms (v4)
    And note query.log: After creating samples and talk update
    When user "participant1" creates 75 rooms (v4)
      | roomType | 2          |
      | roomName | IDENTIFIER |
    And note query.log: After creation of 75 rooms
    Then user "participant1" is participant of the following unordered rooms (v4)
      | name   | type | participantType |
      | room1  | 2    | 1               |
      | room2  | 2    | 1               |
      | room3  | 2    | 1               |
      | room4  | 2    | 1               |
      | room5  | 2    | 1               |
      | room6  | 2    | 1               |
      | room7  | 2    | 1               |
      | room8  | 2    | 1               |
      | room9  | 2    | 1               |
      | room10 | 2    | 1               |
      | room11 | 2    | 1               |
      | room12 | 2    | 1               |
      | room13 | 2    | 1               |
      | room14 | 2    | 1               |
      | room15 | 2    | 1               |
      | room16 | 2    | 1               |
      | room17 | 2    | 1               |
      | room18 | 2    | 1               |
      | room19 | 2    | 1               |
      | room20 | 2    | 1               |
      | room21 | 2    | 1               |
      | room22 | 2    | 1               |
      | room23 | 2    | 1               |
      | room24 | 2    | 1               |
      | room25 | 2    | 1               |
      | room26 | 2    | 1               |
      | room27 | 2    | 1               |
      | room28 | 2    | 1               |
      | room29 | 2    | 1               |
      | room30 | 2    | 1               |
      | room31 | 2    | 1               |
      | room32 | 2    | 1               |
      | room33 | 2    | 1               |
      | room34 | 2    | 1               |
      | room35 | 2    | 1               |
      | room36 | 2    | 1               |
      | room37 | 2    | 1               |
      | room38 | 2    | 1               |
      | room39 | 2    | 1               |
      | room40 | 2    | 1               |
      | room41 | 2    | 1               |
      | room42 | 2    | 1               |
      | room43 | 2    | 1               |
      | room44 | 2    | 1               |
      | room45 | 2    | 1               |
      | room46 | 2    | 1               |
      | room47 | 2    | 1               |
      | room48 | 2    | 1               |
      | room49 | 2    | 1               |
      | room50 | 2    | 1               |
      | room51 | 2    | 1               |
      | room52 | 2    | 1               |
      | room53 | 2    | 1               |
      | room54 | 2    | 1               |
      | room55 | 2    | 1               |
      | room56 | 2    | 1               |
      | room57 | 2    | 1               |
      | room58 | 2    | 1               |
      | room59 | 2    | 1               |
      | room60 | 2    | 1               |
      | room61 | 2    | 1               |
      | room62 | 2    | 1               |
      | room63 | 2    | 1               |
      | room64 | 2    | 1               |
      | room65 | 2    | 1               |
      | room66 | 2    | 1               |
      | room67 | 2    | 1               |
      | room68 | 2    | 1               |
      | room69 | 2    | 1               |
      | room70 | 2    | 1               |
      | room71 | 2    | 1               |
      | room72 | 2    | 1               |
      | room73 | 2    | 1               |
      | room74 | 2    | 1               |
      | room75 | 2    | 1               |
    And disable query.log
