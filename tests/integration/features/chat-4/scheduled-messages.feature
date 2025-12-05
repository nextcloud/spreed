Feature: chat-4/scheduling
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" sends message "Message" to room "room" with 201

  Scenario: Schedule a message
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | false                |
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | 1985514582 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | 1985514582  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |

  Scenario: Schedule a silent message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 2  |
      | sendAt  | 1985514582 |
      | silent  | true       |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | 1985514582  | true   |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |

  Scenario: Schedule a message reply
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 3  |
      | sendAt  | 1985514582 |
      | replyTo | Message    |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent  | message  | messageType | sendAt      | silent |
      | Message 3 | users     | participant1 | 0        | Message |Message 3 | comment     | 1985514582  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |

  Scenario: Schedule a thread
    When user "participant1" schedules a message to room "room" with 201
      | message     | Message 4        |
      | sendAt      | 1985514582       |
      | threadTitle | Scheduled Thread |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | threadTitle      | parent | message   | messageType | sendAt      | silent |
      | Message 4 | users     | participant1 | -1       | Scheduled Thread | null   | Message 4 | comment     | 1985514582  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |

  Scenario: Schedule a thread reply
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 5 |
      | sendAt | 1985514582 |
      | threadId | Thread 1 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 5 | users     | participant1 | Thread 1 | null   | Message 5 | comment     | 1985514582  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |

  Scenario: Schedule a quoted thread reply
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message  | Message 5  |
      | sendAt   | 1985514582 |
      | replyTo  | Message 0  |
      | threadId | Thread 1   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent    | message   | messageType | sendAt      | silent |
      | Message 5 | users     | participant1 | Thread 1 | Message 0 | Message 5 | comment     | 1985514582  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |

  Scenario: Schedule two messages and delete the first
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | 1985514582 |
      | silent  | false      |
    When user "participant1" schedules a message to room "room" with 201
      | message |Message 2  |
      | sendAt  |1985514584 |
      | silent  | true      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | 1985514582  | false  |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | 1985514584  | true   |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |
    When user "participant1" deletes scheduled message "Message 1" from room "room" with 200
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 2 | users     | participant1 | 0        | null   | Message 2 | comment     | 1985514584  | true   |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |
    When user "participant1" deletes scheduled message "Message 2" from room "room" with 200
    Then user "participant1" sees the following scheduled messages in room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | false                 |

  Scenario: edit a scheduled message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | 1985514582 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | actorType | actorId      | threadId | parent | message   | messageType | sendAt      | silent |
      | Message 1 | users     | participant1 | 0        | null   | Message 1 | comment     | 1985514582  | false  |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 202
      | message | Message 1 edited |
      | sendAt  | 1985514582       |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | hasScheduledMessages |
      | room | 2    | true                 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | actorType | actorId      | threadId | parent | message          | messageType | sendAt      | silent |
      | Message 1 edited | users     | participant1 | 0        | null   | Message 1 edited | comment     | 1985514582  | false  |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 202
      | message | Message 1 edited |
      | sendAt  | 1985514582       |
      | silent  | true             |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | actorType | actorId      | threadId | parent | message          | messageType | sendAt      | silent |
      | Message 1 edited | users     | participant1 | 0        | null   | Message 1 edited | comment     | 1985514582  | true   |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 400
      | message     | Message 1 edited |
      | sendAt      | 1985514582       |
      | threadTitle | Abcd             |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | actorType | actorId      | threadId | parent | message          | messageType | sendAt      | silent |
      | Message 1 edited | users     | participant1 | 0        | null   | Message 1 edited | comment     | 1985514582  | true   |
