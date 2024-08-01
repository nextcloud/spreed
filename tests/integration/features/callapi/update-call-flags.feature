Feature: callapi/update-call-flags
  Background:
    Given user "owner" exists
    And user "moderator" exists
    And user "invited user" exists
    And user "not invited but joined user" exists

  Scenario: all participants can update their call flags when in a call
    Given signaling server is started
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" joins room "public room" with 200 (v4)
    And user "moderator" joins room "public room" with 200 (v4)
    And user "invited user" joins room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And reset signaling server requests
    And user "owner" joins call "public room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | public room | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | public room | {"type":"incall","incall":{"incall":7,"changed":[{"inCall":7,"lastPing":LAST_PING(),"sessionId":"SESSION(owner)","nextcloudSessionId":"SESSION(owner)","participantType":1,"participantPermissions":254,"actorType":"users","actorId":"owner","userId":"owner"}],"users":[{"inCall":7,"lastPing":LAST_PING(),"sessionId":"SESSION(owner)","nextcloudSessionId":"SESSION(owner)","participantType":1,"participantPermissions":254,"actorType":"users","actorId":"owner","userId":"owner"}]}} |
    And reset signaling server requests
    When user "owner" updates call flags in room "public room" to "1" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | public room | {"type":"incall","incall":{"incall":1,"changed":[{"inCall":1,"lastPing":LAST_PING(),"sessionId":"SESSION(owner)","nextcloudSessionId":"SESSION(owner)","participantType":1,"participantPermissions":254,"actorType":"users","actorId":"owner","userId":"owner"}],"users":[{"inCall":1,"lastPing":LAST_PING(),"sessionId":"SESSION(owner)","nextcloudSessionId":"SESSION(owner)","participantType":1,"participantPermissions":254,"actorType":"users","actorId":"owner","userId":"owner"}]}} |
    And user "moderator" joins call "public room" with 200 (v4)
    And user "invited user" joins call "public room" with 200 (v4)
    And user "not invited but joined user" joins call "public room" with 200 (v4)
    And user "guest moderator" joins call "public room" with 200 (v4)
    And user "guest" joins call "public room" with 200 (v4)
    And user "moderator" updates call flags in room "public room" to "1" with 200 (v4)
    And user "invited user" updates call flags in room "public room" to "1" with 200 (v4)
    And user "not invited but joined user" updates call flags in room "public room" to "1" with 200 (v4)
    And user "guest moderator" updates call flags in room "public room" to "1" with 200 (v4)
    And user "guest" updates call flags in room "public room" to "1" with 200 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 1      |
      | users      | moderator                   | 1      |
      | users      | invited user                | 1      |
      | users      | not invited but joined user | 1      |
      | guests     | "guest moderator"           | 1      |
      | guests     | "guest"                     | 1      |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 1      |
      | users      | moderator                   | 1      |
      | users      | invited user                | 1      |
      | users      | not invited but joined user | 1      |
      | guests     | "guest moderator"           | 1      |
      | guests     | "guest"                     | 1      |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 1      |
      | users      | moderator                   | 1      |
      | users      | invited user                | 1      |
      | users      | not invited but joined user | 1      |
      | guests     | "guest moderator"           | 1      |
      | guests     | "guest"                     | 1      |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 1      |
      | users      | moderator                   | 1      |
      | users      | invited user                | 1      |
      | users      | not invited but joined user | 1      |
      | guests     | "guest moderator"           | 1      |
      | guests     | "guest"                     | 1      |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 1      |
      | users      | moderator                   | 1      |
      | users      | invited user                | 1      |
      | users      | not invited but joined user | 1      |
      | guests     | "guest moderator"           | 1      |
      | guests     | "guest"                     | 1      |

  Scenario: update call flags with in call does not join the call
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" joins room "public room" with 200 (v4)
    And user "moderator" joins room "public room" with 200 (v4)
    And user "invited user" joins room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    When user "owner" updates call flags in room "public room" to "1" with 400 (v4)
    And user "moderator" updates call flags in room "public room" to "1" with 400 (v4)
    And user "invited user" updates call flags in room "public room" to "1" with 400 (v4)
    And user "not invited but joined user" updates call flags in room "public room" to "1" with 400 (v4)
    And user "guest moderator" updates call flags in room "public room" to "1" with 400 (v4)
    And user "guest" updates call flags in room "public room" to "1" with 400 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 0      |
      | users      | moderator                   | 0      |
      | users      | invited user                | 0      |
      | users      | not invited but joined user | 0      |
      | guests     | "guest moderator"           | 0      |
      | guests     | "guest"                     | 0      |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 0      |
      | users      | moderator                   | 0      |
      | users      | invited user                | 0      |
      | users      | not invited but joined user | 0      |
      | guests     | "guest moderator"           | 0      |
      | guests     | "guest"                     | 0      |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 0      |
      | users      | moderator                   | 0      |
      | users      | invited user                | 0      |
      | users      | not invited but joined user | 0      |
      | guests     | "guest moderator"           | 0      |
      | guests     | "guest"                     | 0      |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 0      |
      | users      | moderator                   | 0      |
      | users      | invited user                | 0      |
      | users      | not invited but joined user | 0      |
      | guests     | "guest moderator"           | 0      |
      | guests     | "guest"                     | 0      |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 0      |
      | users      | moderator                   | 0      |
      | users      | invited user                | 0      |
      | users      | not invited but joined user | 0      |
      | guests     | "guest moderator"           | 0      |
      | guests     | "guest"                     | 0      |

  Scenario: update call flags with disconnected does not leave the call
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" joins room "public room" with 200 (v4)
    And user "moderator" joins room "public room" with 200 (v4)
    And user "invited user" joins room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "owner" joins call "public room" with 200 (v4)
    And user "moderator" joins call "public room" with 200 (v4)
    And user "invited user" joins call "public room" with 200 (v4)
    And user "not invited but joined user" joins call "public room" with 200 (v4)
    And user "guest moderator" joins call "public room" with 200 (v4)
    And user "guest" joins call "public room" with 200 (v4)
    When user "owner" updates call flags in room "public room" to "0" with 400 (v4)
    And user "moderator" updates call flags in room "public room" to "0" with 400 (v4)
    And user "invited user" updates call flags in room "public room" to "0" with 400 (v4)
    And user "not invited but joined user" updates call flags in room "public room" to "0" with 400 (v4)
    And user "guest moderator" updates call flags in room "public room" to "0" with 400 (v4)
    And user "guest" updates call flags in room "public room" to "0" with 400 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |

  Scenario: update call flags requires in call flag to be set
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" joins room "public room" with 200 (v4)
    And user "moderator" joins room "public room" with 200 (v4)
    And user "invited user" joins room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "owner" joins call "public room" with 200 (v4)
    And user "moderator" joins call "public room" with 200 (v4)
    And user "invited user" joins call "public room" with 200 (v4)
    And user "not invited but joined user" joins call "public room" with 200 (v4)
    And user "guest moderator" joins call "public room" with 200 (v4)
    And user "guest" joins call "public room" with 200 (v4)
    When user "owner" updates call flags in room "public room" to "2" with 400 (v4)
    And user "moderator" updates call flags in room "public room" to "2" with 400 (v4)
    And user "invited user" updates call flags in room "public room" to "2" with 400 (v4)
    And user "not invited but joined user" updates call flags in room "public room" to "2" with 400 (v4)
    And user "guest moderator" updates call flags in room "public room" to "2" with 400 (v4)
    And user "guest" updates call flags in room "public room" to "2" with 400 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | inCall |
      | users      | owner                       | 7      |
      | users      | moderator                   | 7      |
      | users      | invited user                | 7      |
      | users      | not invited but joined user | 7      |
      | guests     | "guest moderator"           | 7      |
      | guests     | "guest"                     | 7      |
