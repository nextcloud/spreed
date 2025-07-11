Feature: chat-4/threads
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: A chat messages and reply are not a thread
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200
    And user "participant2" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following recent threads in room "room" with 200

  Scenario: Create thread on root message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" creates thread "Message 1" in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 1 | 0            | 0             | Message 1    | NULL        |

  Scenario: Create thread on root with reply
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    And user "participant1" creates thread "Message 1" in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 1 | 1            | Message 1-1   | Message 1    | Message 1-1 |

  Scenario: Create thread on reply
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    And user "participant1" creates thread "Message 1-1" in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 1 | 1            | Message 1-1   | Message 1    | Message 1-1 |

  Scenario: Creating a thread again does not conflict nor duplicate
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant1" creates thread "Message 1" in room "room" with 200
    When user "participant2" creates thread "Message 1" in room "room" with 200
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 1 | 0            | 0             | Message 1    | NULL        |
    Then user "participant2" sees the following recent threads in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 1 | 0            | 0             | Message 1    | NULL        |

  Scenario: Recent threads are sorted by last activity
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" sends message "Message 2" to room "room" with 201
    When user "participant2" creates thread "Message 2" in room "room" with 200
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 2 | 0            | 0             | Message 2    | NULL        |
    When user "participant1" creates thread "Message 1" in room "room" with 200
    Then user "participant1" sees the following recent threads in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 1 | 0            | 0             | Message 1    | NULL        |
      | Message 2 | 0            | 0             | Message 2    | NULL        |
    When user "participant1" sends reply "Message 2-1" on message "Message 2" to room "room" with 201
    Then user "participant2" sees the following recent threads in room "room" with 200
      | t.id      | t.numReplies | t.lastMessage | firstMessage | lastMessage |
      | Message 2 | 1            | Message 2-1   | Message 2    | Message 2-1 |
      | Message 1 | 0            | 0             | Message 1    | NULL        |
