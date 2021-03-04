Feature: conversation/join-leave

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: join a one-to-one room
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" joins room "room" with 200
    And user "participant2" joins room "room" with 200
    And user "participant3" joins room "room" with 404
    And user "guest" joins room "room" with 404
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And user "guest" is not participant of room "room" (v4)

  Scenario: leave a one-to-one room
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" joins room "room" with 200
    And user "participant2" joins room "room" with 200
    When user "participant1" leaves room "room" with 200
    And user "participant2" leaves room "room" with 200
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)



  Scenario: join a group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant1" joins room "room" with 200
    And user "participant2" joins room "room" with 200
    And user "participant3" joins room "room" with 404
    And user "guest" joins room "room" with 404
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And user "guest" is not participant of room "room" (v4)

  Scenario: leave a group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" joins room "room" with 200
    And user "participant2" joins room "room" with 200
    When user "participant1" leaves room "room" with 200
    And user "participant2" leaves room "room" with 200
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)



  Scenario: join a public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant1" joins room "room" with 200
    And user "participant2" joins room "room" with 200
    And user "participant3" joins room "room" with 200
    And user "guest" joins room "room" with 200
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is participant of room "room" (v4)
    And user "guest" is participant of room "room" (v4)

  Scenario: leave a public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" joins room "room" with 200
    And user "participant2" joins room "room" with 200
    And user "participant3" joins room "room" with 200
    And user "guest" joins room "room" with 200
    When user "participant1" leaves room "room" with 200
    And user "participant2" leaves room "room" with 200
    And user "participant3" leaves room "room" with 200
    And user "guest" leaves room "room" with 200
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And user "guest" is not participant of room "room" (v4)
