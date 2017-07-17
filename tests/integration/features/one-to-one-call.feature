Feature: one-to-one-call
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms
    Then user "participant2" is participant of the following rooms
    Then user "participant3" is participant of the following rooms

  Scenario: User1 invites user2 to a one2one room and they get-peers and join/leave
    When user "participant1" creates room "room1"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room1"
    And user "participant2" is participant of room "room1"
    Then user "participant1" sees 0 peers in call "room1" with 200
    And user "participant2" sees 0 peers in call "room1" with 200
    Then user "participant1" joins call "room1" with 200
    Then user "participant1" sees 1 peers in call "room1" with 200
    And user "participant2" sees 1 peers in call "room1" with 200
    And user "participant2" joins call "room1" with 200
    Then user "participant1" sees 2 peers in call "room1" with 200
    And user "participant2" sees 2 peers in call "room1" with 200
    Then user "participant1" leaves call "room1" with 200
    Then user "participant1" sees 1 peers in call "room1" with 200
    And user "participant2" sees 1 peers in call "room1" with 200
    Then user "participant2" leaves call "room1" with 200
    Then user "participant1" sees 0 peers in call "room1" with 200
    And user "participant2" sees 0 peers in call "room1" with 200

  Scenario: User1 invites user2 to a one2one room and they ping
    When user "participant1" creates room "room2"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" pings call "room2" with 200
    And user "participant2" pings call "room2" with 200

  Scenario: User1 invites user2 to a one2one room and user3 can not get-peers
    When user "participant1" creates room "room3"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room3"
    Then user "participant3" is not participant of room "room3"
    And user "participant3" sees 0 peers in call "room3" with 404
    Then user "participant1" joins call "room3" with 200
    Then user "participant1" sees 1 peers in call "room3" with 200
    And user "participant3" sees 0 peers in call "room3" with 404
    And user "participant3" joins call "room3" with 404
    Then user "participant1" sees 1 peers in call "room3" with 200
    And user "participant3" sees 0 peers in call "room3" with 404
    Then user "participant3" leaves call "room3" with 200
    Then user "participant1" sees 1 peers in call "room3" with 200
    And user "participant3" sees 0 peers in call "room3" with 404
    Then user "participant1" leaves call "room3" with 200
    Then user "participant1" sees 0 peers in call "room3" with 200
    And user "participant3" sees 0 peers in call "room3" with 404

  Scenario: User1 invites user2 to a one2one room and user3 can not ping
    When user "participant1" creates room "room4"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room4"
    And user "participant3" is not participant of room "room4"
    Then user "participant1" pings call "room4" with 200
    And user "participant3" pings call "room4" with 404
