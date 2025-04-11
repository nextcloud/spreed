Feature: delete

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: delete share with an owned one-to-one room
    Given user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" shares "welcome.txt" with room "own one-to-one room" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: delete share with a one-to-one room invited to
    Given user "participant2" creates room "one-to-one room invited to" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" sends message "Message 1" to room "one-to-one room invited to" with 201
    And user "participant1" shares "welcome.txt" with room "one-to-one room invited to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: delete share with an owned group room
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "own group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: delete share with a group room invited to
    Given user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: delete share with an owned public room
    Given user "participant1" creates room "own public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "own public room" with 200 (v4)
    And user "participant3" joins room "own public room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "own public room" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant3" gets last share
    And the OCS status code should be "404"

  Scenario: delete share with a public room invited to
    Given user "participant2" creates room "public room invited to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" adds user "participant1" to room "public room invited to" with 200 (v4)
    And user "participant3" joins room "public room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room invited to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant3" gets last share
    And the OCS status code should be "404"

  Scenario: delete share with a public room self joined to
    Given user "participant2" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" joins room "public room self joined to" with 200 (v4)
    And user "participant3" joins room "public room self joined to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room self joined to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And the OCS status code should be "404"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant3" gets last share
    And the OCS status code should be "404"



  Scenario: delete (unknown) share with a one-to-one room not invited to
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant2" shares "welcome.txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | one-to-one room not invited to |
      | share_with_displayname | participant3-displayname |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | one-to-one room not invited to |
      | share_with_displayname | participant2-displayname |

  Scenario: delete (unknown) share with a group room not invited to
    Given user "participant2" creates room "group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room not invited to" to "Group room not invited to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "group room not invited to" with 200 (v4)
    And user "participant2" shares "welcome.txt" with room "group room not invited to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room not invited to |
      | share_with_displayname | Group room not invited to |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room not invited to |
      | share_with_displayname | Group room not invited to |

  Scenario: delete (unknown) share with a public room not joined to
    Given user "participant2" creates room "public room not joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room not joined to" to "Public room not joined to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "public room not joined to" with 200 (v4)
    And user "participant2" shares "welcome.txt" with room "public room not joined to" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | public room not joined to |
      | share_with_displayname | Public room not joined to |
      | token                  | A_TOKEN |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | public room not joined to |
      | share_with_displayname | Public room not joined to |
      | token                  | A_TOKEN |



  Scenario: delete share with a user who also received that share through a room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant2" gets all received shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: delete share with a room including a user who also received that share directly
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant2" gets all received shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |



  Scenario: delete received share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant1" gets last share
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
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |



  Scenario: delete share received directly and through a room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" deletes last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And user "participant1" gets last share
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
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets all received shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |

  Scenario: Delete file in app Files and don't receive the deleted file when list the shared files with "file" format
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    And user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {file}   | "IGNORE"          |
    And user "participant1" sees the following shared summarized overview in room "public room" with 200
      | audio     | 0 |
      | deckcard  | 0 |
      | file      | 1 |
      | location  | 0 |
      | media     | 0 |
      | other     | 0 |
      | poll      | 0 |
      | voice     | 0 |
      | recording | 0 |
    When user "participant1" deletes file "welcome.txt"
    Then user "participant1" sees the following shared file in room "public room" with 200
    And user "participant1" sees the following shared summarized overview in room "public room" with 200
      | audio     | 0 |
      | deckcard  | 0 |
      | file      | 0 |
      | location  | 0 |
      | media     | 0 |
      | other     | 0 |
      | poll      | 0 |
      | voice     | 0 |
      | recording | 0 |
    And user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | *You shared a file which is no longer available* | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
