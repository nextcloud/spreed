Feature: conversation-3/invite-email
  Background:
    Given user "participant1" exists

  Scenario: Resend email invites
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" adds email "test@example.tld" to room "room" with 200 (v4)
    # Adding the same email again should not error to help the Calendar integration
    # Ref https://github.com/nextcloud/calendar/pull/5380
    When user "participant1" adds email "test@example.tld" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | inCall   | actorType | actorId                  | invitedActorId    |
      | 4               | 0        | emails    | SHA256(test@example.tld) | test@example.tld  |
      | 1               | 0        | users     | participant1             | ABSENT            |
    # Reinvite all emails
    When user "participant1" resends invite for room "room" with 200 (v4)
    # Reinvite only one
    When user "participant1" resends invite for room "room" with 200 (v4)
      | attendeeId | test@example.tld |
    # Reinvite failure
    When user "participant1" resends invite for room "room" with 404 (v4)
      | attendeeId | not-found@example.tld |

  Scenario: Creating a private conversation with an email invitation keeps the submitted room type
    When user "participant1" creates room "room" (v4)
      | roomType                | 2                |
      | roomName                | room             |
      | participants[emails][0] | test@example.tld |
    Then user "participant1" gets room "room" with 200 (v4)
      | type | 2 |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | actorType | actorId                  | invitedActorId    |
      | 4               | emails    | SHA256(test@example.tld) | test@example.tld  |
      | 1               | users     | participant1             | ABSENT            |

  Scenario: Email guest joins a private conversation through their invite link
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds email "test@example.tld" to room "room" with 200 (v4)
    And user "participant1" is participant of room "room" (v4)
      | type |
      | 2    |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | actorType | actorId                  | invitedActorId    |
      | 4               | emails    | SHA256(test@example.tld) | test@example.tld  |
      | 1               | users     | participant1             | ABSENT            |
    When user "guest" views call-URL of room "room" with 200
      | email | test@example.tld |
    Then user "guest" joins room "room" with 200 (v4)
    Then user "guest" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "participant1" sees 1 peers in call "room" with 200 (v4)
    Then user "guest" leaves room "room" with 200 (v4)

  Scenario: Email guest cannot rejoin after being removed from a private conversation
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds email "test@example.tld" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | participantType | actorType | actorId                  | invitedActorId    |
      | 4               | emails    | SHA256(test@example.tld) | test@example.tld  |
      | 1               | users     | participant1             | ABSENT            |
    And user "guest" views call-URL of room "room" with 200
      | email | test@example.tld |
    And user "guest" joins room "room" with 200 (v4)
    And user "guest" leaves room "room" with 200 (v4)
    When user "participant1" removes email "test@example.tld" from room "room" with 200 (v4)
    Then user "guest" joins room "room" with 404 (v4)
