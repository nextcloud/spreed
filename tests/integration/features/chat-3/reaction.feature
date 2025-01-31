Feature: chat-2/reaction
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: React to message with success
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant2 | participant2-displayname | reaction |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions | reactionsSelf |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1}  |               |
    And user "participant1" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
      | users     | participant2 | participant2-displayname | 👍       |
    And user "participant1" react with "🚀" on message "Message 1" to room "room" with 201
    Then user "guest" joins room "room" with 200 (v4)
    And user "guest" react with "👤" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions              | reactionsSelf |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":2,"👤":1,"🚀":1} | ["👍","🚀"]   |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | guests    | guest        |                          | reaction |
      | room | users     | participant1 | participant1-displayname | reaction |
      | room | users     | participant1 | participant1-displayname | reaction |
      | room | users     | participant2 | participant2-displayname | reaction |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: React to message fails without chat permission
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant2 | participant2-displayname | reaction |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "room" to "CSJLAVP" with 200 (v4)
    When user "participant2" delete react with "👍" on message "Message 1" to room "room" with 403
    And user "participant2" react with "💙" on message "Message 1" to room "room" with 403
    And user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant2 | participant2-displayname | reaction |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: React two times to same message with the same reaction
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1}  |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant2 | participant2-displayname | reaction |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: React to message does not fail when the author left the conversation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "room" with 201
    And user "participant2" removes themselves from room "room" with 200 (v4)
    And user "participant1" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | reaction |
      | room | users     | participant2 | participant2-displayname | user_removed |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Delete reaction to message with success
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    Then user "guest" joins room "room" with 200 (v4)
    And user "guest" react with "👤" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1,"👤":1}  |
    And user "participant2" delete react with "👍" on message "Message 1" to room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
      | guests    | guest        |                          | 👤       |
    And user "guest" delete react with "👤" on message "Message 1" to room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | []        |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | guests    | guest        |                          | reaction_revoked |
      | room | users     | participant2 | participant2-displayname | reaction_revoked |
      | room | guests    | guest        |                          | reaction_deleted |
      | room | users     | participant2 | participant2-displayname | reaction_deleted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Retrieve reactions of a message
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
    And user "participant1" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
      | users     | participant2 | participant2-displayname | 👍       |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
      | users     | participant2 | participant2-displayname | 👍       |
    And user "participant2" react with "👎" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
      | users     | participant2 | participant2-displayname | 👎       |
      | users     | participant2 | participant2-displayname | 👍       |
    And user "participant1" retrieve reactions "👎" of message "Message 1" in room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👎       |
    And user "participant1" retrieve reactions "all" of message "Message 1" in room "room" with 200
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant1 | participant1-displayname | 👍       |
      | users     | participant2 | participant2-displayname | 👎       |
      | users     | participant2 | participant2-displayname | 👍       |

  Scenario: Delete message that was reacted to
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    And user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1}  |
    Then user "participant1" deletes message "Message 1" from room "room" with 200 (v1)
    And user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                | messageParameters                                                               | reactions |
      | room | users     | participant1 | participant1-displayname | Message deleted by you | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} | []        |

  Scenario: Deleting a user neutralizes their details
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👍       |
    And user "participant2" react with "👎" on message "Message 1" to room "room" with 201
      | actorType | actorId      | actorDisplayName         | reaction |
      | users     | participant2 | participant2-displayname | 👎       |
      | users     | participant2 | participant2-displayname | 👍       |
    When user "participant2" is deleted
    And user "participant1" retrieve reactions "👎" of message "Message 1" in room "room" with 200
      | actorType     | actorId       | actorDisplayName | reaction |
      | deleted_users | deleted_users |                  | 👎       |
    And user "participant1" retrieve reactions "all" of message "Message 1" in room "room" with 200
      | actorType     | actorId       | actorDisplayName | reaction |
      | deleted_users | deleted_users |                  | 👎       |
      | deleted_users | deleted_users |                  | 👍       |
