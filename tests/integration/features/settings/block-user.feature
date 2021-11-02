Feature: settings/block-user

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: user 1 block user 2
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant1" block user "participant2" with 200 (v4)