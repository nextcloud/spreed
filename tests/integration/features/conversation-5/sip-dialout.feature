Feature: conversation-2/sip-dialout
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists
    Given user "participant1" is member of group "group1"

  Scenario: SIP admin uses dial out
    Given the following "spreed" app config is set
      | sip_bridge_dialin_info | +49-1234-567890 |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups | ["group1"] |
      | sip_dialout | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled |
      | room | 3    | 1               | 0          |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" adds phone "+491601231212" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId           | displayName              | phoneNumber   |
      | 3               | 0        | phones    | PHONE(+491601231212)     | +49160123…               | +491601231212 |
      | 1               | 0        | users     | participant1      | participant1-displayname |               |
      | 3               | 0        | users     | participant2      | participant2-displayname |               |
    When user "participant2" adds phone "+491601231212" to room "room" with 403 (v4)

  Scenario: Non-SIP admin tries to dial out
    Given the following "spreed" app config is set
      | sip_bridge_dialin_info | +49-1234-567890 |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups | ["group1"] |
      | sip_dialout | yes |
    Given user "participant2" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant2" adds phone "+491601231212" to room "room" with 501 (v4)
    When user "participant2" adds user "participant1" to room "room" with 200 (v4)
    # SIP admin that is not a moderator can also not dial-out
    When user "participant1" adds phone "+491601231212" to room "room" with 403 (v4)
