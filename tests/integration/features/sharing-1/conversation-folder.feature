Feature: sharing-1/conversation-folder

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: conversation folder is created automatically when user is added to a room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    When user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    Then user "participant1" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | REGEXP /^\/Talk\/Group room-[a-zA-Z0-9]{8}\/participant1-dis-participant1$/ |
      | item_type              | folder |
      | storage_id             | home::participant1 |
      | file_target            | REGEXP /^\/\{TALK_PLACEHOLDER\}\/Group room-[a-zA-Z0-9]{8}\/participant1-dis-participant1$/ |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | permissions            | 1 |
    And user "participant2" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | REGEXP /^\/Talk\/Group room-[a-zA-Z0-9]{8}\/participant2-dis-participant2$/ |
      | item_type              | folder |
      | file_target            | REGEXP /^\/\{TALK_PLACEHOLDER\}\/Group room-[a-zA-Z0-9]{8}\/participant2-dis-participant2$/ |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | permissions            | 1 |
    And user "participant2" gets all received shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner              | participant1 |
      | displayname_owner      | participant1-displayname |
      | path                   | REGEXP /^\/Talk\/Group room-[a-zA-Z0-9]{8}\/participant1-dis-participant1$/ |
      | item_type              | folder |
      | storage_id             | REGEXP /^shared::\/Talk\/Group room-[a-zA-Z0-9]{8}\/participant1-dis-participant1$/ |
      | file_target            | REGEXP /^\/Talk\/Group room-[a-zA-Z0-9]{8}\/participant1-dis-participant1$/ |
      | share_with             | group room |
      | share_with_displayname | Group room |
      | permissions            | 1 |

  Scenario: manual conversation folder creation is idempotent with auto-created folder
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" creates conversation folder for room "group room" (v4)
    And user "participant1" gets all shares
    Then the list of returned shares has 1 shares

  Scenario: non-participant folder is not shared with the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant3" creates conversation folder for room "group room" (v4)
    And user "participant3" gets all shares
    Then the list of returned shares has 0 shares

  Scenario: participant added later gets own folder and access to existing shares
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    Then user "participant3" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner   | participant3 |
      | item_type   | folder |
      | file_target | REGEXP /^\/\{TALK_PLACEHOLDER\}\/Group room-[a-zA-Z0-9]{8}\/participant3-dis-participant3$/ |
      | share_with  | group room |
      | permissions | 1 |
    And user "participant3" gets all received shares
    And the list of returned shares has 2 shares

  Scenario: each participant gets their own conversation folder automatically
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    When user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    Then user "participant1" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner   | participant1 |
      | item_type   | folder |
      | file_target | REGEXP /^\/\{TALK_PLACEHOLDER\}\/Group room-[a-zA-Z0-9]{8}\/participant1-dis-participant1$/ |
      | share_with  | group room |
      | permissions | 1 |
    And user "participant2" gets all shares
    And the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner   | participant2 |
      | item_type   | folder |
      | file_target | REGEXP /^\/\{TALK_PLACEHOLDER\}\/Group room-[a-zA-Z0-9]{8}\/participant2-dis-participant2$/ |
      | share_with  | group room |
      | permissions | 1 |

  Scenario: auto-created conversation folder does not generate a file_shared chat message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage        |
      | group room | users     | participant1 | participant1-displayname | user_added           |
      | group room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: posting a conversation folder file creates file_shared message without per-file share
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" posts conversation folder file to room "group room" with 200 (v4)
    And user "participant1" gets all shares
    Then the list of returned shares has 1 shares
    And user "participant2" gets all received shares
    And the list of returned shares has 1 shares

  Scenario: conversation folder share ignores the room message expiration policy
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" set the message expiration to 3600 of room "group room" with 200 (v4)
    When user "participant1" creates conversation folder for room "group room" (v4)
    And user "participant1" gets all shares
    Then the list of returned shares has 1 shares
    And share 0 is returned with
      | uid_owner   | participant1 |
      | item_type   | folder |
      | permissions | 1 |
      | expiration  |   |
