Feature: chat-2/unread-messages
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: sending a message clears unread counter for sender
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "group room" with 201
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |

  Scenario: sending several messages clears unread counter for sender
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" sends message "Message 3" to room "group room" with 201
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 3              |

  Scenario: sending a message with previously unread messages clears unread counter for sender
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    And user "participant2" sends message "Message 2" to room "group room" with 201
    When user "participant1" sends message "Message 3" to room "group room" with 201
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |



  Scenario: reading all messages clears unread counter for reader
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" sends message "Message 3" to room "group room" with 201
    When user "participant2" reads message "Message 3" in room "group room" with 200
    Then user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |

  Scenario: reading some messages reduces unread counter for reader
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" sends message "Message 3" to room "group room" with 201
    When user "participant2" reads message "Message 2" in room "group room" with 200
    Then user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |
    When user "participant2" reads message "NULL" in room "group room" with 200
    Then user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |



  Scenario: replies are taken into account in unread counter
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 2              |

  Scenario: rich object messages are taken into account in unread counter
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "group room" with 201 (v1)
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |

  Scenario: shared file messages are taken into account in unread counter
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "group room"
    # Unread counter for sender is cleared in this case, as it is not
    # possible to know whether the file was shared from Talk, which could clear
    # the counter, or from the Files app, which should not clear it.
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |



  Scenario: system messages are not taken into account in unread counter
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" makes room "group room" private with 200 (v4)
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 0              |



  Scenario: marking conversation as unread marks last message as unread
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" sends message "Message 3" to room "group room" with 201
    Then user "participant2" is participant of the following rooms (v4)
      | id         | unreadMessages |
      | group room | 3              |
    And wait for 2 seconds
    Then user "participant2" is participant of the following modified-since rooms (v4)
    And user "participant2" reads message "Message 3" in room "group room" with 200
    And wait for 2 seconds
    Then user "participant2" is participant of the following modified-since rooms (v4)
      | id         | unreadMessages |
      | group room | 0              |
    Then user "participant2" is participant of the following modified-since rooms (v4)
    When user "participant1" marks room "group room" as unread with 200
    And user "participant2" marks room "group room" as unread with 200
    And wait for 2 seconds
    Then user "participant2" is participant of the following modified-since rooms (v4)
      | id         | unreadMessages |
      | group room | 1              |
    Then user "participant2" is participant of the following modified-since rooms (v4)
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |
    And user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |

  Scenario: marking conversation as unread marks last message as unread ignoring system messages
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" sends message "Message 3" to room "group room" with 201
    And user "participant1" makes room "group room" public with 200 (v4)
    And user "participant1" makes room "group room" private with 200 (v4)
    And user "participant1" makes room "group room" public with 200 (v4)
    When user "participant1" marks room "group room" as unread with 200
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |

  Scenario: marking conversation as unread marks last message as unread even if there are other unread messages
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" sends message "Message 3" to room "group room" with 201
    And user "participant1" sends message "Message 4" to room "group room" with 201
    And user "participant2" reads message "Message 1" in room "group room" with 200
    When user "participant2" marks room "group room" as unread with 200
    Then user "participant2" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |

  Scenario: marking conversation as unread marks last message as unread considering shared file
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    And user "participant1" shares "welcome.txt" with room "group room"
    When user "participant1" marks room "group room" as unread with 200
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |
    When user "participant1" sends message "Message 3" to room "group room" with 201
    And user "participant1" marks room "group room" as unread with 200
    Then user "participant1" is participant of room "group room" (v4)
      | unreadMessages |
      | 1              |

  Scenario: marking first message as unread falls back to system message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                      | silent | messageParameters |
      | room | users         | participant1 | user_added           | You added {user}             | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | unreadMessages | lastReadMessage      |
      | room | 1              | conversation_created |
    And wait for 1 seconds
    And user "participant2" marks room "room" as unread with 200
    Then user "participant2" is participant of room "room" (v4)
      | unreadMessages | lastReadMessage |
      | 1              | user_added      |

  Scenario: marking first message as unread falls back to FIRST_MESSAGE_UNREAD
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                      | silent | messageParameters |
      | room | users         | participant1 | conversation_created | You created the conversation | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | unreadMessages | lastReadMessage      |
      | room | 0              | conversation_created |
    And wait for 1 seconds
    And user "participant1" marks room "room" as unread with 200
    Then user "participant1" is participant of room "room" (v4)
      | unreadMessages | lastReadMessage      |
      | 1              | FIRST_MESSAGE_UNREAD |
