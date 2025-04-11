Feature: conversation/delete-user

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  # There is no way to check that the room is deleted if the deleted user is the
  # last one in a one-to-one or group room.

  Scenario: delete user who is in a one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant1" is participant of the following rooms (v4)
      | name         | type     | readOnly |
      | participant2 | 1        | 0        |
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | one-to-one room | deleted_users | deleted_users |                  | Message 1 | []                |
    Then user "participant1" is participant of the following rooms (v4)
      | name                     | type     | readOnly |
      | participant2-displayname | 5        | 1        |

  Scenario: delete user who left a one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant1" is participant of the following rooms (v4)
      | name         | type     | readOnly |
      | participant2 | 1        | 0        |
    When user "participant2" leaves room "one-to-one room" with 200 (v4)
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | one-to-one room | deleted_users | deleted_users |                  | Message 1 | []                |
    Then user "participant1" is participant of the following rooms (v4)
      | name                     | type     | readOnly |
      | participant2-displayname | 5        | 1        |

  Scenario: delete user who is in a group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | group room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | group room | deleted_users | deleted_users |                  | Message 1 | []                |

  Scenario: delete user who is in a public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | public room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "public room" with 201
    When user "participant2" is deleted
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType     | actorId       | actorDisplayName | message   | messageParameters |
      | public room | deleted_users | deleted_users |                  | Message 1 | []                |

  Scenario: delete user who is the last participant in a public room
    Given user "participant2" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | public room |
    When user "participant2" is deleted
    Then user "participant1" joins room "public room" with 404 (v4)
