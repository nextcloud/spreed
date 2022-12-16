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
    When user "participant1" uploads file "/img/favicon.png" as avatar of room "room2" with 200
    Then the room "room2" has an avatar with 200
    And user "participant1" sees the following system messages in room "room2" with 200
      | room  | actorType     | actorId      | systemMessage        | message                         |
      | room2 | users         | participant1 | set_room_avatar      | You set the conversation avatar |
      | room2 | users         | participant1 | conversation_created | You created the conversation    |
    And user "participant1" delete the avatar of room "room2" with 200
    And user "participant1" sees the following system messages in room "room2" with 200
      | room  | actorType     | actorId      | systemMessage        | message                             |
      | room2 | users         | participant1 | delete_room_avatar   | You deleted the conversation avatar |
      | room2 | users         | participant1 | set_room_avatar      | You set the conversation avatar     |
      | room2 | users         | participant1 | conversation_created | You created the conversation        |

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
