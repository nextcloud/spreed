Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |
    When user "participant1" promotes "participant2" in room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname |
    And user "participant1" demotes "participant2" in room "room" with 200
    And user "participant2" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |

  Scenario: Moderator promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |
    And user "participant1" promotes "participant2" in room "room" with 200
    When user "participant2" promotes "participant3" in room "room" with 200
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname, participant3-displayname |
    When user "participant2" demotes "participant3" in room "room" with 200
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |

  Scenario: User promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |
    When user "participant2" promotes "participant3" in room "room" with 403
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |
    When user "participant1" promotes "participant3" in room "room" with 200
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname, participant3-displayname |
    When user "participant2" demotes "participant3" in room "room" with 403
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname, participant3-displayname |

  Scenario: Stranger promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant3-displayname |
    When user "participant2" promotes "participant3" in room "room" with 404
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant3-displayname |
    When user "participant1" promotes "participant3" in room "room" with 200
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant3-displayname |
    When user "participant2" demotes "participant3" in room "room" with 404
    Then user "participant3" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant3-displayname |
