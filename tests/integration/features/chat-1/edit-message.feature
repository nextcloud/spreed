Feature: chat-1/edit-message
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Moderator edits their own message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |
    And user "participant2" edits message "Message 1 - Edit 1" in room "room" to "Message 1 - Edit 2" with 403
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant1 | participant1-displayname | Message 1 - Edit 1 | []                |               |

  Scenario: User and moderator edit user message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "room" with 201
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 1 | []                |               |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 1 | []                |               |
    And user "participant2" edits message "Message 1 - Edit 1" in room "room" to "Message 1 - Edit 2" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2 | []                |               |
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2 | []                |               |
    And user "participant2" edits message "Message 1 - Edit 1" in room "room" to "" with 400
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters | parentMessage |
      | room | users     | participant2 | participant2-displayname | Message 1 - Edit 2 | []                |               |

  Scenario: Editing a caption
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" shares "welcome.txt" with room "room"
      | talkMetaData | {"caption":"Caption 1"} |
    And user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant1 | participant1-displayname | Caption 1 | "IGNORE"          |
    When user "participant1" edits message "Caption 1" in room "room" to "Caption 1 - Edit 1" with 200
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters |
      | room | users     | participant1 | participant1-displayname | Caption 1 - Edit 1 | "IGNORE"          |
    When user "participant1" edits message "Caption 1" in room "room" to "" with 400
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message            | messageParameters |
      | room | users     | participant1 | participant1-displayname | Caption 1 - Edit 1 | "IGNORE"          |
