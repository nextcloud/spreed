Feature: create

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: create share with an owned one-to-one room
    Given user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" shares "welcome.txt" with room "own one-to-one room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own one-to-one room |
      | share_with_displayname | participant2-displayname |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | own one-to-one room |
      | share_with_displayname | participant1-displayname |

  Scenario: create share with a one-to-one room invited to
    Given user "participant2" creates room "one-to-one room invited to" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" sends message "Message 1" to room "one-to-one room invited to" with 201
    When user "participant1" shares "welcome.txt" with room "one-to-one room invited to"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | one-to-one room invited to |
      | share_with_displayname | participant2-displayname |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | one-to-one room invited to |
      | share_with_displayname | participant1-displayname |

  Scenario: create share with a one-to-one room not invited to
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    When user "participant1" shares "welcome.txt" with room "one-to-one room not invited to"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares
    And user "participant3" gets all received shares
    And the list of returned shares has 0 shares

  Scenario: create share with an owned group room
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "own group room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |

  Scenario: create share with a group room invited to
    Given user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "group room invited to"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |

  Scenario: create share with a group room not invited to
    Given user "participant2" creates room "group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" shares "welcome.txt" with room "group room not invited to"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares

  Scenario: create share with a group room no longer invited to
    Given user "participant2" creates room "group room no longer invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" adds user "participant1" to room "group room no longer invited to" with 200 (v4)
    And user "participant2" removes "participant1" from room "group room no longer invited to" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "group room no longer invited to"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares

  Scenario: create share with an owned public room
    Given user "participant1" creates room "own public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "own public room" to "Own public room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own public room" with 200 (v4)
    And user "participant3" joins room "own public room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "own public room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own public room |
      | share_with_displayname | Own public room |
      | token                  | A_TOKEN |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | own public room |
      | share_with_displayname | Own public room |
      | token                  | A_TOKEN |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | own public room |
      | share_with_displayname | Own public room |
      | token                  | A_TOKEN |

  Scenario: create share with a public room invited to
    Given user "participant2" creates room "public room invited to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room invited to" to "Public room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "public room invited to" with 200 (v4)
    And user "participant3" joins room "public room invited to" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "public room invited to"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | public room invited to |
      | share_with_displayname | Public room invited to |
      | token                  | A_TOKEN |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | public room invited to |
      | share_with_displayname | Public room invited to |
      | token                  | A_TOKEN |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | public room invited to |
      | share_with_displayname | Public room invited to |
      | token                  | A_TOKEN |

  Scenario: create share with a public room self joined to
    Given user "participant2" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room self joined to" to "Public room self joined to" with 200 (v4)
    And user "participant1" joins room "public room self joined to" with 200 (v4)
    And user "participant3" joins room "public room self joined to" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "public room self joined to"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | public room self joined to |
      | share_with_displayname | Public room self joined to |
      | token                  | A_TOKEN |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | public room self joined to |
      | share_with_displayname | Public room self joined to |
      | token                  | A_TOKEN |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | public room self joined to |
      | share_with_displayname | Public room self joined to |
      | token                  | A_TOKEN |

  Scenario: create share with a public room not joined to
    Given user "participant2" creates room "public room not joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares "welcome.txt" with room "public room not joined to"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares

  Scenario: create share with a public room no longer joined to
    Given user "participant2" creates room "public room no longer joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" joins room "public room no longer joined to" with 200 (v4)
    And user "participant1" leaves room "public room no longer joined to" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "public room no longer joined to"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares



  Scenario: create share with a room of a received share whose owner is in the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant2" shares "welcome.txt" with user "participant1" with OCS 100
    And user "participant1" accepts last share
    When user "participant1" shares "welcome (2).txt" with room "group room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant2 |
      | displayname_file_owner | participant2-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
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
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant2 |
      | displayname_file_owner | participant2-displayname |
      | path                   | /Talk/welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome (2).txt |
      | file_target            | /Talk/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: create share with a room of a received share whose owner is not in the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant2" shares "welcome.txt" with user "participant1" with OCS 100
    And user "participant1" accepts last share
    When user "participant1" shares "welcome (2).txt" with room "group room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant2 |
      | displayname_file_owner | participant2-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
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
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | uid_file_owner         | participant2 |
      | displayname_file_owner | participant2-displayname |
      | path                   | /Talk/welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome (2).txt |
      | file_target            | /Talk/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: create share with a room of a received share without reshare permissions
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant2" shares "welcome.txt" with user "participant1"
      | permissions            | 1 |
    And share is returned with
      | permissions            | 1 |
      | share_type             | 0 |
    And user "participant1" accepts last share
    When user "participant1" shares "welcome (2).txt" with room "group room"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant1" gets all received shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | permissions            | 1 |
      | share_type             | 0 |
    And user "participant2" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | permissions            | 1 |
      | share_type             | 0 |
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares
    And user "participant3" gets all received shares
    And the list of returned shares has 0 shares



  Scenario: create share with an expiration date
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "group room"
      | expireDate | +3 days |
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
      | expiration             | +3 days |
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
      | share_with_displayname | Group room |
      | expiration             | +3 days |

  Scenario: create share with an invalid expiration date
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "group room"
      | expireDate | invalid date |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 0 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 0 shares

  Scenario: create share with specific permissions
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "group room"
      | permissions | 1 |
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
      | permissions            | 1 |
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
      | share_with_displayname | Group room |
      | permissions            | 1 |



  Scenario: create share again with another room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" creates room "another group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "another group room" to "Another group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "another group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "another group room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | another group room |
      | share_with_displayname | Another group room |
    And user "participant1" gets all shares
    And the list of returned shares has 2 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | another group room |
      | share_with_displayname | Another group room |
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
    And user "participant3" gets all received shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | another group room |
      | share_with_displayname | Another group room |

  Scenario: create share again with same room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" shares "welcome.txt" with room "group room"
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
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

  Scenario: create share again with same room by a sharee
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant2" shares "Talk/welcome.txt" with room "group room"
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets all shares
    And the list of returned shares has 0 shares
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



  Scenario: create share with a room that includes a user who already received that share through another room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant1" creates room "another group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "another group room" to "Another group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "another group room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "another group room"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | another group room |
      | share_with_displayname | Another group room |
    And user "participant2" gets all received shares
    And the list of returned shares has 2 shares
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
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | another group room |
      | share_with_displayname | Another group room |

  Scenario: create share with a user who already received that share through a room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" shares "welcome.txt" with user "participant2"
    Then share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /welcome.txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |
    And user "participant2" gets all received shares
    And the list of returned shares has 2 shares
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
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | participant2 |
      | share_with_displayname | participant2-displayname |
      | share_type             | 0 |

  Scenario: create share with a room including a user who already received that share directly
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" shares "welcome.txt" with room "group room"
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
    And user "participant2" gets all received shares
    And the list of returned shares has 2 shares
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
    And share 1 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/welcome (2).txt |
      | file_target            | /welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
