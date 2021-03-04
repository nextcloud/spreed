Feature: sharees
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"

  Scenario: search empty name
    Given user "participant1" creates room "unnamed own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Group room" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" gets sharees for
      | search | |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned is empty

  Scenario: search one-to-one room
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" gets sharees for
      | search | participant2 |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned are
      | participant2-displayname | one-to-one room |


  Scenario: search own group room with no matches
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Group room" with 200 (v4)
    When user "participant1" gets sharees for
      | search | unmatched search term |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned is empty

  Scenario: search own group room with single match
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Group room" with 200 (v4)
    When user "participant1" gets sharees for
      | search | room |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned are
      | Group room | own group room |

  Scenario: search own group room with single exact match
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Group room" with 200 (v4)
    When user "participant1" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned are
      | Group room | own group room |
    And "rooms" sharees returned is empty

  Scenario: search own group room with several matches
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Group room" with 200 (v4)
    And user "participant1" creates room "another own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "another own group room" to "Another group room" with 200 (v4)
    When user "participant1" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned are
      | Group room | own group room |
    And "rooms" sharees returned are
      | Another group room | another own group room |



  Scenario: search group room not invited to
    Given user "participant1" creates room "group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room not invited to" to "Group room" with 200 (v4)
    And user "participant2" creates room "another group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "another group room not invited to" to "Another group room" with 200 (v4)
    When user "participant3" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned is empty

  Scenario: search group room invited to with single match
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    When user "participant2" gets sharees for
      | search | room |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned are
      | Group room | group room invited to |

  Scenario: search group room invited to with single exact match
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    When user "participant2" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned are
      | Group room | group room invited to |
    And "rooms" sharees returned is empty

  Scenario: search group room invited to with several matches
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "another group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "another group room invited to" to "Another group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "another group room invited to" with 200 (v4)
    When user "participant2" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned are
      | Group room | group room invited to |
    And "rooms" sharees returned are
      | Another group room | another group room invited to |



  Scenario: search group room invited to as member of a group with single match
    Given user "participant1" creates room "group room invited to as member of a group" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" renames room "group room invited to as member of a group" to "Group room" with 200 (v4)
    When user "participant2" gets sharees for
      | search | room |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned are
      | Group room | group room invited to as member of a group |

  Scenario: search group room invited to as member of a group with single exact match
    Given user "participant1" creates room "group room invited to as member of a group" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" renames room "group room invited to as member of a group" to "Group room" with 200 (v4)
    When user "participant2" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned are
      | Group room | group room invited to as member of a group |
    And "rooms" sharees returned is empty

  Scenario: search group room invited to as member of a group with several matches
    Given user "participant1" creates room "group room invited to as member of a group" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" renames room "group room invited to as member of a group" to "Group room" with 200 (v4)
    And user "participant1" creates room "another group room invited to as member of a group" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" renames room "another group room invited to as member of a group" to "Another group room" with 200 (v4)
    When user "participant2" gets sharees for
      | search | group room |
    Then "exact rooms" sharees returned are
      | Group room | group room invited to as member of a group |
    And "rooms" sharees returned are
      | Another group room | another group room invited to as member of a group |



  Scenario: search public room not joined to
    Given user "participant1" creates room "public room not joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room not joined to" to "Public room" with 200 (v4)
    And user "participant2" creates room "another public room not joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "another public room not joined to" to "Another public room" with 200 (v4)
    When user "participant3" gets sharees for
      | search | public room |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned is empty

  Scenario: search public room self joined to with single match
    Given user "participant1" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room self joined to" to "Public room" with 200 (v4)
    And user "participant2" joins room "public room self joined to" with 200 (v4)
    When user "participant2" gets sharees for
      | search | room |
    Then "exact rooms" sharees returned is empty
    And "rooms" sharees returned are
      | Public room | public room self joined to |

  Scenario: search public room self joined to with single exact match
    Given user "participant1" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room self joined to" to "Public room" with 200 (v4)
    And user "participant2" joins room "public room self joined to" with 200 (v4)
    When user "participant2" gets sharees for
      | search | public room |
    Then "exact rooms" sharees returned are
      | Public room | public room self joined to |
    And "rooms" sharees returned is empty

  Scenario: search public room self joined to with several matches
    Given user "participant1" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room self joined to" to "Public room" with 200 (v4)
    And user "participant2" joins room "public room self joined to" with 200 (v4)
    And user "participant1" creates room "another public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "another public room self joined to" to "Another public room" with 200 (v4)
    And user "participant2" joins room "another public room self joined to" with 200 (v4)
    When user "participant2" gets sharees for
      | search | public room |
    Then "exact rooms" sharees returned are
      | Public room | public room self joined to |
    And "rooms" sharees returned are
      | Another public room | another public room self joined to |



  Scenario: search room with several matches
    Given user "participant1" creates room "group room invited to as member of a group" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" renames room "group room invited to as member of a group" to "Room" with 200 (v4)
    And user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room not invited to" to "Group room not invited to" with 200 (v4)
    And user "participant2" creates room "case insensitive match" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant1" creates room "one-to-one room invited to" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" creates room "own public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "own public room" to "Own public room" with 200 (v4)
    When user "participant2" gets sharees for
      | search | room |
    Then "exact rooms" sharees returned are
      | Room | group room invited to as member of a group |
      | room | case insensitive match |
    And "rooms" sharees returned are
      | Group room | group room invited to |
      | Own group room | own group room |
      | Own public room | own public room |
