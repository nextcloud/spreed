Feature: conversation-4/hidden-participants

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: The participants list of a presentation is only available to moderators
    Given user "participant1" creates room "presentation" (v4)
      | roomType | 2            |
      | roomName | presentation |
      | preset   | presentation |
    And user "participant1" adds user "participant2" to room "presentation" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id           | type | participantType | attributes |
      | presentation | 2    | 1               | 32         |
    And user "participant1" sees the following attendees in room "presentation" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
    But user "participant2" sees the following attendees in room "presentation" with 403 (v4)

  Scenario: Webinars hide the participants list
    Given user "participant1" creates room "webinar" (v4)
      | roomType | 3       |
      | roomName | webinar |
      | preset   | webinar |
    Then user "participant1" is participant of the following rooms (v4)
      | id      | type | participantType | attributes |
      | webinar | 3    | 1               | 32         |

  Scenario: Default conversations do not hide the participants list
    Given user "participant1" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant2" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
