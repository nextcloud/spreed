Feature: one-to-one
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms
    Then user "participant2" is participant of the following rooms
    Then user "participant3" is participant of the following rooms

  Scenario: User1 invites user2 to a one2one room and user3 is not part of it
    When user "participant1" creates room "room1"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 1    | 1               | participant1, participant2 |
    And user "participant2" is participant of the following rooms
      | id    | type | participantType | participants |
      | room1 | 1    | 1               | participant1, participant2 |
    And user "participant3" is participant of the following rooms
    And user "participant1" is participant of room "room1"
    And user "participant2" is participant of room "room1"
    And user "participant3" is not participant of room "room1"

  Scenario: User1 invites user2 to a one2one room and leaves it
    When user "participant1" creates room "room2"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room2"
    And user "participant2" is participant of room "room2"
    And user "participant1" leaves room "room2" with 200
    Then user "participant1" is not participant of room "room2"
    And user "participant2" is not participant of room "room2"

  Scenario: User1 invites user2 to a one2one room and deletes it
    When user "participant1" creates room "room3"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room3"
    And user "participant2" is participant of room "room3"
    And user "participant1" deletes room "room3" with 200
    Then user "participant1" is not participant of room "room3"
    And user "participant2" is not participant of room "room3"

  Scenario: User1 invites user2 to a one2one room and removes user2
    When user "participant1" creates room "room4"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room4"
    And user "participant2" is participant of room "room4"
    And user "participant1" removes "participant2" from room "room4" with 200
    Then user "participant1" is not participant of room "room4"
    And user "participant2" is not participant of room "room4"

  Scenario: User1 invites user2 to a one2one room and they get-peers/join/ping/leave
    When user "participant1" creates room "room5"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room5"
    And user "participant2" is participant of room "room5"
    Then user "participant1" sees 0 peers in call "room5" with 200
    And user "participant2" sees 0 peers in call "room5" with 200
    Then user "participant1" joins call "room5" with 200
    Then user "participant1" sees 1 peers in call "room5" with 200
    And user "participant2" sees 1 peers in call "room5" with 200
    And user "participant2" joins call "room5" with 200
    Then user "participant1" sees 2 peers in call "room5" with 200
    And user "participant2" sees 2 peers in call "room5" with 200
    Then user "participant1" pings call "room5" with 200
    And user "participant2" pings call "room5" with 200
    Then user "participant1" leaves call "room5" with 200
    Then user "participant1" sees 1 peers in call "room5" with 200
    And user "participant2" sees 1 peers in call "room5" with 200

  Scenario: User1 invites user2 to a one2one room and user3 can not get-peers/join/ping
    When user "participant1" creates room "room6"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room6"
    Then user "participant3" is not participant of room "room6"
    Then user "participant1" sees 0 peers in call "room6" with 200
    And user "participant3" sees 0 peers in call "room6" with 404
    Then user "participant1" joins call "room6" with 200
    Then user "participant1" sees 1 peers in call "room6" with 200
    And user "participant3" sees 0 peers in call "room6" with 404
    And user "participant3" joins call "room6" with 404
    Then user "participant1" sees 1 peers in call "room6" with 200
    And user "participant3" sees 0 peers in call "room6" with 404
    Then user "participant1" pings call "room6" with 200
    And user "participant3" pings call "room6" with 404
    Then user "participant3" leaves call "room6" with 200
    Then user "participant1" sees 1 peers in call "room6" with 200
    And user "participant3" sees 0 peers in call "room6" with 404
    Then user "participant1" leaves call "room6" with 200
    Then user "participant1" sees 0 peers in call "room6" with 200
    And user "participant3" sees 0 peers in call "room6" with 404
