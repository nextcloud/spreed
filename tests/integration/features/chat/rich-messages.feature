Feature: chat/public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: message without enrichable references has empty parameters
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    When user "participant1" sends message "Message without enrichable references" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                               | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Message without enrichable references | []                |

  Scenario: message with mention to valid user has mention parameter
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    When user "participant1" sends message "Mention to @participant2" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                    | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Mention to {mention-user1} | {"mention-user1":{"type":"user","id":"participant2","name":"participant2-displayname"}} |

  Scenario: message with mention to invalid user has mention parameter
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    When user "participant1" sends message "Mention to @unknownUser" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                    | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Mention to {mention-user1} | {"mention-user1":{"type":"user","id":"unknownUser","name":"Unknown user"}} |

  Scenario: message with duplicated mention has single mention parameter
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    When user "participant1" sends message "Mention to @participant2 and @participant2 again" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                                              | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Mention to {mention-user1} and {mention-user1} again | {"mention-user1":{"type":"user","id":"participant2","name":"participant2-displayname"}} |

  Scenario: message with mentions to several users has mention parameters
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    When user "participant1" sends message "Mention to @participant2, @unknownUser, @participant2 again and @participant3" to room "public room" with 201
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                                                                                | messageParameters |
      | public room | users     | participant1 | participant1-displayname | Mention to {mention-user1}, {mention-user2}, {mention-user1} again and {mention-user3} | {"mention-user1":{"type":"user","id":"participant2","name":"participant2-displayname"},"mention-user2":{"type":"user","id":"unknownUser","name":"Unknown user"},"mention-user3":{"type":"user","id":"participant3","name":"participant3-displayname"}} |
