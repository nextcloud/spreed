Feature: command/command-list

  Scenario: Get the output options
    Given invoking occ with "talk:command:list --help"
    Then the command was successful
    And the command output contains the text "Output format"

  Scenario: List all available commands as plain text and verify if contains the help command as markdown table (default)
    Given invoking occ with "talk:command:list"
    Then the command was successful
    And the command output contains the text "| help"

  Scenario: List all available commands as plain text and verify if contains the help command as markdown table
    Given invoking occ with "talk:command:list --output=plain"
    Then the command was successful
    And the command output contains the text "| help"

  Scenario: List all available commands as json and verify if contains the help command as json format
    Given invoking occ with "talk:command:list --output=json"
    Then the command was successful
    And the command output contains the text:
      """
      "name":"talk","command":"help","script":"help"
      """
