Feature: chat/one-to-one
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: owner can send and receive chat messages to and from one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | one-to-one room | users     | participant1 | participant1-displayname | Message 1 | []                |

  Scenario: invited user can send and receive chat messages to and from one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    When user "participant2" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | one-to-one room | users     | participant2 | participant2-displayname | Message 1 | []                |

  Scenario: not invited user can not send nor receive chat messages to nor from one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant3" sends message "Message 1" to room "one-to-one room" with 404
    And user "participant1" sends message "Message 2" to room "one-to-one room" with 201
    Then user "participant3" sees the following messages in room "one-to-one room" with 404

  Scenario: guest can not send nor receive chat messages to nor from one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "guest" joins call "one-to-one room" with 404 (v4)
    When user "guest" sends message "Message 1" to room "one-to-one room" with 404
    And user "participant1" sends message "Message 2" to room "one-to-one room" with 201
    Then user "guest" sees the following messages in room "one-to-one room" with 404

  Scenario: everyone in a one-to-one room can receive messages from everyone in that room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" sends message "Message 1" to room "one-to-one room" with 201
    And user "participant2" sends message "Message 2" to room "one-to-one room" with 201
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | one-to-one room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | one-to-one room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | one-to-one room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | one-to-one room | users     | participant1 | participant1-displayname | Message 1 | []                |

  Scenario: Sending a message into a one-to-one chat re-adds the participants
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" is participant of room "one-to-one room" (v4)
    And user "participant2" is participant of room "one-to-one room" (v4)
    When user "participant1" removes themselves from room "one-to-one room" with 200 (v4)
    Then user "participant1" is not participant of room "one-to-one room" (v4)
    When user "participant2" sends message "Message" to room "one-to-one room" with 201
    Then user "participant1" is participant of room "one-to-one room" (v4)
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | one-to-one room | users     | participant2 | participant2-displayname | Message   | []                |

  Scenario: Return user status when get single conversation
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant2" set status to "online" with 200 (v1)
    Then user "participant1" gets room "one-to-one room" with 200 (v4)
      | status | online |
    When user "participant2" set status to "offline" with 200 (v1)
    Then user "participant1" gets room "one-to-one room" with 200 (v4)
      | status | offline |
    Then user "participant2" set status to "away" with 200 (v1)
    Then user "participant1" gets room "one-to-one room" with 200 (v4)
      | status | away |
