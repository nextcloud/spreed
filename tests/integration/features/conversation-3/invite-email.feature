Feature: conversation/invite-email
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
