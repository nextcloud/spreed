Feature: chat-1/edit-message
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Moderator edits their own message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |
    And user "participant2" edits message "Message 1 - Edit 1" in room "room" to "Message 1 - Edit 2" with 403
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |

  Scenario: User and moderator edit user message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage | lastEditActorType | lastEditActorId      | lastEditActorDisplayName |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 1 | []                |               | users             | participant1         | participant1-displayname |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage | lastEditActorType | lastEditActorId      | lastEditActorDisplayName |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 1 | []                |               | users             | participant1         | participant1-displayname |
    And user "participant2" edits message "Message 1 - Edit 1" in room "room" to "Message 1 - Edit 2" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage | lastEditActorType | lastEditActorId      | lastEditActorDisplayName |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2 | []                |               | users             | participant2         | participant2-displayname |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage | lastEditActorType | lastEditActorId      | lastEditActorDisplayName |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2 | []                |               | users             | participant2         | participant2-displayname |
    And user "participant2" edits message "Message 1 - Edit 1" in room "room" to "" with 400
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage | lastEditActorType | lastEditActorId      | lastEditActorDisplayName |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2 | []                |               | users             | participant2         | participant2-displayname |
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType     | actorId       | actorDisplayName | message            | messageParameters | parentMessage | lastEditActorType | lastEditActorId      | lastEditActorDisplayName |
      | room | deleted_users | deleted_users |                  | Message 1 - Edit 2 | []                |               | deleted_users     | deleted_users        |                          |
    When aging messages 6 hours in room "room"
    And user "participant1" edits message "Message 1 - Edit 1" in room "room" to "Message 1 - Edit 2" with 200
    When aging messages 24 hours in room "room"
    And user "participant1" edits message "Message 1 - Edit 2" in room "room" to "Message 1 - Edit Too old" with 400
      | error | age |

  Scenario: Editing a caption
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" shares "welcome.txt" with room "room"
      | talkMetaData | {"caption":"Caption 1"} |
    And user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant1 | participant1-displayname | Caption 1 | "IGNORE"          |
    When user "participant1" edits message "Caption 1" in room "room" to "Caption 1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters |
      | room | users     | participant1 | participant1-displayname | Caption 1 - Edit 1 | "IGNORE"          |
    When user "participant1" edits message "Caption 1" in room "room" to "" with 400
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters |
      | room | users     | participant1 | participant1-displayname | Caption 1 - Edit 1 | "IGNORE"          |

  Scenario: Notification handling - None vs Direct mention
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1   | []                |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 0             | 0                   |
    Then user "participant1" has the following notifications
    And user "participant2" edits message "Message 1" in room "room" to "Message 1 - Edit @participant1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                          | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit {mention-user1} | {"mention-user1":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                            | subject                                                      |
      | spreed | chat        | room/Message 1 - Edit {mention-user1} | participant2-displayname mentioned you in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |
    And user "participant2" edits message "Message 1" in room "room" to "Message 1 - Edit 2" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                        | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2             | []                |
    Then user "participant1" has the following notifications
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |

  Scenario: Notification handling - None vs All
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1   | []                |
    Then user "participant1" has the following notifications
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 0             | 0                   |
    And user "participant2" edits message "Message 1" in room "room" to "Message 1 - Edit @all" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                          | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit {mention-call1} | "IGNORE"          |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                            | subject                                                           |
      | spreed | chat        | room/Message 1 - Edit {mention-call1} | participant2-displayname mentioned everyone in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 0                   |
    And user "participant2" edits message "Message 1" in room "room" to "Message 1 - Edit 2" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                        | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2             | []                |
    Then user "participant1" has the following notifications
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 0                   |

  Scenario: Notification handling - Direct mention vs All
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    And user "participant2" sends message "Message 1 - @participant1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                     | messageParameters                                                                       |
      | room | users     | participant2 | participant2-displayname | Message 1 - {mention-user1} | {"mention-user1":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                            | subject                                                      |
      | spreed | chat        | room/Message 1 - {mention-user1} | participant2-displayname mentioned you in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |
    And user "participant2" edits message "Message 1 - @participant1" in room "room" to "Message 1 - Edit @all" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                          | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit {mention-call1} | "IGNORE"          |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                            | subject                                                      |
      | spreed | chat        | room/Message 1 - Edit {mention-call1} | participant2-displayname mentioned you in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |
    And user "participant2" edits message "Message 1 - @participant1" in room "room" to "Message 1 - Edit @participant1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                          | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit {mention-user1} | {"mention-user1":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                            | subject                                                      |
      | spreed | chat        | room/Message 1 - Edit {mention-user1} | participant2-displayname mentioned you in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |


  Scenario: Notification handling - All vs Direct mention
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    And user "participant2" sends message "Message 1 - @all" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                     | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - {mention-call1} | "IGNORE"          |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                        | subject                                                          |
      | spreed | chat        | room/Message 1 - {mention-call1} | participant2-displayname mentioned everyone in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 0                   |
    And user "participant2" edits message "Message 1 - @all" in room "room" to "Message 1 - Edit @participant1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                          | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit {mention-user1} | {"mention-user1":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                        | subject                                                          |
      | spreed | chat        | room/Message 1 - Edit {mention-user1} | participant2-displayname mentioned everyone in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |
    And user "participant2" edits message "Message 1 - Edit @participant1" in room "room" to "Message 1 - Edit @all" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                          | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit {mention-call1} | "IGNORE"          |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id                        | subject                                                          |
      | spreed | chat        | room/Message 1 - Edit {mention-call1} | participant2-displayname mentioned everyone in conversation room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | unreadMention | unreadMentionDirect |
      | room | 1              | 1             | 1                   |
