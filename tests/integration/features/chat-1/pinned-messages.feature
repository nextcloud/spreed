Feature: chat-1/pinned-messages
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Moderators can pin and unpin messages
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant1" sends message "Message 2" to room "room" with 201
    When user "participant2" pins message "Message 2" in room "room" with 403
    When user "participant1" pins message "Message 2" in room "room" with 200
    When user "participant1" pins message "Message 1" in room "room" with 200
    When user "participant1" pins message "Message 2" in room "room" with 200
    When user "participant2" unpins message "Message 1" in room "room" with 403
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | Message 2 | []                |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | systemMessage            | message                | messageParameters |
      | room | users     | participant1 | message_pinned           | You pinned a message   | "IGNORE"          |
      | room | users     | participant1 | message_pinned           | You pinned a message   | "IGNORE"          |
      | room | users     | participant1 | user_added               | You added {user}             | "IGNORE"    |
      | room | users     | participant1 | conversation_created     | You created the conversation | "IGNORE"    |
    Then user "participant1" sees the following shared pinned in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                |
      | room | users     | participant1 | participant1-displayname | Message 2 | []                |
    When user "participant1" unpins message "Message 1" in room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | systemMessage            | message                | messageParameters |
      | room | users     | participant1 | message_unpinned         | You unpinned a message | "IGNORE"          |
      | room | users     | participant1 | message_pinned           | You pinned a message   | "IGNORE"          |
      | room | users     | participant1 | message_pinned           | You pinned a message   | "IGNORE"          |
      | room | users     | participant1 | user_added               | You added {user}             | "IGNORE"    |
      | room | users     | participant1 | conversation_created     | You created the conversation | "IGNORE"    |
    Then user "participant1" sees the following shared pinned in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | Message 2 | []                |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | hidePinnedId |
      | room | 3    | EMPTY        |
    When user "participant2" hides pinned message "Message 2" in room "room" with 200
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | hidePinnedId |
      | room | 3    | Message 2    |
    When user "participant1" unpins message "Message 2" in room "room" with 200
    When user "participant1" pins message "Message 2" in room "room" with 200
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | hidePinnedId |
      | room | 3    | EMPTY        |
