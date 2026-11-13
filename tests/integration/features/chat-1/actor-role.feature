Feature: chat-1/actor-role
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Role of the author is stored at the time of posting
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant1" sends message "Owner" to room "room" with 201
    And user "participant2" sends message "Moderator" to room "room" with 201
    And user "participant3" sends message "User" to room "room" with 201
    And user "participant1" shares "welcome.txt" with room "room"
    And user "participant1" demotes "participant2" in room "room" with 200 (v4)
    And user "participant2" sends message "Demoted" to room "room" with 201
    Then user "participant3" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters | metaData.actorRole |
      | room | users     | participant2 | participant2-displayname | Demoted   | []                | UNSET              |
      | room | users     | participant1 | participant1-displayname | {file}    | "IGNORE"          | owner              |
      | room | users     | participant3 | participant3-displayname | User      | []                | UNSET              |
      | room | users     | participant2 | participant2-displayname | Moderator | []                | moderator          |
      | room | users     | participant1 | participant1-displayname | Owner     | []                | owner              |

  Scenario: No role is stored in one-to-one conversations
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message   | messageParameters | metaData.actorRole |
      | one-to-one room | users     | participant1 | participant1-displayname | Message 1 | []                | UNSET              |
