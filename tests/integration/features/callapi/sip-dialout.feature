Feature: callapi/sip-dialout
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists
    Given user "participant1" is member of group "group1"

  Scenario: SIP admin uses dial out
    Given signaling server is started
    And the following "spreed" app config is set
      | sip_bridge_dialin_info | +49-1234-567890 |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups | ["group1"] |
      | sip_dialout | yes |
    And user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled |
      | room | 3    | 1               | 0          |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" adds phone "+491601231212" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId              | displayName              | phoneNumber   | callId |
      | 3               | 0        | phones    | PHONE(+491601231212) | +49160123…               | +491601231212 |        |
      | 3               | 0        | users     | participant2         | participant2-displayname |               |        |
      | 1               | 0        | users     | participant1         | participant1-displayname |               |        |
    When user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    And reset signaling server requests
    And signaling server will respond with
      | response | {"type": "dialout","dialout": {"callid": "the-call-id"}} |
    And user "participant1" dials out to "+491601231212" from call in room "room" with 201 (v4)
    Then signaling server received the following requests
      | token | data                                                         |
      | room  | {"type":"dialout","dialout":{"number":"+491601231212","options":{"attendeeId":PHONEATTENDEE(+491601231212),"actorType":"phones","actorId":"PHONE(+491601231212)"}}} |
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId              | displayName              | phoneNumber   | callId      |
      | 3               | 0        | phones    | PHONE(+491601231212) | +49160123…               | +491601231212 | the-call-id |
      | 3               | 0        | users     | participant2         | participant2-displayname |               |             |
      | 1               | 7        | users     | participant1         | participant1-displayname |               |             |
    Then user "participant2" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId              | displayName              | phoneNumber   | callId      |
      | 3               | 0        | phones    | PHONE(+491601231212) | +49160123…               |               |             |
      | 3               | 0        | users     | participant2         | participant2-displayname |               |             |
      | 1               | 7        | users     | participant1         | participant1-displayname |               |             |
