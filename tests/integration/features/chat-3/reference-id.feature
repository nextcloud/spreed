Feature: chat-2/reference-id
  Background:
    Given user "participant1" exists

  Scenario: user can send a message with a reference id and see it afterwards
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" sends message "Message 1" with reference id "ref 1" to room "group room" with 201
    When user "participant1" sends message "Message 2" with reference id "ref 2" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters | referenceId |
      | group room | users     | participant1 | participant1-displayname | Message 2 | []                | ref 2       |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                | ref 1       |

  Scenario: user can send a message with the same reference id
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" sends message "Message 1" with reference id "ref 1" to room "group room" with 201
    When user "participant1" sends message "Message 2" with reference id "ref 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters | referenceId |
      | group room | users     | participant1 | participant1-displayname | Message 2 | []                | ref 1       |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                | ref 1       |

  Scenario: too long references dont break the api
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" sends message "Message 1" with reference id "f0a1611b73992b57a8533c7f618bbd145b17ef62238ece5bda548f47c76c02b2" to room "group room" with 201
    When user "participant1" sends message "Message 2" with reference id "too long ref is cut off f0a1611b73992b57a8533c7f618bbd145b17ef62238ece5bda548f47c76c02b2" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters | referenceId |
      | group room | users     | participant1 | participant1-displayname | Message 2 | []                | too long ref is cut off f0a1611b73992b57a8533c7f618bbd145b17ef62 |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                | f0a1611b73992b57a8533c7f618bbd145b17ef62238ece5bda548f47c76c02b2 |
