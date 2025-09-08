Feature: chat-2/search
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Can not search when not a participant
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant2" searches for messages with "essa" in room "room" with 200

  Scenario: Search for message when being a participant
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
    Given user "participant1" creates room "room2" (v4)
      | roomType | 3 |
      | roomName | room2 |
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    And user "participant1" adds user "participant2" to room "room2" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room1" with 201
    And user "participant1" sends message "Message 2" to room "room2" with 201
    And user "participant1" sends thread "Thread 1" with message "Thread 1" to room "room1" with 201
    And user "participant2" sends reply "Thread 1-1" on thread "Thread 1" to room "room1" with 201
    When user "participant1" shares "welcome.txt" with room "room1"
      | talkMetaData | {"caption":"Message 3"} |
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message    | messageParameters | parentMessage |
      | room1 | users     | participant1 | participant1-displayname | Message 3  | "IGNORE"          |               |
      | room1 | users     | participant2 | participant2-displayname | Thread 1-1 | []                | Thread 1      |
      | room1 | users     | participant1 | participant1-displayname | Thread 1   | []                |               |
      | room1 | users     | participant1 | participant1-displayname | Message 1  | []                |               |
    When user "participant2" searches for messages with "essa" in room "room1" with 200
      | title                    | subline   | attributes.conversation | attributes.messageId |
      | participant1-displayname | Message 3 | room1                   | Message 3            |
      | participant1-displayname | Message 1 | room1                   | Message 1            |
    When user "participant2" searches for messages with "essa" in room "room2" with 200
      | title                    | subline   | attributes.conversation | attributes.messageId |
      | participant1-displayname | Message 2 | room2                   | Message 2            |
    When user "participant2" searches for messages with "conversation:ROOM(room1) essa" in room "room1" with 200
      | title                    | subline   | attributes.conversation | attributes.messageId |
      | participant1-displayname | Message 3 | room1                   | Message 3            |
      | participant1-displayname | Message 1 | room1                   | Message 1            |
    When user "participant2" searches for messages in other rooms with "conversation:ROOM(room1) essa" in room "room1" with 200
    When user "participant2" searches for messages with "conversation:ROOM(room1) essa" in room "room2" with 200
    When user "participant2" searches for messages in other rooms with "conversation:ROOM(room1) essa" in room "room2" with 200
      | title                    | subline   | attributes.conversation | attributes.messageId |
      | participant1-displayname | Message 3 | room1                   | Message 3            |
      | participant1-displayname | Message 1 | room1                   | Message 1            |
    When user "participant2" searches for messages with "read" in room "room1" with 200
      | title                    | subline    | attributes.conversation | attributes.threadId | attributes.messageId |
      | participant2-displayname | Thread 1-1 | room1                   | Thread 1            | Thread 1-1           |
      | participant1-displayname | Thread 1   | room1                   | Thread 1            | Thread 1             |

  Scenario: Can not search when being blocked by the lobby
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    When user "participant2" searches for messages with "essa" in room "room" with 200
