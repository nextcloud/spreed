Feature: chat/group-avatar
  Background:
    Given user "participant1" exists

  Scenario: Define an image as group avatar when the group already exists
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
    And user "participant1" send the file "/apps/spreed/img/favicon.png" as avatar of room "room1" with 200
    Then the room "room1" need to have an avatar with 200
    When user "participant1" delete the avatar of room "room1"
    Then the room "room1" need to have an avatar with 404
