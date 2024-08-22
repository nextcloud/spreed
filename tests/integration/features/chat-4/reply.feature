Feature: chat-2/reply
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"

  Scenario: user can reply to own message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |

  Scenario: user can reply to other's messages
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    When user "participant2" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |

  Scenario: several users can reply to the same message several times
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-2" on message "Message 1" to room "group room" with 201
    And user "participant1" sends reply "Message 1-3" on message "Message 1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-4" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-4 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1-3 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1-2 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-4 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1-3 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1-2 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |



  Scenario: user can reply to shared file messages
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" shares "welcome.txt" with room "group room"
    # The messages need to be got so the file message is added to the list of
    # known messages to reply to.
    # The file message parameters are not relevant for this test and are quite
    # large, so they are simply ignored.
    And user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |               |
    When user "participant1" sends reply "Message X-1" on message "{file}" to room "group room" with 201
    And user "participant2" sends reply "Message X-2" on message "{file}" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message X-2 | []                | {file}        |
      | group room | users     | participant1 | participant1-displayname | Message X-1 | []                | {file}        |
      | group room | users     | participant1 | participant1-displayname | {file}      | "IGNORE"          |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message X-2 | []                | {file}        |
      | group room | users     | participant1 | participant1-displayname | Message X-1 | []                | {file}        |
      | group room | users     | participant1 | participant1-displayname | {file}      | "IGNORE"          |               |

  Scenario: user can not reply to system messages
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    # The system messages need to be got so they are added to the list of known
    # messages to reply to.
    And user "participant1" sees the following system messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage        |
      | group room | users     | participant1 | participant1-displayname | user_added           |
      | group room | users     | participant1 | participant1-displayname | group_added           |
      | group room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" sends reply "Message X-1" on message "conversation_created" to room "group room" with 400
    Then user "participant1" sees the following messages in room "group room" with 200
    And user "participant2" sees the following messages in room "group room" with 200



  Scenario: user can reply to own replies
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1-1" on message "Message 1-1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-1 | []                | Message 1-1   |
      | group room | users     | participant1 | participant1-displayname | Message 1-1   | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1     | []                |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-1 | []                | Message 1-1   |
      | group room | users     | participant1 | participant1-displayname | Message 1-1   | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1     | []                |               |

  Scenario: user can reply to other's replies
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1-1" on message "Message 1-1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-1-1-1" on message "Message 1-1-1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message         | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-1-1-1 | []                | Message 1-1-1 |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-1   | []                | Message 1-1   |
      | group room | users     | participant2 | participant2-displayname | Message 1-1     | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1       | []                |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message         | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-1-1-1 | []                | Message 1-1-1 |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-1   | []                | Message 1-1   |
      | group room | users     | participant2 | participant2-displayname | Message 1-1     | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1       | []                |               |

  Scenario: several users can reply to the same reply several times
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1-1" on message "Message 1-1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-1-2" on message "Message 1-1" to room "group room" with 201
    And user "participant1" sends reply "Message 1-1-3" on message "Message 1-1" to room "group room" with 201
    And user "participant2" sends reply "Message 1-1-4" on message "Message 1-1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-1-4 | []                | Message 1-1   |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-3 | []                | Message 1-1   |
      | group room | users     | participant2 | participant2-displayname | Message 1-1-2 | []                | Message 1-1   |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-1 | []                | Message 1-1   |
      | group room | users     | participant2 | participant2-displayname | Message 1-1   | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1     | []                |               |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1-1-4 | []                | Message 1-1   |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-3 | []                | Message 1-1   |
      | group room | users     | participant2 | participant2-displayname | Message 1-1-2 | []                | Message 1-1   |
      | group room | users     | participant1 | participant1-displayname | Message 1-1-1 | []                | Message 1-1   |
      | group room | users     | participant2 | participant2-displayname | Message 1-1   | []                | Message 1     |
      | group room | users     | participant1 | participant1-displayname | Message 1     | []                |               |

  Scenario: getting parent and quote works
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant2" sends reply "Message 2-1" on message "Message 2" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" starting with "Message 1" with 200
      | room       | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1     | []                |               |
      | group room | users     | participant1 | participant1-displayname | Message 2     | []                |               |
      | group room | users     | participant2 | participant2-displayname | Message 2-1   | []                | Message 2     |
    Then user "participant1" sees the following messages in room "group room" starting with "Message 2" with 200
      | room       | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 2     | []                |               |
      | group room | users     | participant2 | participant2-displayname | Message 2-1   | []                | Message 2     |



  Scenario: user can not reply when not in the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    When user "participant3" sends reply "Message 1-1" on message "Message 1" to room "group room" with 404
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |               |
    And user "participant3" sees the following messages in room "group room" with 404



  Scenario: user can not reply to a message from another room
    Given user "participant1" creates room "group room1" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" creates room "group room2" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Message 1" to room "group room1" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room2" with 400
    Then user "participant1" sees the following messages in room "group room1" with 200
      | room        | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant1" sees the following messages in room "group room2" with 200
