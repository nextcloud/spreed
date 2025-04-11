Feature: command/monitor-calls

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: No call in progress
    Given invoking occ with "talk:monitor:calls"
    Then the command was successful
    And the command output contains the text "No calls in progress"

  Scenario: Users only chatting
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" joins room "room" with 200 (v4)

    Given invoking occ with "talk:monitor:calls"
    Then the command was successful
    And the command output contains the text "No calls in progress"

    Then user "participant1" leaves room "room" with 200 (v4)


  Scenario: Calls in progress
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |

    Then user "participant1" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)

    Given invoking occ with "talk:monitor:calls"
    Then the command was successful
    And the command output contains the text "There are currently 1 calls in progress with 1 participants"
    And the command output contains the list entry '"ROOM(room)"' with value '1'

    When user "participant3" creates room "room2" (v4)
      | roomType | 1 |
      | invite | participant2 |
    Given user "participant2" creates room "room2" with 200 (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant2" gets room "room2" with 200 (v4)

    And user "participant2" joins room "room2" with 200 (v4)
    And user "participant3" joins room "room2" with 200 (v4)
    And user "participant2" joins call "room2" with 200 (v4)
    And user "participant3" joins call "room2" with 200 (v4)

    Given invoking occ with "talk:monitor:calls"
    Then the command was successful
    And the command output contains the text "There are currently 2 calls in progress with 3 participants"
    And the command output contains the list entry '"ROOM(room)"' with value '1'
    And the command output contains the list entry '"ROOM(room2)"' with value '2'

    Then user "participant1" leaves call "room" with 200 (v4)
    And user "participant1" leaves room "room" with 200 (v4)

    Given invoking occ with "talk:monitor:calls"
    Then the command was successful
    And the command output contains the text "There are currently 1 calls in progress with 2 participants"
    And the command output contains the list entry '"ROOM(room2)"' with value '2'
