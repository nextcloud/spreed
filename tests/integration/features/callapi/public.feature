Feature: callapi/public
  Background:
    Given user "participant1" exists
    And user "participant2" exists
    And user "participant3" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms (v4)
    Then user "participant2" is participant of the following rooms (v4)
    Then user "participant3" is participant of the following rooms (v4)

  Scenario: User1 invites user2 to a public room and they can do everything
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    Then user "participant2" joins room "room" with 200 (v4)
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    And user "participant2" joins call "room" with 200
    Then user "participant1" sees 2 peers in call "room" with 200
    And user "participant2" sees 2 peers in call "room" with 200
    Then user "participant1" leaves call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    Then user "participant1" leaves room "room" with 200 (v4)
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    Then user "participant2" leaves call "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant2" leaves room "room" with 200 (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200

  Scenario: User1 invites user2 to a public room and user3 can do everything
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    Then user "participant3" is not participant of room "room" (v4)
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant3" joins room "room" with 200 (v4)
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 1 peers in call "room" with 200
    And user "participant3" joins call "room" with 200
    Then user "participant1" sees 2 peers in call "room" with 200
    And user "participant3" sees 2 peers in call "room" with 200
    Then user "participant3" leaves call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 1 peers in call "room" with 200
    Then user "participant3" leaves room "room" with 200 (v4)
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant1" leaves call "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant1" leaves room "room" with 200 (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404

  Scenario: User1 invites user2 to a public room and guest can do everything
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "guest" sees 0 peers in call "room" with 404
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    Then user "guest" joins room "room" with 200 (v4)
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 1 peers in call "room" with 200
    And user "guest" joins call "room" with 200
    Then user "participant1" sees 2 peers in call "room" with 200
    And user "guest" sees 2 peers in call "room" with 200
    Then user "guest" leaves call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 1 peers in call "room" with 200
    Then user "guest" leaves room "room" with 200 (v4)
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    Then user "participant1" leaves call "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    Then user "participant1" leaves room "room" with 200 (v4)
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
