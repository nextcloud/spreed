Feature: conversation/find-listed
  Background:
    Given user "creator" exists
    And user "regular-user" exists
    And guest accounts can be created
    And user "user-guest@example.com" is a guest account user

  Scenario Outline: Nobody can find non-listed rooms
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    And user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    When user "creator" allows listing room "group-room" for "none" with 200 (v4)
    And user "creator" allows listing room "public-room" for "none" with 200 (v4)
    Then user "<user>" cannot find any listed rooms (v4)
    Examples:
      | user                   |
      | creator                |
      | regular-user           |
      | user-guest@example.com |

  Scenario: Regular users can find user-listed rooms
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    And user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    And user "creator" sets description for room "group-room" to "the group-room description" with 200 (v4)
    And user "creator" sets description for room "public-room" to "the public-room description" with 200 (v4)
    When user "creator" allows listing room "group-room" for "users" with 200 (v4)
    And user "creator" allows listing room "public-room" for "users" with 200 (v4)
    Then user "regular-user" can find listed rooms (v4)
      | name        | listable | description                 |
      | group-room  | 1        | the group-room description  |
      | public-room | 1        | the public-room description |
    And user "user-guest@example.com" cannot find any listed rooms (v4)

  Scenario: All users can find all-listed rooms
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    And user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    When user "creator" allows listing room "group-room" for "all" with 200 (v4)
    And user "creator" allows listing room "public-room" for "all" with 200 (v4)
    Then user "regular-user" can find listed rooms (v4)
      | name        | listable |
      | group-room  | 2        |
      | public-room | 2        |
    And user "user-guest@example.com" can find listed rooms (v4)
      | name        | listable |
      | group-room  | 2        |
      | public-room | 2        |

  Scenario: Participants cannot search for already joined listed rooms
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    And user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    And user "creator" allows listing room "group-room" for "users" with 200 (v4)
    And user "creator" allows listing room "public-room" for "users" with 200 (v4)
    When user "regular-user" joins room "group-room" with 200 (v4)
    And user "regular-user" joins room "public-room" with 200 (v4)
    Then user "regular-user" cannot find any listed rooms (v4)

  Scenario: Participants cannot search for already joined listed rooms
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    And user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    And user "creator" allows listing room "group-room" for "users" with 200 (v4)
    And user "creator" allows listing room "public-room" for "users" with 200 (v4)
    When user "regular-user" joins room "group-room" with 200 (v4)
    And user "regular-user" joins room "public-room" with 200 (v4)
    Then user "regular-user" cannot find any listed rooms (v4)

  Scenario: Users can use search terms to find listed rooms
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    And user "creator" creates room "group-the-cool-room" (v4)
      | roomType | 2                   |
      | roomName | group-the-cool-room |
    And user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    And user "creator" creates room "public-the-cool-room" (v4)
      | roomType | 3                    |
      | roomName | public-the-cool-room |
    When user "creator" allows listing room "group-room" for "all" with 200 (v4)
    And user "creator" allows listing room "public-room" for "all" with 200 (v4)
    And user "creator" allows listing room "group-the-cool-room" for "all" with 200 (v4)
    And user "creator" allows listing room "public-the-cool-room" for "all" with 200 (v4)
    Then user "regular-user" can find listed rooms with term "cool" (v4)
      | name                 | listable |
      | group-the-cool-room  | 2        |
      | public-the-cool-room | 2        |
    And user "user-guest@example.com" can find listed rooms with term "cool" (v4)
      | name                 | listable |
      | group-the-cool-room  | 2        |
      | public-the-cool-room | 2        |

  Scenario: Searching for a listable room by unknown term returns no results
    Given user "creator" creates room "group-room" (v4)
      | roomType | 2           |
      | roomName | group-room  |
    When user "creator" allows listing room "group-room" for "all" with 200 (v4)
    Then user "regular-user" cannot find any listed rooms with term "cool" (v4)
    And user "user-guest@example.com" cannot find any listed rooms with term "cool" (v4)

  Scenario: Guest users without accounts cannot search for listed rooms
    Given user "creator" creates room "public-room" (v4)
      | roomType | 3           |
      | roomName | public-room |
    And user "creator" creates room "public-room-listed" (v4)
      | roomType | 3                  |
      | roomName | public-room-listed |
    And user "creator" allows listing room "public-room-listed" for "all" with 200 (v4)
    When user "guest" joins room "public-room" with 200 (v4)
    Then user "guest" cannot find any listed rooms with 401 (v4)
