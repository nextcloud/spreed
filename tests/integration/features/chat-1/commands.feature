Feature: chat/commands
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: user can see own help command and others can not
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" sends message "/help" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId | actorDisplayName | message                                    | messageParameters |
      | group room | bots      | talk    | talk-bot         | There are currently no commands available. | []                |
    And user "participant2" sees the following messages in room "group room" with 200

  Scenario: user can see own help command along with regular messages and others can not
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "/help" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message                                    | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Message 2                                  | []                |
      | group room | bots      | talk         | talk-bot                 | There are currently no commands available. | []                |
      | group room | users     | participant1 | participant1-displayname | Message 1                                  | []                |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Message 2 | []                |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |

  Scenario: double slash escapes a command for everyone
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" sends message "//help" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | group room | users     | participant1 | participant1-displayname | /help   | []                |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | group room | users     | participant1 | participant1-displayname | /help   | []                |
