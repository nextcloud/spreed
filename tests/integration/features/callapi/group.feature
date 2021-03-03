Feature: callapi/group
  Background:
    Given user "participant1" exists
    And user "participant2" exists
    And user "participant3" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms
    Then user "participant2" is participant of the following rooms
    Then user "participant3" is participant of the following rooms

  Scenario: User1 invites group attendees1 to a group room and they can do everything
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    Then user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins room "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins call "room" with 200
      | flags | 1 |
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    Then user "participant1" is participant of the following rooms (v3)
      | id   | type | callFlag |
      | room | 2    | 1        |
    Then user "participant2" joins room "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    And user "participant2" joins call "room" with 200
    Then user "participant1" sees 2 peers in call "room" with 200
    And user "participant2" sees 2 peers in call "room" with 200
    Then user "participant1" is participant of the following rooms (v3)
      | id   | type | callFlag |
      | room | 2    | 7        |
    Then user "participant1" leaves call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    Then user "participant1" leaves room "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant2" sees 1 peers in call "room" with 200
    Then user "participant2" leaves call "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant2" leaves room "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200

  Scenario: User1 invites group attendees1 to a group room and user3 can't do anything
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    Then user "participant1" is participant of room "room"
    Then user "participant3" is not participant of room "room"
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant1" joins room "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant2" sees 0 peers in call "room" with 200
    Then user "participant1" joins call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    And user "participant3" joins room "room" with 404
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    And user "participant3" joins call "room" with 404
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant3" leaves call "room" with 404
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant3" leaves room "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404
    Then user "participant1" leaves call "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 404

  Scenario: User1 invites group attendees1 to a group room and guest can't do anything
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    Then user "participant1" is participant of room "room"
    And user "guest" sees 0 peers in call "room" with 404
    Then user "participant1" joins room "room" with 200
    Then user "participant1" joins call "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    And user "guest" joins room "room" with 404
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    And user "guest" joins call "room" with 404
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    Then user "guest" leaves call "room" with 404
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    Then user "guest" leaves room "room" with 200
    Then user "participant1" sees 1 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
    Then user "participant1" leaves call "room" with 200
    Then user "participant1" sees 0 peers in call "room" with 200
    And user "guest" sees 0 peers in call "room" with 404
