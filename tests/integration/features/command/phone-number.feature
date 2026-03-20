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
    And the command output contains the text "Phone number 491601231212 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:find --phone 491601231212"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 491601231212 is assigned to participant1"

    Given invoking occ with "talk:phone-number:find --phone 49160123121234"
    Then the command failed with exit code 1
    And the command output contains the text "Phone number 49160123121234 could not be found"

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 0
    And the command output contains the text "participant1 has phone number 491601231212 assigned"

    Given invoking occ with "talk:phone-number:find --user participant2"
    Then the command failed with exit code 1
    And the command output contains the text "No phone number found for participant2"

    Given invoking occ with "talk:phone-number:add +49-160-123-1213 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 491601231213 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 0
    And the command output contains the text "participant1 has the following phone numbers assigned:"
    And the command output contains the text "- 491601231212"
    And the command output contains the text "- 491601231213"

    Given invoking occ with "talk:phone-number:add +49-160-123-1212 participant2"
    Then the command failed with exit code 1
    And the command output contains the text "Phone number is already assigned to participant1"

    Given invoking occ with "talk:phone-number:add --force '+49-160-123-12-12' participant2"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 491601231212 is now assigned to participant2"
    And the command output contains the text "Was assigned to participant1"

    Given invoking occ with "talk:phone-number:add 23 participant2"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 23 is now assigned to participant2"

    Given invoking occ with "talk:phone-number:find --user participant2"
    Then the command failed with exit code 0
    And the command output contains the text "participant2 has the following phone numbers assigned:"
    And the command output contains the text "- 491601231212"
    And the command output contains the text "- 23"

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 0
    And the command output contains the text "participant1 has phone number 491601231213 assigned"

    Given invoking occ with "talk:phone-number:remove 491601231213"
    Then the command failed with exit code 0

    Given invoking occ with "talk:phone-number:find --user participant1"
    Then the command failed with exit code 1
    And the command output contains the text "No phone number found for participant1"

    Given invoking occ with "talk:phone-number:remove-user participant2"
    Then the command failed with exit code 0

    Given invoking occ with "talk:phone-number:find --user participant2"
    Then the command failed with exit code 1
    And the command output contains the text "No phone number found for participant2"

  Scenario: Phone number validation
    Given invoking occ with "config:system:set default_phone_region --value DE"
    # Invalid phone number with +
    Given invoking occ with "talk:phone-number:add +4911223344 participant1"
    Then the command failed with exit code 1
    And the command output contains the text "Not a valid phone number +4911223344. The format is invalid."

    # Valid German number
    Given invoking occ with "talk:phone-number:add 004971112347 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 4971112347 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:add +4971112346 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 4971112346 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:add 4971112345 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 4971112345 is now assigned to participant1"

    Given invoking occ with "talk:phone-number:add 071112348 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 4971112348 is now assigned to participant1"

    # 01122 is not a valid prefix in Germany
    Given invoking occ with "talk:phone-number:add 011223344 participant1"
    Then the command failed with exit code 1
    And the command output contains the text "Not a valid phone number 011223344. The format is invalid."

    # Local PBX
    Given invoking occ with "talk:phone-number:add 3001 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 3001 is now assigned to participant1"

    # Local number 030 Berlin, but 01 is too short
    Given invoking occ with "talk:phone-number:add 03001 participant1"
    Then the command failed with exit code 1
    And the command output contains the text "Not a valid phone number 03001. The format is invalid."

    # Invalid German number but seen as local PBX
    Given invoking occ with "talk:phone-number:add 4911223344 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 4911223344 is now assigned to participant1"

    # Valid US number
    Given invoking occ with "talk:phone-number:add 00112345678901 participant1"
    Then the command failed with exit code 0
    And the command output contains the text "Phone number 12345678901 is now assigned to participant1"
