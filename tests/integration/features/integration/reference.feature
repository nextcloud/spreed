Feature: integration/reference
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Message reference is only resolved when not blocked by the lobby
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | group room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "group room" with 201
    Then user "participant2" resolves reference to message "Message 1" in room "group room" with 200
      | title       | Message of participant1-displayname in group room |
      | description | Message 1                                         |
      | message-id  | Message 1                                         |
    When user "participant1" sets lobby state for room "group room" to "non moderators" with 200 (v4)
    Then user "participant2" resolves reference to message "Message 1" in room "group room" with 200
      | title       | group room |
      | description |            |
      | message-id  |            |
    When user "participant1" promotes "participant2" in room "group room" with 200 (v4)
    Then user "participant2" resolves reference to message "Message 1" in room "group room" with 200
      | title       | Message of participant1-displayname in group room |
      | description | Message 1                                         |
      | message-id  | Message 1                                         |
