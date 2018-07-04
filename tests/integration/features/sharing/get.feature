Feature: get

  Background:
    Given user "participant1" exists
    Given user "participant2" exists



  Scenario: get a share
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" gets last share
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: get a received share
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" gets last share
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |



  Scenario: get a share using a user not invited to the room
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" gets last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"



  Scenario: get a share after changing the room name
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" renames room "group room" to "New room name" with 200
    When user "participant1" gets last share
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | group room |
      | share_with_displayname | New room name |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | New room name |
