Feature: command/monitor-room

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: No call in progress
    Given invoking occ with "talk:monitor:room abcdef"
    Then the command failed with exit code 1
    And the command output contains the text "Room with token abcdef not found"

  Scenario: From nothing to calling
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" gets room "room" with 200 (v4)

    Given invoking occ with "talk:monitor:room room-name:room"
    Then the command was successful
    And the command output contains the text "The conversation has 2 attendees with 0 sessions of which 0 are in the call."

    Given user "participant1" joins room "room" with 200 (v4)
    And invoking occ with "talk:monitor:room room-name:room"
    Then the command was successful
    And the command output contains the text "The conversation has 2 attendees with 1 sessions of which 0 are in the call."

    Given user "participant1" joins call "room" with 200 (v4)
    And invoking occ with "talk:monitor:room room-name:room"
    Then the command was successful
    And the command output contains the text "The conversation has 2 attendees with 1 sessions of which 1 are in the call."

    Given user "participant2" joins room "room" with 200 (v4)
    And user "participant2" joins call "room" with 200 (v4)
    And invoking occ with "talk:monitor:room room-name:room"
    Then the command was successful
    And the command output contains the text "The conversation has 2 attendees with 2 sessions of which 2 are in the call."
