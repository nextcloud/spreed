Feature: conversation/delete-user

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  # There is no way to check that the room is deleted if the deleted user is the
  # last one in a one-to-one or group room.

  Scenario: delete user who is in a one-to-one room
    Given user "participant1" creates room "one-to-one room"
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" sends message "Message 1" to room "one-to-one room" with 201
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | one-to-one room | deleted_users | deleted_users |                  | Message 1 | []                |
    Then user "participant1" is participant of the following rooms (v3)
      | name                     | type     |
      | participant2-displayname | 2        |

  Scenario: delete user who left a one-to-one room
    Given user "participant1" creates room "one-to-one room" (v3)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" sends message "Message 1" to room "one-to-one room" with 201
    When user "participant2" leaves room "one-to-one room" with 200 (v3)
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | one-to-one room | deleted_users | deleted_users |                  | Message 1 | []                |
    Then user "participant1" is participant of the following rooms (v3)
      | name                     | type     |
      | participant2-displayname | 2        |

  Scenario: delete user who is in a group room
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | group room |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | group room | deleted_users | deleted_users |                  | Message 1 | []                |

  Scenario: delete user who is in a public room
    Given user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | public room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant2" sends message "Message 1" to room "public room" with 201
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | public room | deleted_users | deleted_users |                  | Message 1 | []                |

  Scenario: delete user who is the last participant in a public room
    Given user "participant2" creates room "public room"
      | roomType | 3 |
      | roomName | public room |
    When user "participant2" is deleted
    Then user "participant1" joins room "public room" with 404
