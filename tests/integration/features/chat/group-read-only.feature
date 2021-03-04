Feature: chat/group-read-only
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"

  Scenario: owner can send and receive chat messages to and from group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    When user "participant1" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |
    When user "participant1" locks room "group room" with 200 (v4)
    When user "participant1" sends message "Message 2" to room "group room" with 403
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |
    When user "participant1" unlocks room "group room" with 200 (v4)
    When user "participant1" sends message "Message 3" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Message 3 | []                |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |

  Scenario: invited user can send and receive chat messages to and from group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    When user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant2 | participant2-displayname | Message 1 | []                |
    When user "participant1" locks room "group room" with 200 (v4)
    When user "participant2" sends message "Message 2" to room "group room" with 403
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant2 | participant2-displayname | Message 1 | []                |
    When user "participant1" unlocks room "group room" with 200 (v4)
    When user "participant2" sends message "Message 3" to room "group room" with 201
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant2 | participant2-displayname | Message 3 | []                |
      | group room | users     | participant2 | participant2-displayname | Message 1 | []                |
