Feature: integration/dashboard-talk
  Background:
    Given user "participant1" exists

  Scenario: User gets the events for the talk dashboard
    Given user "participant1" creates room "dashboardRoom" (v4)
      | roomType | 2 |
    Given user "participant1" creates calendar events for a room "dashboardRoom" (v4)
      | roomType | 2 |
    Then user "participant1" sees the following entry when loading the dashboard conversations (v4)
      |roomName          | roomType| eventName                | invited | accepted | declined | tentative | eventAttachments | calendars |
      |dashboardRoom     | 2       | dashboardRoom-single     | null    | null     | null     | null      | 0                | 1         |
      |dashboardRoom     | 2       | dashboardRoom-attachment | null    | null     | null     | null      | 1                | 1         |
      |dashboardRoom     | 2       | dashboardRoom-attendees  | 1       | 1        | null     | null      | 0                | 1         |
      |dashboardRoom     | 2       | dashboardRoom-recurring  | null    | null     | null     | null      | 0                | 1         |

  Scenario: User gets mutual events for a one to one conversation
    Given user "participant1" exists and has an email address
    Given user "participant2" exists and has an email address
    Given user "participant1" creates room "room1" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room1" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    Then user "participant1" is participant of room "room1" (v4)
    And user "participant2" is participant of room "room1" (v4)
    And user "participant1" joins room "room1" with 200 (v4)
    Given user "participant1" creates calendar events inviting user "participant2" (v4)
    Then user "participant1" sees the following entry when loading mutual events in room "room1" (v4)
      | eventName | eventDescription | invited | accepted | declined | tentative | eventAttachments | calendars | roomType |
      | Test      | Test             | 1       | null     | null     | null      | 0                | 1         | 0        |
      | Test      | Test             | 1       | null     | null     | null      | 0                | 1         | 0        |
      | Test      | Test             | 1       | null     | null     | null      | 1                | 1         | 0        |
    Then user "participant2" joins room "room1" with 200 (v4)
    Then user "participant2" sees the following entry when loading mutual events in room "room1" (v4)
      | eventName | eventDescription | invited | accepted | declined | tentative | eventAttachments | calendars | roomType |
      | Test      | Test             | 1       | null     | null     | null      | 0                | 1         | 0        |
      | Test      | Test             | 1       | null     | null     | null      | 0                | 1         | 0        |
      | Test      | Test             | 1       | null     | null     | null      | 1                | 1         | 0        |
