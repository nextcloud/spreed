Feature: chat-4/summary
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Request a chat summary
    Given Fake summary task provider is enabled
    And the following spreed app config is set
      | ai_unread_summary_batch_size | 3 |
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sends message "Message 2" to room "room" with 201
    And user "participant1" sends message "Message 3" to room "room" with 201
    And user "participant1" sends message "Message 4" to room "room" with 201
    And user "participant2" requests summary for "room" starting from "Message 1" with 201
      | nextOffset | Message 3 |
    And repeating run "OC\TaskProcessing\SynchronousBackgroundJob" background jobs
    Then user "participant2" receives summary for "room" with 200
      | contains | This is a fake summary |
    And user "participant2" requests summary for "room" starting from "Message 3" with 201
