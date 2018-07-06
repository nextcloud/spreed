Feature: hooks

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  # Entering a room does not really require any hook to work, but conceptually
  # these tests belong here.
  Scenario: invite user to group room after a file was shared
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" adds "participant2" to room "group room" with 200
    Then user "participant2" gets last share
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

  Scenario: join public room after a file was shared
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    And user "participant1" renames room "public room" to "Public room" with 200
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    And user "participant2" joins room "public room" with 200
    Then user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | public room |
      | share_with_displayname | Public room |



  Scenario: remove sharer from group room after sharing a file
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant2" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" removes "participant2" from room "group room" with 200
    Then user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: remove herself from group room after sharing a file
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant2" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" removes themselves from room "group room" with 200
    Then user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: leave group room after sharing a file
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant2" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" leaves room "group room" with 200
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: leave public room invited to after sharing a file
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    And user "participant1" renames room "public room" to "Public room" with 200
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant2" shares "welcome.txt" with room "public room" with OCS 100
    When user "participant2" leaves room "public room" with 200
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | public room |
      | share_with_displayname | Public room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /welcome.txt |
      | share_with             | public room |
      | share_with_displayname | Public room |

  Scenario: leave public room self joined to after sharing a file
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    And user "participant2" joins room "public room" with 200
    And user "participant2" shares "welcome.txt" with room "public room" with OCS 100
    When user "participant2" leaves room "public room" with 200
    Then user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: remove sharer from group room with other shares after sharing a file
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" creates folder "test"
    And user "participant1" shares "test" with room "group room" with OCS 100
    And user "participant2" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" removes "participant2" from room "group room" with 200
    Then user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant1" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /test |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | permissions            | 31 |



  Scenario: remove sharer from group room after sharing a file and a receiver reshared it
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant2" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" shares "welcome (2).txt" with user "participant3" with OCS 100
    When user "participant1" removes "participant2" from room "group room" with 200
    Then user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant2 |
      | displayname_file_owner | participant2-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant3 |
      | share_with_displayname | participant3-displayname |
      | share_type             | 0 |
    And user "participant2" gets all shares
    And the list of returned shares has 0 shares
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant2 |
      | displayname_file_owner | participant2-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | participant3 |
      | share_with_displayname | participant3-displayname |
      | share_type             | 0 |



  Scenario: remove sharee from group room after a file was shared
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" removes "participant2" from room "group room" with 200
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
    And the OCS status code should be "404"

  Scenario: remove sharee from group room after a file was shared and the sharee moved it
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" moves file "welcome (2).txt" to "renamed.txt"
    When user "participant1" removes "participant2" from room "group room" with 200
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
    And the OCS status code should be "404"

  Scenario: remove herself from group room after a file was shared
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" removes themselves from room "group room" with 200
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
    And the OCS status code should be "404"

  Scenario: remove herself from group room after a file was shared and the sharee moved it
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" moves file "welcome (2).txt" to "renamed.txt"
    When user "participant2" removes themselves from room "group room" with 200
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
    And the OCS status code should be "404"

  Scenario: leave public room self joined to after a file was shared
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    And user "participant1" renames room "public room" to "Public room" with 200
    And user "participant2" joins room "public room" with 200
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    When user "participant2" leaves room "public room" with 200
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | public room |
      | share_with_displayname | Public room |
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: leave public room self joined to after a file was shared and the sharee moved it
    Given user "participant1" creates room "public room"
      | roomType | 3 |
    And user "participant1" renames room "public room" to "Public room" with 200
    And user "participant2" joins room "public room" with 200
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    And user "participant2" moves file "welcome (2).txt" to "renamed.txt"
    When user "participant2" leaves room "public room" with 200
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | public room |
      | share_with_displayname | Public room |
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: remove sharee from group room with other sharees after a file was shared and the sharees moved it
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" renames room "group room" to "Group room" with 200
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" moves file "welcome (2).txt" to "renamed.txt"
    And user "participant3" moves file "welcome (2).txt" to "renamed too.txt"
    When user "participant1" removes "participant2" from room "group room" with 200
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
    And the OCS status code should be "404"
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /renamed too.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/renamed too.txt |
      | file_target            | /renamed too.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |



  Scenario: remove sharee from group room after a file was shared and the sharee reshared it
    Given user "participant1" creates room "group room"
      | roomType | 2 |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" shares "welcome (2).txt" with user "participant3" with OCS 100
    When user "participant1" removes "participant2" from room "group room" with 200
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant3 |
      | share_with_displayname | participant3-displayname |
      | share_type             | 0 |
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets all shares
    And the list of returned shares has 0 shares
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | participant3 |
      | share_with_displayname | participant3-displayname |
      | share_type             | 0 |
