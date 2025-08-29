Feature: get

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists



  Scenario: get a share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" gets last share
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: get a received share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" gets last share
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |



  Scenario: get a share using a user not invited to the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" gets last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"



  Scenario: get a share after changing the room name
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" renames room "group room" to "New room name" with 200 (v4)
    When user "participant1" gets last share
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | New room name |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | New room name |



  Scenario: get an expired share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room"
      | expireDate | -3 days |
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | expiration             | -3 days |
    When user "participant1" gets last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"

  Scenario: get an expired share moved by the sharee
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" moves file "/Talk/welcome.txt" to "/Talk/renamed.txt" with 201
    And user "participant1" updates last share with
      | expireDate | -3 days |
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | expiration             | -3 days |
    When user "participant1" gets last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"



  Scenario: get a share after deleting its file
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" deletes file "welcome.txt"
    When user "participant1" gets last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"



  Scenario: get all shares of a user
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    And user "participant1" creates folder "/deleted"
    And user "participant1" shares "deleted" with room "group room invited to" with OCS 100
    And user "participant1" deletes file "deleted"
    When user "participant1" gets all shares
    Then the list of returned shares has 4 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/test |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 31 |
    And share 2 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And share 3 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/test |
      | share_with             | own one-to-one room |
      | share_with_displayname | participant3-displayname |
      | permissions            | 31 |

  Scenario: get all shares and reshares of a user
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    And user "participant1" creates folder "/deleted"
    And user "participant1" shares "deleted" with room "group room invited to" with OCS 100
    And user "participant2" shares "Talk/deleted" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant1" deletes file "deleted"
    When user "participant1" gets all shares and reshares
    Then the list of returned shares has 6 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/test |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 31 |
    And share 2 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And share 3 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/test |
      | share_with             | own one-to-one room |
      | share_with_displayname | participant3-displayname |
      | permissions            | 31 |
    And share 4 is returned with
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
    And share 5 is returned with
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares and reshares of a user who reshared a file to an owned one-to-one room
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares and reshares of a user who reshared a file to a one-to-one room
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant2" sends message "Message 1" to room "one-to-one room not invited to" with 201
    And user "participant1" shares "welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
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
    And share 1 is returned with
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares of a file
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" gets all shares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |

  Scenario: get all shares of a deleted file
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    And user "participant1" deletes file "welcome.txt"
    When user "participant1" gets all shares for "/welcome.txt"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"

  Scenario: get all shares and reshares of a file
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 4 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And share 2 is returned with
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
    And share 3 is returned with
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares and reshares of a file reshared to a one-to-one room by its owner
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares and reshares of a file reshared to a one-to-one room by its second participant
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant2" sends message "Message 1" to room "one-to-one room not invited to" with 201
    And user "participant1" shares "welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
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
    And share 1 is returned with
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares and reshares of a file reshared to a group room not invited to
    Given user "participant2" creates room "group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room not invited to" to "Group room not invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "group room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |

  Scenario: get all shares and reshares of a file reshared to a public room not invited to
    Given user "participant2" creates room "public room not invited to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room not invited to" to "Public room not invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "public room not invited to" with OCS 100
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |
      | token                  | A_TOKEN |

  Scenario: get all shares and reshares of a file reshared to a public room invited to
    Given user "participant2" creates room "public room invited to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room invited to" to "Public room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "public room invited to" with OCS 100
    And user "participant2" adds user "participant1" to room "public room invited to" with 200 (v4)
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | public room invited to |
      | share_with_displayname | Public room invited to |
      | token                  | A_TOKEN |

  Scenario: get all shares and reshares of a file reshared to a public room self-joined to
    Given user "participant2" creates room "public room self-joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room self-joined to" to "Public room self-joined to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "welcome (2).txt" with room "public room self-joined to" with OCS 100
    And user "participant1" joins room "public room self-joined to" with 200 (v4)
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | public room self-joined to |
      | share_with_displayname | Public room self-joined to |
      | token                  | A_TOKEN |

  Scenario: get all shares and reshares of a deleted file
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" shares "Talk/welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "welcome (2).txt" with room "one-to-one room not invited to" with OCS 100
    And user "participant1" deletes file "welcome.txt"
    When user "participant1" gets all shares and reshares for "/welcome.txt"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"

  Scenario: get all shares of a folder
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" creates folder "/test/subfolder"
    And user "participant1" creates folder "/test/subfolder/subsubfolder"
    And user "participant1" creates folder "/test2"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test/subfolder" with room "group room invited to" with OCS 100
    And user "participant1" shares "test/subfolder/subsubfolder" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test2" with room "own one-to-one room" with OCS 100
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant2" shares "Talk/subfolder" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "subfolder" with room "one-to-one room not invited to" with OCS 100
    # Only direct children are taken into account
    When user "participant1" gets all shares for "/test" and its subfiles
    Then the list of returned shares has 5 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test/renamed.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test/subfolder |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/subfolder |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 31 |
    And share 2 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /test/renamed.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And share 3 is returned with
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /test/subfolder |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /subfolder |
      | share_with             | participant3 |
      | share_with_displayname | participant3-displayname |
      | share_type             | 0 |
      | permissions            | 31 |
    And share 4 is returned with
      | uid_file_owner         | participant1 |
      | displayname_file_owner | participant1-displayname |
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | path                   | /test/subfolder |
      | path                   | /test/subfolder |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/subfolder |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |
      | permissions            | 31 |

  Scenario: get all shares of a deleted folder
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant4 |
    And user "participant1" creates folder "/test"
    And user "participant1" creates folder "/test/subfolder"
    And user "participant1" creates folder "/test/subfolder/subsubfolder"
    And user "participant1" creates folder "/test2"
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant1" shares "test/subfolder" with room "group room invited to" with OCS 100
    And user "participant1" shares "test/subfolder/subsubfolder" with room "group room invited to" with OCS 100
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" shares "test2" with room "own one-to-one room" with OCS 100
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant2" shares "Talk/subfolder" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" shares "subfolder" with room "one-to-one room not invited to" with OCS 100
    And user "participant1" deletes file "test"
    When user "participant1" gets all shares for "/test" and its subfiles
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"



  Scenario: get all received shares of a user
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" sends message "Message 1" to room "own one-to-one room" with 201
    And user "participant3" creates folder "/test"
    And user "participant2" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant3" shares "test" with room "group room invited to" with OCS 100
    And user "participant2" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant3" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" creates folder "/deleted"
    And user "participant2" shares "deleted" with room "group room invited to" with OCS 100
    And user "participant2" deletes file "deleted"
    When user "participant1" gets all received shares
    Then the list of returned shares has 4 shares
    And share 0 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | path                   | /Talk/test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | shared::/Talk/test |
      | file_target            | /Talk/test |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 31 |
    And share 2 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And share 3 is returned with
      | uid_owner              | participant3 |
      | displayname_owner      | participant3-displayname |
      | path                   | /Talk/test |
      | item_type              | folder |
      | mimetype               | httpd/unix-directory |
      | storage_id             | shared::/Talk/test |
      | file_target            | /Talk/test |
      | share_with             | own one-to-one room |
      | share_with_displayname | participant3-displayname |
      | permissions            | 31 |

  Scenario: get all received shares of a file
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" sends message "Message 1" to room "own one-to-one room" with 201
    And user "participant3" creates folder "/test"
    And user "participant2" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant3" shares "test" with room "group room invited to" with OCS 100
    And user "participant2" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant3" shares "test" with room "own one-to-one room" with OCS 100
    When user "participant1" gets all received shares for "/Talk/welcome.txt"
    Then the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And share 1 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |

  Scenario: get all received shares of a deleted file
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own group room" with 200 (v4)
    And user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "group room invited to" with 200 (v4)
    And user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant1" sends message "Message 1" to room "own one-to-one room" with 201
    And user "participant3" creates folder "/test"
    And user "participant2" shares "welcome.txt" with room "own group room" with OCS 100
    And user "participant3" shares "test" with room "group room invited to" with OCS 100
    And user "participant2" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant3" shares "test" with room "own one-to-one room" with OCS 100
    And user "participant2" deletes file "welcome.txt"
    When user "participant1" gets all received shares for "/welcome (2).txt"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"



  Scenario: get deleted shares when deleting an own share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" deletes last share
    When user "participant1" gets deleted shares
    Then the list of returned shares has 0 shares
    And user "participant2" gets deleted shares
    And the list of returned shares has 0 shares

  Scenario: get deleted shares when deleting a received share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" deletes last share
    When user "participant2" gets deleted shares
    Then the list of returned shares has 1 shares
    And share 0 is returned with
      | id                     | REGEXP /ocRoomShare:[0-9]+/ |
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | permissions            | 0 |
      | mail_send              | IGNORE |
    And user "participant1" gets deleted shares
    And the list of returned shares has 0 shares
    And user "participant3" gets deleted shares
    And the list of returned shares has 0 shares

  Scenario: get deleted shares when deleting the file of an own share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" deletes file "welcome.txt"
    When user "participant1" gets deleted shares
    Then the list of returned shares has 0 shares
    And user "participant2" gets deleted shares
    And the list of returned shares has 0 shares



  Scenario: get DAV properties for a share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/welcome.txt"
    Then the response contains a share-types DAV property with
      | 10 |

  Scenario: get DAV properties for a folder with a share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "/test/renamed.txt" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/test"
    Then the response contains a share-types DAV property with
      | 10 |

  Scenario: get DAV properties for a received share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/welcome.txt"
    Then the response contains a share-types DAV property with
      | 10 |
    When user "participant2" gets the share-type DAV property for "Talk/welcome.txt"
    Then the response contains a share-types DAV property with
      | 10 |

  Scenario: get DAV properties for a room share reshared with a user
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
    When user "participant1" gets the share-type DAV property for "/welcome.txt"
    Then the response contains a share-types DAV property with
      | 10 |
    When user "participant2" gets the share-type DAV property for "Talk/welcome.txt"
    Then the response contains a share-types DAV property with
      | 0  |
      | 10 |

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
      | 0  |
    When user "participant2" gets the share-type DAV property for "welcome (2).txt"
    Then the response contains a share-types DAV property with
      | 0  |
      | 10 |

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
    When user "participant1" gets the share-type DAV property for "welcome.txt"
    Then the response contains a share-types DAV property with
      | 10 |
    When user "participant2" gets the share-type DAV property for "/Talk/welcome.txt"
    Then the response contains a share-types DAV property with
      | 0  |
      | 10 |

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
    When user "participant1" gets the share-type DAV property for "welcome.txt"
    Then the response contains a share-types DAV property with
      | 0  |
    When user "participant2" gets the share-type DAV property for "/welcome (2).txt"
    Then the response contains a share-types DAV property with
      | 0  |
      | 10 |

  # Reshares are taken into account only for the files in the folder, not the
  # folder itself.
  Scenario: get DAV properties for a reshared folder
    Given user "participant2" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" creates folder "/test"
    And user "participant1" shares "/test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "/test" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/test"
    Then the response contains a share-types DAV property with
      | 0 |

  Scenario: get DAV properties for a folder with a reshare
    Given user "participant2" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "/test/renamed.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "renamed.txt" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/test"
    Then the response contains a share-types DAV property with
      | 0 |
      | 10 |

  Scenario: get DAV properties for a folder with a reshared folder
    Given user "participant2" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" creates folder "/test"
    And user "participant1" creates folder "/test/subfolder"
    And user "participant1" shares "/test/subfolder" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "subfolder" with room "group room" with OCS 100
    When user "participant1" gets the share-type DAV property for "/test"
    Then the response contains a share-types DAV property with
      | 0 |
      | 10 |



  Scenario: get files after sharing a file
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" gets the DAV properties for "/"
    Then the list of returned files for "participant1" is
      | / |
      | /welcome.txt |
    And user "participant2" gets the DAV properties for "/"
    And the list of returned files for "participant2" is
      | / |
      | /Talk/ |
      | /welcome.txt |
    And user "participant2" gets the DAV properties for "/Talk"
    And the list of returned files for "participant2" is
      | /Talk/ |
      | /Talk/welcome.txt |

  Scenario: get files after deleting a share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" deletes last share
    When user "participant1" gets the DAV properties for "/"
    Then the list of returned files for "participant1" is
      | / |
      | /welcome.txt |
    And user "participant2" gets the DAV properties for "/"
    And the list of returned files for "participant2" is
      | / |
      | /welcome.txt |

  Scenario: get files after deleting a received share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" deletes last share
    When user "participant2" gets the DAV properties for "/"
    Then the list of returned files for "participant2" is
      | / |
      | /Talk/ |
      | /welcome.txt |
    And user "participant1" gets the DAV properties for "/"
    And the list of returned files for "participant1" is
      | / |
      | /welcome.txt |

  Scenario: get files after deleting the file of a share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" deletes file "welcome.txt"
    When user "participant1" gets the DAV properties for "/"
    Then the list of returned files for "participant1" is
      | / |
    And user "participant2" gets the DAV properties for "/"
    And the list of returned files for "participant2" is
      | / |
      | /welcome.txt |



  Scenario: get recent files including a share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" creates folder "/test"
    And user "participant1" creates folder "/test/subfolder"
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" shares "test/subfolder" with room "group room" with OCS 100
    And user "participant1" shares "test/subfolder" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    When user "participant1" gets recent files
    Then the response contains a share-types file property for "/welcome.txt" with
      | 10 |
    And the response contains a share-types file property for "/test" with
    And the response contains a share-types file property for "/test/subfolder" with
      | 0 |
      | 10 |
