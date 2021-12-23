Feature: reaction/react
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
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1}  |
    And user "participant1" react with "👍" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":2}  |

  Scenario: React two times to same message with the same reaction
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 409
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1}  |

  Scenario: Delete reaction to message with success
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | {"👍":1}  |
    And user "participant2" delete react with "👍" on message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | reactions |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                | []        |
