Feature: command/user-remove

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Remove a user from all their rooms
    Given user "participant1" creates room "one-to-one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | public room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    Given user "participant1" creates room "private room" (v4)
      | roomType | 2 |
      | roomName | private room |
    And user "participant1" adds user "participant2" to room "private room" with 200 (v4)
    Given user "participant1" creates room "listable room" (v4)
      | roomType | 2 |
      | roomName | listable room |
    And user "participant1" allows listing room "listable room" for "users" with 200 (v4)
    And user "participant1" adds user "participant2" to room "listable room" with 200 (v4)
    And user "participant2" is participant of the following unordered rooms (v4)
      | id            | name          | type | participantType | readOnly |
      | one-to-one    | participant1  | 1    | 1               | 0        |
      | public room   | public room   | 3    | 3               | 0        |
      | private room  | private room  | 2    | 3               | 0        |
      | listable room | listable room | 2    | 3               | 0        |
    And user "participant1" is participant of the following unordered rooms (v4)
      | id            | name          | type | participantType | readOnly |
      | one-to-one    | participant2  | 1    | 1               | 0        |
      | public room   | public room   | 3    | 1               | 0        |
      | private room  | private room  | 2    | 1               | 0        |
      | listable room | listable room | 2    | 1               | 0        |
    And invoking occ with "talk:user:remove --user participant2 --private-only"
    And the command output contains the text "Users successfully removed from all rooms"
    Then the command was successful
    And user "participant2" is participant of the following unordered rooms (v4)
      | id            | name          | type | participantType | readOnly |
      | one-to-one    | participant1  | 1    | 1               | 0        |
      | public room   | public room   | 3    | 3               | 0        |
      | listable room | listable room | 2    | 3               | 0        |
    And user "participant1" is participant of the following unordered rooms (v4)
      | id            | name          | type | participantType | readOnly |
      | one-to-one    | participant2  | 1    | 1               | 0        |
      | public room   | public room   | 3    | 1               | 0        |
      | private room  | private room  | 2    | 1               | 0        |
      | listable room | listable room | 2    | 1               | 0        |
    And invoking occ with "talk:user:remove --user participant2"
    And the command output contains the text "Users successfully removed from all rooms"
    Then the command was successful
    And user "participant2" is participant of the following rooms (v4)
    And user "participant2" is participant of the following unordered rooms (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id            | name          | type | participantType | readOnly |
      | one-to-one    | participant2-displayname | 5    | 1               | 1        |
      | public room   | public room   | 3    | 1               | 0        |
      | private room  | private room  | 2    | 1               | 0        |
      | listable room | listable room | 2    | 1               | 0        |
    And user "participant1" sees the following attendees in room "one-to-one" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Remove a user after there was a missed call
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
    Then user "participant1" leaves call "room" with 200 (v4)
    Then user "participant1" leaves room "room" with 200 (v4)
    And reset signaling server requests
    And invoking occ with "talk:user:remove --user participant2"
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"disinvite","disinvite":{"userids":["participant2"],"alluserids":["participant1"],"properties":{"name":"Private conversation","type":1,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"participant-list":"refresh"}}} |
      # Type changed
      | room  | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":5,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
      # Name changed
      | room  | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":5,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      # Read only changed
      | room  | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":5,"lobby-state":0,"lobby-timer":null,"read-only":1,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
   And the command output contains the text "Users successfully removed from all rooms"
    Then the command was successful
    And user "participant2" is participant of the following rooms (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id   | name                     | type | participantType |
      | room | participant2-displayname | 5    | 1               |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                      | messageParameters |
      | room | guests        | cli          | read_only            | An administrator locked the conversation | {"actor":{"type":"guest","id":"guest\/cli","name":"Administration","mention-id":"guest\/cli"}} |
      | room | users         | participant1 | call_tried           | You tried to call {user}     | {"user":{"type":"highlight","id":"deleted_users","name":"participant2-displayname"}} |
      | room | users         | participant1 | call_left            | You left the call            | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
      | room | users         | participant1 | call_started         | You started a call           | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
      | room | users         | participant1 | conversation_created | You created the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
