Feature: chat/password
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: owner can send and receive chat messages to and from public password protected room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "public password protected room" with 201
    Then user "participant1" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | users     | participant1 | participant1-displayname | Message 1 | []                |

  Scenario: invited user can send and receive chat messages to and from public password protected room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "public password protected room" with 200 (v4)
    When user "participant2" sends message "Message 1" to room "public password protected room" with 201
    Then user "participant2" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | users     | participant2 | participant2-displayname | Message 1 | []                |

  Scenario: invited user can send and receive chat messages to and from public password protected room with initial password
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
      | password | ARoomPassword123. |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "public password protected room" with 200 (v4)
    When user "participant2" sends message "Message 1" to room "public password protected room" with 201
    Then user "participant2" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | users     | participant2 | participant2-displayname | Message 1 | []                |

  Scenario: not invited but joined with password user can send and receive chat messages to and from public password protected room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    And user "participant3" joins room "public password protected room" with 200 (v4)
      | password | foobar |
    When user "participant3" sends message "Message 1" to room "public password protected room" with 201
    Then user "participant3" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | users     | participant3 | participant3-displayname | Message 1 | []                |

  Scenario: not invited user can not send nor receive chat messages to and from public password protected room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    When user "participant3" sends message "Message 1" to room "public password protected room" with 404
    And user "participant1" sends message "Message 2" to room "public password protected room" with 201
    Then user "participant3" sees the following messages in room "public password protected room" with 404

  Scenario: joined with password guest can send and receive chat messages to and from public password protected room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    And user "guest" joins room "public password protected room" with 200 (v4)
      | password | foobar |
    When user "guest" sends message "Message 1" to room "public password protected room" with 201
    Then user "guest" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId | actorDisplayName | message   | messageParameters |
      | public password protected room | guests    | guest   |                  | Message 1 | []                |

  Scenario: not joined guest can not send nor receive chat messages to and from public password protected room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    When user "guest" sends message "Message 1" to room "public password protected room" with 404
    And user "participant1" sends message "Message 2" to room "public password protected room" with 201
    Then user "guest" sees the following messages in room "public password protected room" with 404

  Scenario: everyone in a public password protected room can receive messages from everyone in that room
    Given user "participant1" creates room "public password protected room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public password protected room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "public password protected room" with 200 (v4)
    And user "participant3" joins room "public password protected room" with 200 (v4)
      | password | foobar |
    And user "guest" joins room "public password protected room" with 200 (v4)
      | password | foobar |
    When user "participant1" sends message "Message 1" to room "public password protected room" with 201
    And user "participant2" sends message "Message 2" to room "public password protected room" with 201
    And user "participant3" sends message "Message 3" to room "public password protected room" with 201
    And user "guest" sends message "Message 4" to room "public password protected room" with 201
    Then user "participant1" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | guests    | guest        |                          | Message 4 | []                |
      | public password protected room | users     | participant3 | participant3-displayname | Message 3 | []                |
      | public password protected room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public password protected room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant2" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | guests    | guest        |                          | Message 4 | []                |
      | public password protected room | users     | participant3 | participant3-displayname | Message 3 | []                |
      | public password protected room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public password protected room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant3" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | guests    | guest        |                          | Message 4 | []                |
      | public password protected room | users     | participant3 | participant3-displayname | Message 3 | []                |
      | public password protected room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public password protected room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "guest" sees the following messages in room "public password protected room" with 200
      | room                           | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | public password protected room | guests    | guest        |                          | Message 4 | []                |
      | public password protected room | users     | participant3 | participant3-displayname | Message 3 | []                |
      | public password protected room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | public password protected room | users     | participant1 | participant1-displayname | Message 1 | []                |
