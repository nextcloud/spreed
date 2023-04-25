Feature: command/command-list

  Scenario: Get the output options
    Given invoking occ with "talk:command:list --help"
    Then the command was successful
    And the command output contains the text "Output format"

  Scenario: List all available commands as plain text (default)
    Given invoking occ with "talk:command:list"
    Then the command was successful
    And the command output contains the text "Response values: 0 - No one"

  Scenario: List all available commands as plain text
    Given invoking occ with "talk:command:list --output=plain"
    Then the command was successful
    And the command output contains the text "Response values: 0 - No one"

  Scenario: List all available commands as json
    Given invoking occ with "talk:command:list --output=json"
    Then the command was successful
    And the command output contains the text "[{"
