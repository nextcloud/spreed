Feature: conversation-3/schedule-meeting
  Background:
    Given user "participant1" exists and has an email address

  Scenario: Moderator can schedule a meeting
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" schedules a meeting in room "room" with 200 (v4)

  Scenario: Moderator can schedule a meeting with title and description
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" schedules a meeting in room "room" with 200 (v4)
      | title       | Weekly sync  |
      | description | Agenda TBD   |

  Scenario: Scheduling a meeting with end before start is rejected
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" schedules a meeting in room "room" with 400 (v4)
      | start | 7200 |
      | end   | 3600 |

  Scenario: Regular participant cannot schedule a meeting
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" exists
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" schedules a meeting in room "room" with 403 (v4)
