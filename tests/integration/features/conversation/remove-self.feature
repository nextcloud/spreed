Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname |
    And user "participant2" is not participant of room "room"
    And user "participant3" is not participant of room "room"
    When user "participant1" removes themselves from room "room" with 200
    Then user "participant1" is not participant of room "room"

  Scenario: Moderator removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" removes themselves from room "room" with 200
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: Last moderator removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" removes themselves from room "room" with 200
    Then user "participant2" gets room "room" with 404 (v3)

  Scenario: User removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" removes themselves from room "room" with 200
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: Self joined user removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 5               | participant1-displayname, participant2-displayname |
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" removes themselves from room "room" with 200
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"

  Scenario: Stranger removes the room from their room list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
    When user "participant2" removes themselves from room "room" with 404
    Then user "participant1" is participant of room "room"
    And user "participant2" is not participant of room "room"
