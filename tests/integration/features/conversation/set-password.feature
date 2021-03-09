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
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant1" sets password "" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 200 (v4)

  Scenario: Moderator sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant2" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant2" sets password "" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 200 (v4)

  Scenario: User sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" sets password "foobar" for room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant2" sets password "" for room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 403 (v4)

  Scenario: Stranger sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant2" sets password "foobar" for room "room" with 404 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant2" sets password "" for room "room" with 404 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
