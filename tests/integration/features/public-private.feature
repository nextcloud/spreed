Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner makes room private/public
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    When user "participant1" makes room "room" private with 200
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 2    | 1               | participant1 |
    When user "participant1" makes room "room" public with 200
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |

  Scenario: Moderator makes room private/public
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    When user "participant2" makes room "room" private with 200
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 2    | 1               | participant1, participant2 |
    When user "participant2" makes room "room" public with 200
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1, participant2 |

  Scenario: User makes room private/public
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant2" makes room "room" private with 403
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1, participant2 |
    When user "participant1" makes room "room" private with 200
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 2    | 1               | participant1, participant2 |
    When user "participant2" makes room "room" public with 403
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 2    | 1               | participant1, participant2 |

  Scenario: Stranger makes room private/public
    Given user "participant1" creates room "room"
      | roomType | 3 |
    And user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    When user "participant2" makes room "room" private with 404
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1 |
    When user "participant1" makes room "room" private with 200
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 2    | 1               | participant1 |
    When user "participant2" makes room "room" public with 404
    Then user "participant1" is participant of the following rooms
      | id   | type | participantType | participants |
      | room | 2    | 1               | participant1 |
