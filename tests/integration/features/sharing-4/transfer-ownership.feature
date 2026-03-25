Feature: transfer-ownership

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: transfer ownership of a file shared with a room to a user in the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When transfering ownership from "participant1" to "participant2"
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | REGEXP /\/Transferred from participant1-displayname on .*\/welcome.txt/ |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  Scenario: transfer ownership of a file reshared with a room to a user in the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant3" shares "welcome.txt" with user "participant1" with OCS 100
    And user "participant1" accepts last share
    And user "participant1" shares "welcome (2).txt" with room "group room" with OCS 100
    When transfering ownership from "participant1" to "participant2"
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant3 |
      | displayname_file_owner | participant3-displayname |
      | path                   | /Talk/welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome (2).txt |
      | file_target            | /Talk/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant3 |
      | displayname_file_owner | participant3-displayname |
      | path                   | /Transferred from participant1-displayname on {{DATE AND TIME}}/welcome (2).txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Transferred from participant1-displayname on {{DATE AND TIME}}/welcome (2).txt |
      | file_target            | /Talk/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | uid_file_owner         | participant3 |
      | displayname_file_owner | participant3-displayname |
      | path                   | /welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant3 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome (2).txt |
      | share_with             | group room |
      | share_with_displayname | Group room |

  # This is a special case in which even if the (now) sharer is not in a room
  # the room share is valid and other participants can access that share.
  Scenario: transfer ownership of a file shared with a room to a user not in the room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When transfering ownership from "participant1" to "participant2"
    Then user "participant1" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
    And user "participant2" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | REGEXP /\/Transferred from participant1-displayname on .*\/welcome.txt/ |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | home::participant2 |
      | file_target            | /{TALK_PLACEHOLDER}/welcome.txt |
      | share_with             | private_conversation |
      | share_with_displayname | Private conversation |
    And user "participant3" gets last share
    And share is returned with
      | uid_owner              | participant2 |
      | displayname_owner      | participant2-displayname |
      | path                   | /Talk/welcome.txt |
      | item_type              | file |
      | mimetype               | text/plain |
      | storage_id             | shared::/Talk/welcome.txt |
      | file_target            | /Talk/welcome.txt |
      | share_with             | group room |
      | share_with_displayname | Group room |
