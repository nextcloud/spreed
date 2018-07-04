Feature: move

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: move share to another folder
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" creates folder "/test"
    When user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test/renamed.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
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
      | share_with_displayname | Group room |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |



  Scenario: move received share to another folder
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" creates folder "/test"
    When user "participant2" moves file "/welcome (2).txt" to "/test/renamed.txt" with 201
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test/renamed.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/test/renamed.txt |
      | file_target            | /test/renamed.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
