Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant1" sets password "foobar" for room "room" with 200
    Then user "participant3" joins room "room" with 403
    Then user "participant3" joins room "room" with 200
      | password | foobar |
    And user "participant3" leaves room "room" with 200
    When user "participant1" sets password "" for room "room" with 200
    Then user "participant3" joins room "room" with 200

  Scenario: Moderator sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    When user "participant2" sets password "foobar" for room "room" with 200
    Then user "participant3" joins room "room" with 403
    Then user "participant3" joins room "room" with 200
      | password | foobar |
    And user "participant3" leaves room "room" with 200
    When user "participant2" sets password "" for room "room" with 200
    Then user "participant3" joins room "room" with 200

  Scenario: User sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant2" sets password "foobar" for room "room" with 403
    Then user "participant3" joins room "room" with 200
    And user "participant3" leaves room "room" with 200
    When user "participant1" sets password "foobar" for room "room" with 200
    Then user "participant3" joins room "room" with 403
    Then user "participant3" joins room "room" with 200
      | password | foobar |
    And user "participant3" leaves room "room" with 200
    When user "participant2" sets password "" for room "room" with 403
    Then user "participant3" joins room "room" with 403

  Scenario: Stranger sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant2" sets password "foobar" for room "room" with 404
    Then user "participant3" joins room "room" with 200
    And user "participant3" leaves room "room" with 200
    When user "participant1" sets password "foobar" for room "room" with 200
    Then user "participant3" joins room "room" with 403
    Then user "participant3" joins room "room" with 200
      | password | foobar |
    And user "participant3" leaves room "room" with 200
    When user "participant2" sets password "" for room "room" with 404
    Then user "participant3" joins room "room" with 403
