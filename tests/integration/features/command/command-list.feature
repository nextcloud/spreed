Feature: command/command-list

  Scenario: List all available commands as plain text
    Given invoking occ with "talk:command:list"
    Then the command was successful
    And the command output contains the text "Response values: 0 - No one"

  Scenario: List all available commands as json
    Given invoking occ with "talk:command:list --output=json"
    Then the command was successful
    And the command output contains the text "[{"
