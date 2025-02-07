Feature: conversation/avatar
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    And guest accounts can be created
    And user "user-guest@example.com" is a guest account user

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
    Then user "participant1" gets room "room2" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 1 |
    And the room "room2" has not an svg as avatar with 200
    And user "participant1" sees the following system messages in room "room2" with 200
      | room  | actorType     | actorId      | systemMessage        | message                          |
      | room2 | users         | participant1 | avatar_set           | You set the conversation picture |
      | room2 | users         | participant1 | conversation_created | You created the conversation     |
    When user "participant1" delete the avatar of room "room2" with 200
    Then user "participant1" sees the following system messages in room "room2" with 200
      | room  | actorType     | actorId      | systemMessage        | message                              |
      | room2 | users         | participant1 | avatar_removed       | You removed the conversation picture |
      | room2 | users         | participant1 | avatar_set           | You set the conversation picture     |
      | room2 | users         | participant1 | conversation_created | You created the conversation         |
    And user "participant1" gets room "room2" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
    Then the room "room2" has an avatar with 200

  Scenario: Get avatar of conversation without custom avatar (fallback)
    Given user "participant1" creates room "room3" (v4)
      | roomType | 3 |
      | roomName | room3 |
    Then the room "room3" has an avatar with 200
    And user "participant1" gets room "room3" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |

  Scenario: Get avatar of conversation without being a participant
    Given user "participant1" creates room "room3" (v4)
      | roomType | 3 |
      | roomName | room3 |
    Then the room "room3" has an avatar with 200
    And user "participant1" gets room "room3" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
    And as user "participant2"
    And the room "room3" has an avatar with 404
    And as user "user-guest@example.com"
    And the room "room3" has an avatar with 404
    And as user "guest"
    And the room "room3" has an avatar with 404
    When user "participant1" allows listing room "room3" for "users" with 200 (v4)
    And as user "participant2"
    And the room "room3" has an avatar with 200
    And as user "user-guest@example.com"
    And the room "room3" has an avatar with 404
    And as user "guest"
    And the room "room3" has an avatar with 404
    When user "participant1" allows listing room "room3" for "all" with 200 (v4)
    And as user "participant2"
    And the room "room3" has an avatar with 200
    And as user "user-guest@example.com"
    And the room "room3" has an avatar with 200
    And as user "guest"
    And the room "room3" has an avatar with 404

  Scenario: Get avatar of one2one without custom avatar (fallback)
    When user "participant1" creates room "one2one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then the room "one2one" has an avatar with 200
    And user "participant1" gets room "one2one" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |

  Scenario: Try to change avatar of one2one without success
    When user "participant1" creates room "one2one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" uploads file "/img/favicon.png" as avatar of room "one2one" with 400
    And user "participant1" gets room "one2one" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
    Then user "participant1" sets emoji "👋" with color "123456" as avatar of room "one2one" with 400 (v1)
    And user "participant1" gets room "one2one" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |

  Scenario: Conversation that the name start with emoji dont need to have custom avatar
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
    And the room "room1" has an svg as avatar with 200
    And user "participant1" gets room "room1" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
      | displayName | room1 |
    And user "participant1" renames room "room1" to "💙room2" with 200 (v4)
    Then user "participant1" gets room "room1" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
      | displayName | 💙room2 |
    And the room "room1" has an svg as avatar with 200
    And the avatar svg of room "room1" contains the string "💙"
    When user "participant1" renames room "room1" to "room1" with 200 (v4)
    Then user "participant1" gets room "room1" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
      | displayName | room1 |
    And the room "room1" has an svg as avatar with 200

  Scenario: User should receive the room avatar when see a rich object at media tab
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | public room |
    And user "participant1" uploads file "/img/favicon.png" as avatar of room "public room" with 200
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    Then user "participant1" sees the following shared other in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"object":{"name":"Another room","call-type":"group","type":"call","id":"R4nd0mT0k3n","icon-url":"{VALIDATE_ICON_URL_PATTERN}"}} |

  Scenario: User sets emoji as avatar
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | public room |
    Then user "participant1" sets emoji "👋🚀" with color "123456" as avatar of room "room" with 400 (v1)
    And user "participant1" sets emoji "👋" with color "1234567" as avatar of room "room" with 400 (v1)
    And user "participant1" sets emoji "👋" with color "GGGGGG" as avatar of room "room" with 400 (v1)
    And user "participant1" gets room "room" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 0 |
    Then user "participant1" sets emoji "👩🏽‍🚀" with color "123456" as avatar of room "room" with 200 (v1)
    And user "participant1" gets room "room" with 200 (v4)
      | avatarVersion | NOT_EMPTY |
      | isCustomAvatar | 1 |
    And the room "room" has an svg as avatar with 200
    And the avatar svg of room "room" contains the string "👩🏽‍🚀"
    And the avatar svg of room "room" contains the string "123456"
    Then user "participant1" sets emoji "🍏" with color "null" as avatar of room "room" with 200 (v1)
    And the avatar svg of room "room" contains the string "🍏"
    And the avatar svg of room "room" contains the string "6B6B6B"
    And the avatar svg of room "room" not contains the string "3B3B3B"
    And the dark avatar svg of room "room" contains the string "🍏"
    And the dark avatar svg of room "room" not contains the string "6B6B6B"
    And the dark avatar svg of room "room" contains the string "3B3B3B"
