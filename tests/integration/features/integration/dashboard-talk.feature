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
