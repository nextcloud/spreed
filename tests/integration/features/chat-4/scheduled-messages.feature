Feature: chat-4/scheduling
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" sends message "Message" to room "room" with 201
    Then user "participant1" reads message "Message" in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages | unreadMessages | lastReadMessage |
      | room | 2    | 0                    | 0              | Message         |

  Scenario: Schedule a message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages | unreadMessages | lastReadMessage |
      | room | 2    | 1                    | 0              | Message         |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages | unreadMessages | lastReadMessage |
      | room | 2    | 0                    | 1              | Message         |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | comment     | Message 1 | []                |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |

  Scenario: Schedule a message mentioning another user
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 @participant2 |
      | sendAt  | {NOW}                   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id                      | actorType | actorId      | threadId | parent | message                 | messageType | sendAt      | silent |
      | Message 1 @participant2 | users     | participant1 | 0        | null   | Message 1 @participant2 | comment     | {NOW}  | false  |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message                   | messageParameters                                                                                                   |
      | room | users     | participant1 | participant1-displayname | comment     | Message 1 {mention-user1} | {"mention-user1":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users     | participant2 | participant2-displayname | comment     | Message                   | []                                                                                                                  |

  Scenario: Schedule a silent message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 2 |
      | sendAt  | {NOW}     |
      | silent  | true      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | {NOW}  | true   |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | comment     | Message 2 | []                |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |

  Scenario: Schedule a message reply
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 3 |
      | sendAt  | {NOW}     |
      | replyTo | Message   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent  | message   | messageType | sendAt | silent |
      | Message 3 | users     | participant1 | 0        | Message | Message 3 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | comment     | Message 3 | []                | Message       |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |               |

  Scenario: Schedule a message reply, but the parent message was deleted in the meantime
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 3 |
      | sendAt  | {NOW}     |
      | replyTo | Message   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent  | message   | messageType | sendAt | silent |
      | Message 3 | users     | participant1 | 0        | Message | Message 3 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When user "participant2" deletes message "Message" from room "room" with 200
    And wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent  | message   | messageType | sendAt | silent | originalSendAt |
      | Message 3 | users     | participant1 | 0        | Message | Message 3 | comment     | 0      | false  | {NOW}          |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | -1                   |

  Scenario: Schedule a thread
    When user "participant1" schedules a message to room "room" with 201
      | message     | Message 4        |
      | sendAt      | {NOW}            |
      | threadTitle | Scheduled Thread |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | threadTitle      | parent | message   | messageType | sendAt | silent |
      | Message 4 | users     | participant1 | -1       | Scheduled Thread | null   | Message 4 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters | threadTitle      | threadReplies |
      | room | users     | participant1 | participant1-displayname | comment     | Message 4 | []                | Scheduled Thread | 0             |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |                  |               |

  Scenario: Schedule a thread with a mention
    When user "participant1" schedules a message to room "room" with 201
      | message     | Message 4 @participant2 |
      | sendAt      | {NOW}                   |
      | threadTitle | Scheduled Thread        |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | threadTitle      | parent | message                 | messageType | sendAt | silent |
      | Message 4 | users     | participant1 | -1       | Scheduled Thread | null   | Message 4 @participant2 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message                   | messageParameters                                                                                                   | threadTitle      | threadReplies |
      | room | users     | participant1 | participant1-displayname | comment     | Message 4 {mention-user1} | {"mention-user1":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} | Scheduled Thread | 0             |
      | room | users     | participant2 | participant2-displayname | comment     | Message                   | []                                                                                                                  |                  |               |

  Scenario: Schedule a thread with empty thread title
    When user "participant1" schedules a message to room "room" with 400
      | message     | Message 4 |
      | sendAt      | {NOW}     |
      | threadTitle |           |
      | threadId    | -1        |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |

  Scenario: Schedule a thread reply
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 5 |
      | sendAt | {NOW}      |
      | threadId | Thread 1 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 5 | users     | participant1 | Thread 1 | null   | Message 5 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters |parentMessage | threadReplies |
      | room | users     | participant1 | participant1-displayname | comment     | Message 5 | []                | Message 0    | 1             |
      | room | users     | participant1 | participant1-displayname | comment     | Message 0 | []                |              | 1             |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |              |               |

  Scenario: Schedule a quoted thread reply
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message  | Message 5  |
      | sendAt   | {NOW} |
      | replyTo  | Message 0  |
      | threadId | Thread 1   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent    | message   | messageType | sendAt | silent |
      | Message 5 | users     | participant1 | Thread 1 | Message 0 | Message 5 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters | parentMessage | threadReplies |
      | room | users     | participant1 | participant1-displayname | comment     | Message 5 | []                | Message 0     | 1             |
      | room | users     | participant1 | participant1-displayname | comment     | Message 0 | []                |               | 1             |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |               |               |

  Scenario: Schedule a quoted thread reply, but the replied to thread message was deleted in the meantime
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    And user "participant2" sends reply "Thread 1-1" on thread "Thread 1" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message  | Message 5  |
      | sendAt   | {NOW}      |
      | replyTo  | Thread 1-1 |
      | threadId | Thread 1   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent     | message   | messageType | sendAt | silent |
      | Message 5 | users     | participant1 | Thread 1 | Thread 1-1 | Message 5 | comment     | {NOW}  | false  |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When user "participant2" deletes message "Thread 1-1" from room "room" with 200
    And wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent     | message   | messageType | sendAt | silent | originalSendAt |
      | Message 5 | users     | participant1 | Thread 1 | Thread 1-1 | Message 5 | comment     | 0      | false  | {NOW}          |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | -1                   |

  Scenario: Schedule messages and delete them again
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
      | silent  | false     |
    And user "participant1" schedules a message to room "room" with 201
      | message | Message 2 |
      | sendAt  | {NOW}     |
      | silent  | true      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | {NOW}  | true   |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 2                    |
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 3 |
      | sendAt  | {NOW}     |
      | silent  | true      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | {NOW}  | true   |
      | Message 3 | users     | participant1 | 0        | null   | Message 3 | comment     | {NOW}  | true   |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 3                    |
    When user "participant1" deletes scheduled message "Message 1" from room "room" with 200
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | {NOW}  | true   |
      | Message 3 | users     | participant1 | 0        | null   | Message 3 | comment     | {NOW}  | true   |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 2                    |
    When user "participant1" deletes scheduled message "Message 2" from room "room" with 200
    When user "participant1" deletes scheduled message "Message 3" from room "room" with 200
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |

  Scenario: Schedule messages and try to delete the scheduled message from a different account
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
      | silent  | false     |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    When user "participant2" deletes scheduled message "Message 1" from room "room" with 404
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |

  Scenario: Schedule two messages where one sends now and one is still in the queue
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
      | silent  | false     |
    And user "participant1" schedules a message to room "room" with 201
      | message | Message 2 |
      | sendAt  | {FUTURE}  |
      | silent  | true      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt   | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}    | false  |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | {FUTURE} | true   |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 2                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt   | silent |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | {FUTURE} | true   |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | comment     | Message 1 | []                |
      | room | users     | participant2 | participant2-displayname | comment     | Message   | []                |

  Scenario: Edit a scheduled message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | {NOW} |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 202
      | message | Message 1 edited |
      | sendAt  | {NOW}       |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | actorType | actorId      | threadId | parent | message          | messageType | sendAt | silent |
      | Message 1 edited | users     | participant1 | 0        | null   | Message 1 edited | comment     | {NOW}  | false  |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 202
      | message | Message 1 edited |
      | sendAt  | {NOW}       |
      | silent  | true             |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | actorType | actorId      | threadId | parent | message          | messageType | sendAt | silent |
      | Message 1 edited | users     | participant1 | 0        | null   | Message 1 edited | comment     | {NOW}  | true   |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 400
      | message     | Message 1 edited |
      | sendAt      | {NOW}       |
      | threadTitle | Abcd             |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | actorType | actorId      | threadId | parent | message          | messageType | sendAt | silent |
      | Message 1 edited | users     | participant1 | 0        | null   | Message 1 edited | comment     | {NOW}  | true   |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message          | messageParameters |
      | room | users     | participant1 | participant1-displayname | comment     | Message 1 edited | []                |
      | room | users     | participant2 | participant2-displayname | comment     | Message          | []                |

  Scenario: Try to schedule a message in the past
    When user "participant1" schedules a message to room "room" with 400
      | message | Message 1 |
      | sendAt  | {PAST}    |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |

  Scenario: Try to schedule a message to a public room as self joined user
    Given user "participant1" creates room "public" (v4)
      | roomType | 3      |
      | roomName | public |
    And user "participant2" joins room "public" with 200 (v4)
    And user "participant2" schedules a message to room "public" with 404
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant2" sees the following scheduled messages in room "public" with 404
    Then user "participant2" is participant of the following unordered rooms (v4)
      | name   | type | hasScheduledMessages |
      | room   | 2    | 0                    |
      | public | 3    | 0                    |

  Scenario: Try to schedule a message to a room as a removed user
    When user "participant1" removes "participant2" from room "room" with 200 (v4)
    And user "participant2" schedules a message to room "room" with 404
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant2" is participant of the following rooms (v4)
    Then user "participant2" sees the following scheduled messages in room "room" with 404

  Scenario: Schedule a message to a room mentioning a removed user
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 @participant2 |
      | sendAt  | {NOW}                   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id                      | actorType | actorId      | threadId | parent | message                 | messageType | sendAt      | silent |
      | Message 1 @participant2 | users     | participant1 | 0        | null   | Message 1 @participant2 | comment     | {NOW}  | false  |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When user "participant1" removes "participant2" from room "room" with 200 (v4)
    And wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message                   | messageParameters                                                                                                   |
      | room | users     | participant1 | participant1-displayname | comment     | Message 1 {mention-user1} | {"mention-user1":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users     | participant2 | participant2-displayname | comment     | Message                   | []                                                                                                                  |


  Scenario: Try to schedule a message with an empty title
    When user "participant2" schedules a message to room "room" with 400
      | message |           |
      | sendAt  | {NOW}     |
    Then user "participant2" sees the following scheduled messages in room "room" with 200

  Scenario: Try to schedule a reply to a system message
    When user "participant1" sets description for room "room" to "New description" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | description_set      |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" schedules a message to room "room" with 400
      | message | Message 3       |
      | sendAt  | {NOW}           |
      | replyTo | description_set |
    Then user "participant1" sees the following scheduled messages in room "room" with 200

  Scenario: Schedule a message to a public room
    Given user "participant1" creates room "public" (v4)
      | roomType | 3      |
      | roomName | public |
    And user "participant2" joins room "public" with 200 (v4)
    And user "participant1" schedules a message to room "public" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant1" sees the following scheduled messages in room "public" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following unordered rooms (v4)
      | name   | type | hasScheduledMessages |
      | room   | 2    | 0                    |
      | public | 3    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant2" sees the following messages in room "public" with 200
      | room   | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters |
      | public | users     | participant1 | participant1-displayname | comment     | Message 1 | []                |

  Scenario: Schedule a message to a one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" schedules a message to room "one-to-one room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant1" sees the following scheduled messages in room "one-to-one room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following unordered rooms (v4)
      | name         | type | hasScheduledMessages |
      | room         | 2    | 0                    |
      | participant2 | 1    | 1                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | messageType | message   | messageParameters |
      | one-to-one room | users     | participant1 | participant1-displayname | comment     | Message 1 | []                |

  Scenario: Schedule a message to a one-to-one room and remove the other participant
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" schedules a message to room "one-to-one room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant1" sees the following scheduled messages in room "one-to-one room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    Then user "participant1" is participant of the following unordered rooms (v4)
      | name         | type | hasScheduledMessages |
      | room         | 2    | 0                    |
      | participant2 | 1    | 1                    |
    When user "participant2" is deleted
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" sees the following messages in room "one-to-one room" with 200

  Scenario: Schedule a reply, but the room was locked in the meantime
    When user "participant2" schedules a message to room "room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant2" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent |
      | Message 1 | users     | participant2 | 0        | null   | Message 1 | comment     | {NOW}  | false  |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When user "participant1" locks room "room" with 200 (v4)
    And wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant2" sees the following scheduled messages in room "room" with 403
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | -1                   |

  Scenario: Schedule a reply, but the account's chat permissions have been revoked in the meantime
    When user "participant2" schedules a message to room "room" with 201
      | message | Message 1 |
      | sendAt  | {NOW}     |
    Then user "participant2" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent | originalSendAt |
      | Message 1 | users     | participant2 | 0        | null   | Message 1 | comment     | {NOW}  | false  | UNSET          |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 1                    |
    When user "participant1" sets permissions for "participant2" in room "room" to "C" with 200 (v4)
    And wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant2" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt | silent | originalSendAt |
      | Message 1 | users     | participant2 | 0        | null   | Message 1 | comment     | 0      | false  | {NOW}          |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | -1                   |

  Scenario: Schedule a message mentioning a group
    Given group "group" exists
    And set display name of group "group" to "group-displayname"
    Given User "participant1" creates team "team"
    And user "participant1" adds group "group" to room "room" with 200 (v4)
    And user "participant1" adds team "team" to room "room" with 200 (v4)
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1 @"group/group" |
      | sendAt  | {NOW}                    |
    And user "participant1" schedules a message to room "room" with 201
      | message | Message 2 @"TEAM_ID(team)" |
      | sendAt  | {NOW}                      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id                         | actorType | actorId      | threadId | parent | message                    | messageType | sendAt | silent |
      | Message 1 @"group/group"   | users     | participant1 | 0        | null   | Message 1 @"group/group"   | comment     | {NOW}  | false  |
      | Message 2 @"TEAM_ID(team)" | users     | participant1 | 0        | null   | Message 2 @"TEAM_ID(team)" | comment     | {NOW}  | false  |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 2                    |
    When wait for 4 seconds
    And force run "OCA\Talk\BackgroundJob\SendScheduledMessages" background jobs
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | 0                    |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | messageType | message                    | messageParameters                                                                                                   |
      | room | users     | participant1 | participant1-displayname | comment     | Message 2 {mention-team1}  | {"mention-team1":{"type":"circle","id":"TEAM_ID(team)","name":"team","link":"","mention-id":"team\/TEAM_ID(team)"}} |
      | room | users     | participant1 | participant1-displayname | comment     | Message 1 {mention-group1} | {"mention-group1":{"type":"user-group","id":"group","name":"group-displayname","mention-id":"group\/group"}}        |
      | room | users     | participant2 | participant2-displayname | comment     | Message                    | []                                                                                                                  |
