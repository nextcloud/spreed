Feature: conversation-2/set-password
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner sets a room password
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And reset signaling server requests
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | room  | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant1" sets password "" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 200 (v4)

  Scenario: Moderator sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant2" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant2" sets password "" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 200 (v4)

  Scenario: User sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" sets password "foobar" for room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant2" sets password "" for room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 403 (v4)

  Scenario: Stranger sets a room password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant2" sets password "foobar" for room "room" with 404 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then user "participant3" joins room "room" with 403 (v4)
    Then user "participant3" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant3" leaves room "room" with 200 (v4)
    When user "participant2" sets password "" for room "room" with 404 (v4)
    Then user "participant3" joins room "room" with 403 (v4)

  # Applicable e.g. after permissions increased https://github.com/nextcloud/spreed/issues/14728
  Scenario: Guest can reload / rejoin a password protected conversation without reentering the password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" sets password "foobar" for room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4) session name "guest1"
      | password | foobar |
    And user "guest" sends message "Message 1" to room "room" with 201
    And user "guest" joins room "room" with 200 (v4) session name "guest1"
    And user "guest" sends message "Message 2" to room "room" with 201
