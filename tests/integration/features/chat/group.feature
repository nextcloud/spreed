Feature: chat/group
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

  Scenario: invited user can send and receive chat messages to and from group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    When user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant2 | participant2-displayname | Message 1 | []                |

  Scenario: invited user can not send without chat permissions
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "group room" to "CSJLAVP" with 200 (v4)
    When user "participant2" sends message "Message 1" to room "group room" with 403
    When user "participant1" sends message "Message 2" to room "group room" with 201
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Message 2 | []                |

  Scenario: not invited user can not send nor receive chat messages to nor from group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    When user "participant3" sends message "Message 1" to room "group room" with 404
    And user "participant1" sends message "Message 2" to room "group room" with 201
    Then user "participant3" sees the following messages in room "group room" with 404

  Scenario: guest can not send nor receive chat messages to nor from group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "guest" joins call "group room" with 404 (v4)
    When user "guest" sends message "Message 1" to room "group room" with 404
    And user "participant1" sends message "Message 2" to room "group room" with 201
    Then user "guest" sees the following messages in room "group room" with 404

  Scenario: everyone in a group room can receive messages from everyone in that room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    When user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant2" sends message "Message 2" to room "group room" with 201
    And user "participant2" sends message "" to room "group room" with 400
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | group room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | group room | users     | participant1 | participant1-displayname | Message 1 | []                |
