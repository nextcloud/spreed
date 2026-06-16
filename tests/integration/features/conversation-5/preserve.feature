Feature: conversation-5/preserve
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner can preserve and stop preserving a conversation
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" preserves room "room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | attributes |
      | room | 2    | 2          |
    When user "participant1" stops preserving room "room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |

  Scenario: A preserved conversation can not be deleted, cleared or have its guests/joinable settings changed
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" preserves room "room" with 200 (v4)
    Then user "participant1" deletes room "room" with 403 (v4)
    And user "participant1" deletes chat history for room "room" with 403 (v1)
    And user "participant1" makes room "room" public with 403 (v4)
    And user "participant1" allows listing room "room" for "all" with 403 (v4)
    When user "participant1" stops preserving room "room" with 200 (v4)
    Then user "participant1" makes room "room" public with 200 (v4)
    And user "participant1" allows listing room "room" for "all" with 200 (v4)
    And user "participant1" deletes chat history for room "room" with 200 (v1)
    And user "participant1" deletes room "room" with 200 (v4)

  Scenario: A preserved public conversation can not be made private
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" preserves room "room" with 200 (v4)
    Then user "participant1" makes room "room" private with 403 (v4)

  Scenario: Only the owner can preserve a conversation
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    Then user "participant2" preserves room "room" with 403 (v4)
    And user "participant3" preserves room "room" with 403 (v4)
    And user "participant1" preserves room "room" with 200 (v4)
    And user "participant2" stops preserving room "room" with 403 (v4)
    And user "participant3" stops preserving room "room" with 403 (v4)
    And user "participant1" stops preserving room "room" with 200 (v4)
