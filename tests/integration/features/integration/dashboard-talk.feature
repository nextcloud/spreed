Feature: integration/dashboard-talk
  Background:
    Given user "participant1" exists

  Scenario: User gets the events for the talk dashboard
    Given user "participant1" creates conversation with event "room" (v4)
      | roomType | 2 |
      | objectType | event |
      | objectId | 36000#72000 |
    Given user "participant1" creates conversation with event "room2" (v4)
      | roomType | 2 |
    Given user "participant1" creates room "room3" (v4)
      | roomType | 2 |
    Then user "participant1" sees the following entry when loading the dashboard conversations (v4)
      | name | objectType | objectId |
      | room | event      | OBJECT_ID(room) |
      | room2 | | |
