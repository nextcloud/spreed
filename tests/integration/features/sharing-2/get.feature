Feature: get

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists


  Scenario: get DAV properties for a received share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" gets the share-type DAV property for "Talk/welcome.txt"
    Then the response contains a share-types DAV property with
    

  Scenario: get DAV properties for a user share reshared with a room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" creates room "another group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/welcome.txt"
    Then the response contains a share-types DAV property with
      | 0 |

  Scenario: get DAV properties for a room share reshared with a user as the resharer
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" creates room "another group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    When user "participant2" gets the share-type DAV property for "/Talk/welcome.txt"
    Then the response contains a share-types DAV property with
      | 0 |

  Scenario: get DAV properties for a user share reshared with a room as the resharer
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" creates room "another group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "group room" with OCS 100
    When user "participant2" gets the share-type DAV property for "/welcome (2).txt"
    Then the response contains a share-types DAV property with
      | 10 |

