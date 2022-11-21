Feature: conversation/avatar
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Misteps
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
    Then user "participant1" uploads file "invalid" as avatar of room "room1" with 400
    And user "participant2" uploads file "/img/favicon.png" as avatar of room "room1" with 404
    And user "participant2" delete the avatar of room "room1" with 404

  Scenario: Define an image as avatar with success
    Given user "participant1" creates room "room2" (v4)
      | roomType | 3 |
      | roomName | room2 |
    And user "participant1" uploads file "/img/favicon.png" as avatar of room "room2" with 200
    Then the room "room2" has an avatar with 200
    And user "participant1" delete the avatar of room "room2" with 200

  Scenario: Get avatar of conversation without custom avatar (fallback)
    Given user "participant1" creates room "room3" (v4)
      | roomType | 3 |
      | roomName | room3 |
    Then the room "room3" has an avatar with 200

  Scenario: Get avatar of one2one without custom avatar (fallback)
    When user "participant1" creates room "one2one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then the room "one2one" has an avatar with 200

  Scenario: Try to change avatar of one2one without success
    When user "participant1" creates room "one2one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" uploads file "/img/favicon.png" as avatar of room "one2one" with 400

  Scenario: get mentions in a group room with no other participant
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" uploads file "/img/favicon.png" as avatar of room "group room" with 200
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | avatar       |
      | all          | room                     | calls  | group room   |
