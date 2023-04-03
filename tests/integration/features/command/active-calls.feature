Feature: command/active-calls

  Background:
    Given user "participant1" exists

  Scenario: No call in progress
    Given invoking occ with "talk:active-calls"
    Then the command was successful
    And the command output contains the text "No calls in progress"

  Scenario: Users only chatting
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" joins room "room" with 200 (v4)

    Given invoking occ with "talk:active-calls"
    Then the command was successful
    And the command output contains the text "No calls in progress"

    Then user "participant1" leaves room "room" with 200 (v4)


  Scenario: Call in progress
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |

    Then user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)

    Given invoking occ with "talk:active-calls"
    # It didn't really fail, it just has an exit code that is not 0
    Then the command failed with exit code 1
    And the command output contains the text "There are currently 1 calls in progress with 1 participants"

    Then user "participant1" leaves call "room" with 200 (v4)
    And user "participant1" leaves room "room" with 200 (v4)

    Given invoking occ with "talk:active-calls"
    Then the command was successful
    And the command output contains the text "No calls in progress"
