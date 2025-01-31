Feature: chat/delete
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: moderator deletes their own message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: user deletes their own message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant2" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: user cannot delete without chat permission
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "group room" to "CSJLAVP" with 200 (v4)
    And user "participant2" deletes message "Message 1" from room "group room" with 403
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |

  Scenario: moderator deletes other user message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by {actor}   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: moderator deletes their own message which got replies
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by you     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by {actor}     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by {actor}   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: user deletes their own message which got replies
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant2" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by author     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by you     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: moderator deletes other user message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by you     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by {actor}     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by {actor}   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: Can only delete own messages in one-to-one
    Given user "participant1" creates room "room1" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" sends message "Message 1" to room "room1" with 201
    And user "participant2" sends message "Message 2" to room "room1" with 201
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room1 | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    Then user "participant2" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room1 | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant1" deletes message "Message 2" from room "room1" with 403
    And user "participant2" deletes message "Message 1" from room "room1" with 403
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room1 | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant1" deletes message "Message 1" from room "room1" with 200
    And user "participant2" deletes message "Message 2" from room "room1" with 200
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message                      | messageParameters                                                               |
      | room1 | users     | participant2 | participant2-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room1 | users     | participant1 | participant1-displayname | Message deleted by you       | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant2" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message                      | messageParameters                                                               |
      | room1 | users     | participant2 | participant2-displayname | Message deleted by you       | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room1 | users     | participant1 | participant1-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Clear chat history as a moderator
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room1" with 201
    And user "participant2" sends message "Message 2" to room "room1" with 201
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room1 | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    Then user "participant2" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room1 | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant2" deletes chat history for room "room1" with 403
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room1 | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room1 | users     | participant1 | participant1-displayname | Message 1 | []                |
    Then user "participant1" is participant of room "room1" (v4)
      | unreadMessages | lastReadMessage |
      | 1              | Message 1       |
    Then user "participant2" is participant of room "room1" (v4)
      | unreadMessages | lastReadMessage |
      | 0              | Message 2       |
    And user "participant1" deletes chat history for room "room1" with 200
    Then user "participant1" is participant of room "room1" (v4)
      | unreadMessages | lastReadMessage      |
      | 1              | FIRST_MESSAGE_UNREAD |
    Then user "participant2" is participant of room "room1" (v4)
      | unreadMessages | lastReadMessage      |
      | 1              | FIRST_MESSAGE_UNREAD |
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage   |
      | room1 | users     | participant1 | participant1-displayname | history_cleared |
    Then user "participant2" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage   |
      | room1 | users     | participant1 | participant1-displayname | history_cleared |

  Scenario: Can delete chat history in one-to-one conversations when config is set
    Given user "participant1" creates room "room" with 201 (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" sends message "Message" to room "room" with 201
    Then user "participant1" deletes chat history for room "room" with 403
    When the following "spreed" app config is set
      | delete_one_to_one_conversations | 1 |
    Then user "participant1" deletes chat history for room "room" with 200
