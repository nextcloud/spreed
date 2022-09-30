Feature: conversation-2/promotion-demotion
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant1" demotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: Moderator promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant2" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: User promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" promotes "participant3" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant1" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |

  Scenario: Stranger promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" promotes "participant3" in room "room" with 404 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant1" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 404 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
