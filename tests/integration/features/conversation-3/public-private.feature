Feature: conversation-2/public-private
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner makes room private/public
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And reset signaling server requests
    When user "participant1" makes room "room" private with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | room  | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":2,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    And reset signaling server requests
    When user "participant1" makes room "room" public with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | room  | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |

  Scenario: Moderator makes room private/public
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant2" makes room "room" private with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    When user "participant2" makes room "room" public with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |

  Scenario: User makes room private/public
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" makes room "room" private with 403 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant1" makes room "room" private with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    When user "participant2" makes room "room" public with 403 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |

  Scenario: Stranger makes room private/public
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant2" makes room "room" private with 404 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    When user "participant1" makes room "room" private with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    When user "participant2" makes room "room" public with 404 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
