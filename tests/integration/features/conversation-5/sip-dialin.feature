Feature: conversation-2/sip-dialin
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given group "group1" exists
    Given user "participant1" is member of group "group1"

  Scenario: SIP admin enables SIP
    Given the following "spreed" app config is set
      | sip_bridge_dialin_info | +49-1234-567890 |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups | ["group1"] |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled | attendeePin |
      | room | 3    | 1               | 0          |             |
    When user "participant1" sets SIP state for room "room" to "enabled" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled | attendeePin |
      | room | 3    | 1               | 1          | **PIN**     |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" adds user "participant3" to room "room" with 200 (v4)
    When user "participant1" adds email "test@example.tld" to room "room" with 200 (v4)
    # Guests don't get a PIN as they can not be recognized and are deleted on leave
    When user "guest" joins room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId                  | attendeePin |
      | 4               | 0        | emails    | SHA256(test@example.tld) | **PIN**     |
      | 4               | 0        | guests    | "guest"                  |             |
      | 1               | 0        | users     | participant1             | **PIN**     |
      | 3               | 0        | users     | participant2             | **PIN**     |
      | 3               | 0        | users     | participant3             | **PIN**     |
    When user "participant2" sets SIP state for room "room" to "disabled" with 403 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId                  | attendeePin |
      | 4               | 0        | emails    | SHA256(test@example.tld) | **PIN**     |
      | 4               | 0        | guests    | "guest"                  |             |
      | 1               | 0        | users     | participant1             | **PIN**     |
      | 3               | 0        | users     | participant2             | **PIN**     |
      | 3               | 0        | users     | participant3             | **PIN**     |
    When user "participant1" sets SIP state for room "room" to "disabled" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId                  | attendeePin |
      | 4               | 0        | emails    | SHA256(test@example.tld) |             |
      | 4               | 0        | guests    | "guest"                  |             |
      | 1               | 0        | users     | participant1             |             |
      | 3               | 0        | users     | participant2             |             |
      | 3               | 0        | users     | participant3             |             |
    When user "participant1" sets SIP state for room "room" to "no pin" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId                  | attendeePin |
      | 4               | 0        | emails    | SHA256(test@example.tld) | **PIN**     |
      | 4               | 0        | guests    | "guest"                  |             |
      | 1               | 0        | users     | participant1             | **PIN**     |
      | 3               | 0        | users     | participant2             | **PIN**     |
      | 3               | 0        | users     | participant3             | **PIN**     |

  Scenario: Non-SIP admin tries to enable SIP
    Given the following "spreed" app config is set
      | sip_bridge_dialin_info | +49-1234-567890 |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups | ["group1"] |
    Given user "participant2" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled | attendeePin |
      | room | 3    | 1               | 0          |             |
    When user "participant2" sets SIP state for room "room" to "enabled" with 403 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled | attendeePin |
      | room | 3    | 1               | 0          |             |
