Feature: update

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: update share with an owned one-to-one room
    Given user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" shares "welcome.txt" with room "own one-to-one room" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own one-to-one room |
      | share_with_displayname | participant2-displayname |
      | permissions            | 1 |
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
      | share_with             | own one-to-one room |
      | share_with_displayname | participant1-displayname |
      | permissions            | 1 |
      | expiration             | +3 days |

  Scenario: update share with a one-to-one room invited to
    Given user "participant2" creates room "one-to-one room invited to" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" sends message "Message 1" to room "one-to-one room invited to" with 201
    And user "participant1" shares "welcome.txt" with room "one-to-one room invited to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | one-to-one room invited to |
      | share_with_displayname | participant2-displayname |
      | permissions            | 1 |
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
      | share_with             | one-to-one room invited to |
      | share_with_displayname | participant1-displayname |
      | permissions            | 1 |
      | expiration             | +3 days |

  Scenario: update share with an owned group room
    Given user "participant1" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | own group room |
      | share_with_displayname | Own group room |
      | permissions            | 1 |
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
      | share_with             | own group room |
      | share_with_displayname | Own group room |
      | permissions            | 1 |
      | expiration             | +3 days |

  Scenario: update share with a group room invited to
    Given user "participant2" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "group room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 1 |
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
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 1 |
      | expiration             | +3 days |

  Scenario: update share with an owned public room
    Given user "participant1" creates room "own public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "own public room" to "Own public room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "own public room" with 200 (v4)
    And user "participant3" joins room "own public room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "own public room" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
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
      | permissions            | 1 |
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
      | share_with             | own public room |
      | share_with_displayname | Own public room |
      | token                  | A_TOKEN |
      | permissions            | 1 |
      | expiration             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |

  Scenario: update share with a public room invited to
    Given user "participant2" creates room "public room invited to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room invited to" to "Public room invited to" with 200 (v4)
    And user "participant2" adds user "participant1" to room "public room invited to" with 200 (v4)
    And user "participant3" joins room "public room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room invited to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
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
      | permissions            | 1 |
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
      | share_with             | public room invited to |
      | share_with_displayname | Public room invited to |
      | token                  | A_TOKEN |
      | permissions            | 1 |
      | expiration             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |

  Scenario: update share with a public room self joined to
    Given user "participant2" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room self joined to" to "Public room self joined to" with 200 (v4)
    And user "participant1" joins room "public room self joined to" with 200 (v4)
    And user "participant3" joins room "public room self joined to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room self joined to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant1" gets last share
    And share is returned with
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
      | permissions            | 1 |
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
      | share_with             | public room self joined to |
      | share_with_displayname | Public room self joined to |
      | token                  | A_TOKEN |
      | permissions            | 1 |
      | expiration             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |



  Scenario: update (unknown) share with a one-to-one room not invited to
    Given user "participant2" creates room "one-to-one room not invited to" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant2" shares "welcome.txt" with room "one-to-one room not invited to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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

  Scenario: update (unknown) share with a group room not invited to
    Given user "participant2" creates room "group room not invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "group room not invited to" to "Group room not invited to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "group room not invited to" with 200 (v4)
    And user "participant2" shares "welcome.txt" with room "group room not invited to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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

  Scenario: update (unknown) share with a public room not joined to
    Given user "participant2" creates room "public room not joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "public room not joined to" to "Public room not joined to" with 200 (v4)
    And user "participant2" adds user "participant3" to room "public room not joined to" with 200 (v4)
    And user "participant2" shares "welcome.txt" with room "public room not joined to" with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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



  Scenario: update received share with an owned one-to-one room
    Given user "participant2" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" sends message "Message 1" to room "own one-to-one room" with 201
    And user "participant1" shares "welcome.txt" with room "own one-to-one room" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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

  Scenario: update received share with a one-to-one room invited to
    Given user "participant1" creates room "one-to-one room invited to" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" shares "welcome.txt" with room "one-to-one room invited to" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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

  Scenario: update received share with an owned group room
    Given user "participant2" creates room "own group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant2" renames room "own group room" to "Own group room" with 200 (v4)
    And user "participant2" adds user "participant1" to room "own group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "own group room" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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

  Scenario: update received share with a group room invited to
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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
    And user "participant3" gets last share
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

  Scenario: update received share with a group room no longer invited to
    Given user "participant1" creates room "group room no longer invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room no longer invited to" to "Group room no longer invited to" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room no longer invited to" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room no longer invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room no longer invited to" with OCS 100
    And user "participant1" removes "participant2" from room "group room no longer invited to" with 200 (v4)
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expiration             | +3 days |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room no longer invited to |
      | share_with_displayname | Group room no longer invited to |
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room no longer invited to |
      | share_with_displayname | Group room no longer invited to |

  Scenario: update received share with an owned public room
    Given user "participant2" creates room "own public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" renames room "own public room" to "Own public room" with 200 (v4)
    And user "participant2" adds user "participant1" to room "own public room" with 200 (v4)
    And user "participant3" joins room "own public room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "own public room" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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

  Scenario: update received share with a public room invited to
    Given user "participant1" creates room "public room invited to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room invited to" to "Public room invited to" with 200 (v4)
    And user "participant1" adds user "participant2" to room "public room invited to" with 200 (v4)
    And user "participant3" joins room "public room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room invited to" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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

  Scenario: update received share with a public room self joined to
    Given user "participant1" creates room "public room self joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room self joined to" to "Public room self joined to" with 200 (v4)
    And user "participant2" joins room "public room self joined to" with 200 (v4)
    And user "participant3" joins room "public room self joined to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room self joined to" with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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

  Scenario: update received share with a public room no longer joined to
    Given user "participant1" creates room "public room no longer joined to" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" renames room "public room no longer joined to" to "Public room no longer joined to" with 200 (v4)
    And user "participant2" joins room "public room no longer joined to" with 200 (v4)
    And user "participant3" joins room "public room no longer joined to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room no longer joined to" with OCS 100
    And user "participant2" leaves room "public room no longer joined to" with 200 (v4)
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expiration             | +3 days |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | public room no longer joined to |
      | share_with_displayname | Public room no longer joined to |
      | token                  | A_TOKEN |
    And user "participant2" gets last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | public room no longer joined to |
      | share_with_displayname | Public room no longer joined to |
      | token                  | A_TOKEN |



  Scenario: update received share after moving it
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant2" creates folder "/test"
    And user "participant2" moves file "/Talk/welcome.txt" to "/test/renamed.txt" with 201
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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
      | path                   | /test/renamed.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/test/renamed.txt |
      | file_target            | /test/renamed.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
    And user "participant3" gets last share
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

  Scenario: update received share with a room no longer invited to after moving it
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant2" creates folder "/test"
    And user "participant2" moves file "/Talk/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" removes "participant2" from room "group room invited to" with 200 (v4)
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
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
    And the OCS status code should be "404"
    And the HTTP status code should be "200"
    And user "participant3" gets last share
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



  Scenario: update received share with increased permissions
    Given user "participant1" creates room "group room invited to" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room invited to" to "Group room invited to" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room invited to" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room invited to" with OCS 100
    And user "participant1" updates last share with
      | permissions            | 1 |
    When user "participant2" updates last share with
      | permissions            | 19 |
    Then the OCS status code should be "403"
    And the HTTP status code should be "200"
    And user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant1 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
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
      | share_with             | group room invited to |
      | share_with_displayname | Group room invited to |
      | permissions            | 1 |



  Scenario: update share after sharee deleted it
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" deletes last share with OCS 100
    When user "participant1" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
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
      | expiration             | +3 days |
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
      | permissions            | 1 |
      | expiration             | +3 days |
    And user "participant2" gets last share
    And the OCS status code should be "404"

  Scenario: update received share after deleting it
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    And user "participant2" deletes last share with OCS 100
    When user "participant2" updates last share with
      | permissions            | 1 |
      | expireDate             | +3 days |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
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
    And user "participant2" gets last share
    And the OCS status code should be "404"
