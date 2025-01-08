Feature: conversation-2/promotion-demotion
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner promotes/demotes moderator
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And reset signaling server requests
    When user "participant1" promotes "participant2" in room "room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
    # TODO remove handler with "roomModified" in favour of handler with
    # "participantsModified" once the clients no longer expect a
    # "roomModified" message for participant type changes.
      | room  | {"type":"update","update":{"userids":["participant1","participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
      | room  | {"type":"participants","participants":{"changed":[],"users":[{"inCall":0,"lastPing":0,"sessionId":"0","participantType":1,"participantPermissions":1,"displayName":"participant1-displayname","actorType":"users","actorId":"participant1","userId":"participant1"},{"inCall":0,"lastPing":0,"sessionId":"0","participantType":2,"participantPermissions":1,"displayName":"participant2-displayname","actorType":"users","actorId":"participant2","userId":"participant2"}]}} |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant1" demotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: Moderator promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" sets permissions for "participant3" in room "room" to "L" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | participant1 | SJLAVPM     |
      | users      | participant2 | SJLAVPM     |
      | users      | participant3 | CL           |
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant2" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | participant1 | SJLAVPM     |
      | users      | participant2 | SJLAVPM     |
      | users      | participant3 | SJLAVPM     |
    When user "participant2" demotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | participant1 | SJLAVPM     |
      | users      | participant2 | SJLAVPM     |
      | users      | participant3 | SJAVPM      |

  Scenario: Moderator promotes self-joined user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 5               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant1" demotes "participant2" in room "room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: User promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" promotes "participant3" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant1" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |

  Scenario: Stranger promotes/demotes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" promotes "participant3" in room "room" with 404 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant1" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 404 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
