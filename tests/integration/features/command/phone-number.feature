Feature: command/phone-number
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Configuring a phone number
    Given invoking occ with "talk:phone-number:add abc participant1"
    Then the command failed with exit code 1
    And the command output contains the text "Not a valid phone number abc. The format is invalid."

    Given invoking occ with "talk:phone-number:add +49-160-123-12-12 participant3"
    Then the command failed with exit code 1
    And the command output contains the text 'Invalid user "participant3" provided'

    Given invoking occ with "talk:phone-number:add +49-160-123-12-12 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number +491601231212 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:find --phone +491601231212"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number +491601231212 is assigned to participant1"

    Given invoking occ with "talk:phone-number:find --phone +49160123121234"
    Then the command failed with exit code 1
    And the command output contains the text "Phone number +49160123121234 could not be found"

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 0
    And the command output contains the text "participant1 has phone number +491601231212 assigned"

    Given invoking occ with "talk:phone-number:find --user participant2"
    Then the command failed with exit code 1
    And the command output contains the text "No phone number found for participant2"

    Given invoking occ with "talk:phone-number:add +49-160-123-1213 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number +491601231213 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 0
    And the command output contains the text "participant1 has the following phone numbers assigned:"
    And the command output contains the text "- +491601231212"
    And the command output contains the text "- +491601231213"

    Given invoking occ with "talk:phone-number:add +49-160-123-1212 participant2"
    Then the command failed with exit code 1
    And the command output contains the text "Phone number is already assigned to participant1"

    Given invoking occ with "talk:phone-number:add --force '+49-160-123-12-12' participant2"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number +491601231212 is now assigned to participant2"
    And the command output contains the text "Was assigned to participant1"

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 0
    And the command output contains the text "participant1 has phone number +491601231213 assigned"

    Given invoking occ with "talk:phone-number:remove +491601231213"
    Then the command failed with exit code 0

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 1
    And the command output contains the text "No phone number found for participant1"

    Given invoking occ with "talk:phone-number:remove-user participant2"
    Then the command failed with exit code 0

    Given invoking occ with "talk:phone-number:find --user participant2"
    Then the command failed with exit code 1
    And the command output contains the text "No phone number found for participant2"
