Feature: chat-4/threads
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Create a thread
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends thread "Thread 1" with message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 0            | 0             | 0                   | Message 1    | NULL        |

  Scenario: Create thread and reply
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends thread "Thread 1" with message "Message 1" to room "room" with 201
    And user "participant2" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 1            |  Message 1-1  | 0                   | Message 1    | Message 1-1 |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1-1 | []                | Message 1     |
      | room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                        | silent | messageParameters |
      | room | users         | participant1 | thread_created       | You created thread {title} | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"title":{"type":"highlight","id":THREAD_ID(Thread 1),"name":"Thread 1"}} |
      | room | users         | participant1 | user_added           | You added {user}               | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | You created the conversation   | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And user "participant1" renames thread "Thread 1" to "Thredited 1" in room "room" with 200
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title     | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thredited 1 | 1            | Message 1-1   | 0                   | Message 1    | Message 1-1 |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1-1 | []                | Message 1     |
      | room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                        | silent | messageParameters |
      | room | users         | participant1 | thread_renamed       | {actor} renamed thread {title} | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"title":{"type":"highlight","id":THREAD_ID(Thread 1),"name":"Thredited 1"}} |
      | room | users         | participant1 | thread_created       | {actor} created thread {title} | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"title":{"type":"highlight","id":THREAD_ID(Thread 1),"name":"Thread 1"}} |
      | room | users         | participant1 | user_added           | {actor} added you              | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | {actor} created the conversation | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Thread titles are trimmed
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends thread "More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789" with message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 … | 0            | 0          | 0                   | Message 1    | NULL        |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                        | silent | messageParameters |
      | room | users         | participant1 | thread_created       | You created thread {title}     | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"title":{"type":"highlight","id":THREAD_ID(More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789),"name":"More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 \u2026"}} |
      | room | users         | participant1 | user_added           | You added {user}               | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | You created the conversation   | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    And user "participant1" renames thread "More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789" to "Still more than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789" in room "room" with 200
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title     | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Still more than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 1234… | 0            | 0             | 0                   | Message 1    | NULL        |
    Then user "participant2" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                        | silent | messageParameters |
      | room | users         | participant1 | thread_renamed       | {actor} renamed thread {title} | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"title":{"type":"highlight","id":THREAD_ID(More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789),"name":"Still more than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 1234\u2026"}} |
      | room | users         | participant1 | thread_created       | {actor} created thread {title} | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"title":{"type":"highlight","id":THREAD_ID(More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789),"name":"More than 200 chars 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456789 \u2026"}} |
      | room | users         | participant1 | user_added           | {actor} added you              | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | {actor} created the conversation | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |

  Scenario: Recent threads are sorted by last activity
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends thread "Thread 1" with message "Message 1" to room "room" with 201
    And user "participant1" sends thread "Thread 2" with message "Message 2" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 2 | Thread 2 | 0            | 0             | 0                   | Message 2    | NULL        |
      | Message 1 | Thread 1 | 0            | 0             | 0                   | Message 1    | NULL        |
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 1            | Message 1-1   | 0                   | Message 1    | Message 1-1 |
      | Message 2 | Thread 2 | 0            | 0             | 0                   | Message 2    | NULL        |

  Scenario: Change notification setting for thread
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends thread "Thread 1" with message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 0            | 0             | 0                   | Message 1    | NULL        |
    And user "participant1" subscribes to thread "Message 1" in room "room" with notification level 1 with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 0            | 0             | 1                   | Message 1    | NULL        |
    And user "participant1" subscribes to thread "Message 1" in room "room" with notification level 2 with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 0            | 0             | 2                   | Message 1    | NULL        |
    And user "participant1" subscribes to thread "Message 1" in room "room" with notification level 3 with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 0            | 0             | 3                   | Message 1    | NULL        |
    And user "participant1" subscribes to thread "Message 1" in room "room" with notification level 4 with 400
    And user "participant1" subscribes to thread "Message 1" in room "room" with notification level 0 with 200
      | t.id      | t.title  | t.numReplies | t.lastMessage | a.notificationLevel | firstMessage | lastMessage |
      | Message 1 | Thread 1 | 0            | 0             | 0                   | Message 1    | NULL        |
