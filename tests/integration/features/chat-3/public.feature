Feature: chat-2/public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: owner can send and receive chat messages to and from public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" sends message "Message 1" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Message 1 | []                |

  Scenario: invited user can send and receive chat messages to and from public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    When user "participant2" sends message "Message 1" to room "public room" with 201
    Then user "participant2" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | users     | participant2 | participant2-displayname | Message 1 | []                |

  Scenario: invited user can not send without chat permissions
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "public room" to "CSJLAVP" with 200 (v4)
    When user "participant2" sends message "Message 1" to room "public room" with 403
    When user "participant1" sends message "Message 2" to room "public room" with 201
    Then user "participant2" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Message 2 | []                |

  Scenario: not invited but joined user can send and receive chat messages to and from public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" joins room "public room" with 200 (v4)
    When user "participant3" sends message "Message 1" to room "public room" with 201
    Then user "participant3" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | users     | participant3 | participant3-displayname | Message 1 | []                |

  Scenario: not invited user can not send nor receive chat messages to and from public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant3" sends message "Message 1" to room "public room" with 404
    And user "participant1" sends message "Message 2" to room "public room" with 201
    Then user "participant3" sees the following messages in room "public room" with 404

  Scenario: joined guest can send and receive chat messages to and from public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "guest" joins room "public room" with 200 (v4)
    When user "guest" sends message "Message 1" to room "public room" with 201
    Then user "guest" sees the following messages in room "public room" with 200
      | room        | actorType | actorId | actorDisplayName | message   | messageParameters |
      | public room | guests    | guest   |                  | Message 1 | []                |

  Scenario: not joined guest can not send nor receive chat messages to and from public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "guest" sends message "Message 1" to room "public room" with 404
    And user "participant1" sends message "Message 2" to room "public room" with 201
    Then user "guest" sees the following messages in room "public room" with 404

  Scenario: everyone in a public room can receive messages from everyone in that room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "public room" with 201
    And user "participant2" sends message "Message 2" to room "public room" with 201
    And user "guest" sends message "Message 3" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | guests    | guest        |                          | Message 3 | []                |
      | public room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant2" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | guests    | guest        |                          | Message 3 | []                |
      | public room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "guest" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public room | guests    | guest        |                          | Message 3 | []                |
      | public room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public room | users     | participant1 | participant1-displayname | Message 1 | []                |


  Scenario: Create room with big name and rename
    # 260 chars
    When user "participant1" creates room "public room" with 400 (v4)
      | roomType | 3 |
      | roomName | A name with 260 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 12345678 |
    # 255 chars
    And user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | A name with 255 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123 |
    # 260 chars
    And user "participant1" renames room "public room" to "A name with 260 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 12345678" with 400 (v4)
    # 255 chars
    And user "participant1" renames room "public room" to "Another name with 255 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 1234567" with 200 (v4)

  Scenario: Create public room with a password
    When user "participant1" creates room "public room" with 201 (v4)
      | roomType | 3 |
      | roomName | A room name |
      | password | ARoomPassword123. |
