Feature: chat/avatar
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Define an image as avatar when the conversation already exists
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
    And user "participant1" send the file "/apps/spreed/img/favicon.png" as avatar of room "room1" with 200
    Then the room "room1" need to have an avatar with 200
    When user "participant1" delete the avatar of room "room1" with 200
    Then the room "room1" need to have an avatar with 404

  Scenario: Try to change the room avatar without success
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
    Then user "participant2" send the file "/apps/spreed/img/favicon.png" as avatar of room "room1" with 404
    And user "participant1" delete the avatar of room "room1" with 404
