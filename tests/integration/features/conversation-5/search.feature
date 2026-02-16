Feature: conversation-5/search

  Background:
    Given user "participant1" exists

  Scenario: Search for conversations with cursor and limit
    Given user "participant1" creates room "Room 1" (v4)
      | roomType | 2 |
      | roomName | Room 1 |
    Given user "participant1" creates room "Room 2" (v4)
      | roomType | 2 |
      | roomName | room 2 |
    Given user "participant1" creates room "Room 3" (v4)
      | roomType | 2 |
      | roomName | ROOM 3 |
    Given user "participant1" creates room "Room 4" (v4)
      | roomType | 2 |
      | roomName | Room 4 |
    Given user "participant1" creates room "Room 5" (v4)
      | roomType | 2 |
      | roomName | Room 5 |
    Given user "participant1" creates room "Room 6" (v4)
      | roomType | 2 |
      | roomName | Room 6 |
    And user "participant1" searches for conversations with "o" limit 1 expected cursor "Room 1"
      | title  | subline | attributes.conversation |
      | Room 1 |         | Room 1                  |
    And user "participant1" searches for conversations with "o" offset "Room 4" limit 1 expected cursor "Room 5"
      | title  | subline | attributes.conversation |
      | Room 5 |         | Room 5                  |
    And user "participant1" searches for conversations with "o" offset "Room 4" limit 5 expected cursor ""
      | title  | subline | attributes.conversation |
      | Room 5 |         | Room 5                  |
      | Room 6 |         | Room 6                  |

  Scenario: Search for conversations with cyrillic multibyte chars
    Given user "participant1" creates room "кирилл 1" (v4)
      | roomType | 2 |
      | roomName | кирилл 1 |
    Given user "participant1" creates room "Кирилл 2" (v4)
      | roomType | 2 |
      | roomName |Кирилл 2 |
    And user "participant1" searches for conversations with "ки" limit 3 expected cursor "NULL"
      | title    | subline | attributes.conversation |
      | кирилл 1 |         | кирилл 1                |
      | Кирилл 2 |         | Кирилл 2                |
    And user "participant1" searches for conversations with "Ки" limit 3 expected cursor "NULL"
      | title    | subline | attributes.conversation |
      | кирилл 1 |         | кирилл 1                |
      | Кирилл 2 |         | Кирилл 2                |
    And user "participant1" searches for conversations with "Ки" limit 1 expected cursor "кирилл 1"
      | title    | subline | attributes.conversation |
      | кирилл 1 |         | кирилл 1                |
    And user "participant1" searches for conversations with "Ки" offset "кирилл 1" limit 1 expected cursor ""
      | title    | subline | attributes.conversation |
      | Кирилл 2 |         | Кирилл 2                |
