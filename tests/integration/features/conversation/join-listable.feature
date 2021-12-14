Feature: conversation/join-listable
  Background:
    Given user "creator" exists
    And user "regular-user" exists
    And guest accounts can be created
    And user "user-guest@example.com" is a guest account user
    # implicit: And user "guest" is a guest user with no account

  # -----------------------------------------------------------------------------
  # Non-listed rooms
  # -----------------------------------------------------------------------------
  Scenario: Nobody can join a non-listed group room
    Given user "creator" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    When user "creator" allows listing room "room" for "none" with 200 (v4)
    Then user "regular-user" joins room "room" with 404 (v4)
    And user "user-guest@example.com" joins room "room" with 404 (v4)
    And user "guest" joins room "room" with 404 (v4)

  Scenario: Anyone can join a non-listed public room
    Given user "creator" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "creator" allows listing room "room" for "none" with 200 (v4)
    When user "regular-user" joins room "room" with 200 (v4)
    And user "user-guest@example.com" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    Then user "creator" sees the following attendees in room "room" with 200 (v4)
      | actorId                | participantType   | actorType |
      | creator                | OWNER             | users     |
      | regular-user           | USER_SELF_JOINED  | users     |
      | user-guest@example.com | USER_SELF_JOINED  | users     |
      | "guest"                | GUEST             | guests    |

  # -----------------------------------------------------------------------------
  # User-listed rooms
  # -----------------------------------------------------------------------------
  Scenario: Only regular users can join a user-listed group room
    Given user "creator" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "creator" allows listing room "room" for "users" with 200 (v4)
    When user "regular-user" joins room "room" with 200 (v4)
    And user "user-guest@example.com" joins room "room" with 404 (v4)
    And user "guest" joins room "room" with 404 (v4)
    Then user "creator" sees the following attendees in room "room" with 200 (v4)
      | actorId       | participantType | actorType |
      | creator       | OWNER           | users     |
      | regular-user  | USER            | users     |

  Scenario: Anyone can join a user-listed public room
    Given user "creator" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "creator" allows listing room "room" for "users" with 200 (v4)
    When user "regular-user" joins room "room" with 200 (v4)
    And user "user-guest@example.com" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    Then user "creator" sees the following attendees in room "room" with 200 (v4)
      | actorId                | participantType   | actorType |
      | creator                | OWNER             | users     |
      | regular-user           | USER              | users     |
      | user-guest@example.com | USER_SELF_JOINED  | users     |
      | "guest"                | GUEST             | guests    |

  # -----------------------------------------------------------------------------
  # All-listed rooms
  # -----------------------------------------------------------------------------
  Scenario: Only users with accounts can join an all-listed group room
    Given user "creator" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "creator" allows listing room "room" for "all" with 200 (v4)
    When user "regular-user" joins room "room" with 200 (v4)
    And user "user-guest@example.com" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 404 (v4)
    Then user "creator" sees the following attendees in room "room" with 200 (v4)
      | actorId                | participantType   | actorType |
      | creator                | OWNER             | users     |
      | regular-user           | USER              | users     |
      | user-guest@example.com | USER              | users     |

  Scenario: Anyone can join an all-listed public room
    Given user "creator" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "creator" allows listing room "room" for "all" with 200 (v4)
    When user "regular-user" joins room "room" with 200 (v4)
    And user "user-guest@example.com" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    Then user "creator" sees the following attendees in room "room" with 200 (v4)
      | actorId                | participantType   | actorType |
      | creator                | OWNER             | users     |
      | regular-user           | USER              | users     |
      | user-guest@example.com | USER              | users     |
      | "guest"                | GUEST             | guests    |

  # -----------------------------------------------------------------------------
  # Join listed conversation which has a password
  # -----------------------------------------------------------------------------
  Scenario: Only users with accounts can join an all-listed group room
    Given user "creator" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    When user "creator" sets password "foobar" for room "room" with 200 (v4)
    And user "creator" allows listing room "room" for "all" with 200 (v4)
    When user "regular-user" joins room "room" with 200 (v4)
    Then user "creator" sees the following attendees in room "room" with 200 (v4)
      | actorId                | participantType   | actorType |
      | creator                | OWNER             | users     |
      | regular-user           | USER              | users     |
