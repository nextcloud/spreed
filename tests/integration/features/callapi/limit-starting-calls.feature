Feature: callapi/limit-starting-calls
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists

  Scenario: User (even as room owner) can not start a call while not being a member of the allowed groups
    Given the following "spreed" app config is set
      | start_calls_groups | ["group1"] |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 403 (v4)
    Given user "participant1" is member of group "group1"
    Then user "participant1" joins call "room" with 200 (v4)

  Scenario: User that is not a member of the allowed groups can join an ongoing call
    Given the following "spreed" app config is set
      | start_calls_groups | ["group1"] |
    And user "participant1" is member of group "group1"
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    Then user "participant2" joins call "room" with 403 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" joins call "room" with 200 (v4)

  Scenario: Without the app config being set every user can start a call
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
