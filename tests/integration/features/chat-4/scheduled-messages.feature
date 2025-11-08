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
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | 1985514582 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                         |
      | Message 1 | room   | users     | participant1 | 0        | null     | Message 1 | comment     | 1985514582  | {"thread_title":"","silent":false,"thread_id":0} |

  Scenario: Schedule a silent message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 2  |
      | sendAt  | 1985514582 |
      | silent  | true       |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                        |
      | Message 2 | room   | users     | participant1 | 0        | null     | Message 2 | comment     | 1985514582  | {"thread_title":"","silent":true,"thread_id":0} |

  Scenario: Schedule a message reply
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 3  |
      | sendAt  | 1985514582 |
      | replyTo | Message    |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | parent  | message  | messageType | sendAt      | metaData                                         |
      | Message 3 | room   | users     | participant1 | 0        | Message  | Message |Message 3 | comment     | 1985514582  | {"thread_title":"","silent":false,"thread_id":0} |

  Scenario: Schedule a thread
    When user "participant1" schedules a message to room "room" with 201
      | message     | Message 4        |
      | sendAt      | 1985514582       |
      | threadTitle | Scheduled Thread |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                                          |
      | Message 4 | room   | users     | participant1 | -1       | null     | Message 4 | comment     | 1985514582  | {"thread_title":"Scheduled Thread","silent":false,"thread_id":-1} |

  Scenario: Schedule a thread reply
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 5 |
      | sendAt | 1985514582 |
      | threadId | Thread 1 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                                          |
      | Message 5 | room   | users     | participant1 | Thread 1 | null     | Message 5 | comment     | 1985514582  | {"thread_title":"Thread 1","silent":false,"thread_id":"Thread 1"} |

  Scenario: Schedule a quoted thread reply
    Given user "participant1" sends thread "Thread 1" with message "Message 0" to room "room" with 201
    When user "participant1" schedules a message to room "room" with 201
      | message  | Message 5  |
      | sendAt   | 1985514582 |
      | replyTo  | Message 0  |
      | threadId | Thread 1   |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId  | parent    | message   | messageType | sendAt      | metaData                                                          |
      | Message 5 | room   | users     | participant1 | Thread 1 | Message 0 | Message 0 | Message 5 | comment     | 1985514582  | {"thread_title":"Thread 1","silent":false,"thread_id":"Thread 1"} |

  Scenario: Schedule two messages and delete the first
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | 1985514582 |
      | silent  | false      |
    When user "participant1" schedules a message to room "room" with 201
      | message |Message 2  |
      | sendAt  |1985514582 |
      | silent  | true      |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                         |
      | Message 1 | room   | users     | participant1 | 0        | null     | Message 1 | comment     | 1985514582  | {"thread_title":"","silent":false,"thread_id":0} |
      | Message 2 | room   | users     | participant1 | 0        | null     | Message 2 | comment     | 1985514582  | {"thread_title":"","silent":true,"thread_id":0}  |
    When user "participant1" deletes scheduled message "Message 1" from room "room" with 200
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                        |
      | Message 2 | room   | users     | participant1 | 0        | null     | Message 2 | comment     | 1985514582  | {"thread_title":"","silent":true,"thread_id":0} |

  Scenario: edit a scheduled message
    When user "participant1" schedules a message to room "room" with 201
      | message | Message 1  |
      | sendAt  | 1985514582 |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id        | roomId | actorType | actorId      | threadId | parentId | message   | messageType | sendAt      | metaData                                         |
      | Message 1 | room   | users     | participant1 | 0        | null     | Message 1 | comment     | 1985514582  | {"thread_title":"","silent":false,"thread_id":0} |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 202
      | message | Message 1 edited |
      | sendAt  | 1985514582       |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | roomId | actorType | actorId      | threadId | parentId | message          | messageType | sendAt      | metaData                                                              |
      | Message 1 edited | room   | users     | participant1 | 0        | null     | Message 1 edited | comment     | 1985514582  | {"thread_title":"","silent":false,"thread_id":0,"last_edited_time":0} |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 202
      | message | Message 1 edited |
      | sendAt  | 1985514582       |
      | silent  | true             |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | roomId | actorType | actorId      | threadId | parentId | message          | messageType | sendAt      | metaData                                                             |
      | Message 1 edited | room   | users     | participant1 | 0        | null     | Message 1 edited | comment     | 1985514582  | {"thread_title":"","silent":true,"thread_id":0,"last_edited_time":0} |
    When user "participant1" updates scheduled message "Message 1" in room "room" with 400
      | message     | Message 1 edited |
      | sendAt      | 1985514582       |
      | threadTitle | Abcd             |
    Then user "participant1" sees the following scheduled messages in room "room" with 200
      | id               | roomId | actorType | actorId      | threadId | parentId | message          | messageType | sendAt      | metaData                                                             |
      | Message 1 edited | room   | users     | participant1 | 0        | null     | Message 1 edited | comment     | 1985514582  | {"thread_title":"","silent":true,"thread_id":0,"last_edited_time":0} |
