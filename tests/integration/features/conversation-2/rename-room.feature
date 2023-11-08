Feature: conversation-2/rename-room
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner renames
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    When user "participant1" renames room "room" to "new name" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)

  Scenario: Moderator renames
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And reset signaling server requests
    When user "participant2" renames room "room" to "new name" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | room  | {"type":"update","update":{"userids":["participant1","participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |

  Scenario: User renames
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" renames room "room" to "new name" with 403 (v4)

  Scenario: Stranger renames
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)
    When user "participant2" renames room "room" to "new name" with 404 (v4)
