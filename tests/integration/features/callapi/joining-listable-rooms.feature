Feature: callapi/listable-rooms
  Background:
    Given user "creator" exists
    And user "regular-user" exists
    And user "user-guest" exists
    And user "user-guest" is member of group "guest_app"
    # implicit: And user "guest" is a guest user with no account

  # -----------------------------------------------------------------------------
  # Non-listed rooms
  # -----------------------------------------------------------------------------
  Scenario: Nobody can join a non-listed group room
    Given user "creator" creates room "room"
      | roomType | 2    |
      | roomName | room |
    When user "creator" allows listing room "room" for "none" with 200
    Then user "regular-user" joins room "room" with 404
    And user "user-guest" joins room "room" with 404
    And user "guest" joins room "room" with 404

  Scenario: Anyone can join a non-listed public room
    Given user "creator" creates room "room"
      | roomType | 3    |
      | roomName | room |
    And user "creator" allows listing room "room" for "none" with 200
    When user "regular-user" joins room "room" with 200
    And user "user-guest" joins room "room" with 200
    And user "guest" joins room "room" with 200
    Then user "creator" sees the following attendees in room "room" with 200 (v3)
      | actorId      | participantType   | actorType |
      | creator      | OWNER             | users     |
      | regular-user | USER_SELF_JOINED  | users     |
      | user-guest   | USER_SELF_JOINED  | users     |
      | "guest"      | GUEST             | guests    |

  # -----------------------------------------------------------------------------
  # User-listed rooms
  # -----------------------------------------------------------------------------
  Scenario: Only regular users can join a user-listed group room
    Given user "creator" creates room "room"
      | roomType | 2    |
      | roomName | room |
    And user "creator" allows listing room "room" for "users" with 200
    When user "regular-user" joins room "room" with 200
    And user "user-guest" joins room "room" with 404
    And user "guest" joins room "room" with 404
    Then user "creator" sees the following attendees in room "room" with 200 (v3)
      | actorId       | participantType | actorType |
      | creator       | OWNER           | users     |
      | regular-user  | USER            | users     |

  Scenario: Anyone can join a user-listed public room
    Given user "creator" creates room "room"
      | roomType | 3    |
      | roomName | room |
    And user "creator" allows listing room "room" for "users" with 200
    When user "regular-user" joins room "room" with 200
    And user "user-guest" joins room "room" with 200
    And user "guest" joins room "room" with 200
    Then user "creator" sees the following attendees in room "room" with 200 (v3)
      | actorId      | participantType   | actorType |
      | creator      | OWNER             | users     |
      | regular-user | USER              | users     |
      | user-guest   | USER_SELF_JOINED  | users     |
      | "guest"      | GUEST             | guests    |

  # -----------------------------------------------------------------------------
  # All-listed rooms
  # -----------------------------------------------------------------------------
  Scenario: Only users with accounts can join an all-listed group room
    Given user "creator" creates room "room"
      | roomType | 2    |
      | roomName | room |
    And user "creator" allows listing room "room" for "all" with 200
    When user "regular-user" joins room "room" with 200
    And user "user-guest" joins room "room" with 200
    And user "guest" joins room "room" with 404
    Then user "creator" sees the following attendees in room "room" with 200 (v3)
      | actorId      | participantType   | actorType |
      | creator      | OWNER             | users     |
      | regular-user | USER              | users     |
      | user-guest   | USER_SELF_JOINED  | users     |

  Scenario: Anyone can join an all-listed public room
    Given user "creator" creates room "room"
      | roomType | 3    |
      | roomName | room |
    And user "creator" allows listing room "room" for "all" with 200
    When user "regular-user" joins room "room" with 200
    And user "user-guest" joins room "room" with 200
    And user "guest" joins room "room" with 200
    Then user "creator" sees the following attendees in room "room" with 200 (v3)
      | actorId      | participantType   | actorType |
      | creator      | OWNER             | users     |
      | regular-user | USER              | users     |
      | user-guest   | USER              | users     |
      | "guest"      | GUEST             | guests    |
