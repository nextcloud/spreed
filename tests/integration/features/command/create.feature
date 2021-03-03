Feature: create

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists
    Given user "participant2" is member of group "group1"

  Scenario: Create a group room for participant1
    Given invoking occ with "talk:room:create room1 --user participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | name  | type | participantType | participants |
      | room1 | 2    | 3               | participant1-displayname |

  Scenario: Create a group room for participant1 as moderator
    Given invoking occ with "talk:room:create room1 --user participant1 --moderator participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | name  | type | participantType | participants |
      | room1 | 2    | 2               | participant1-displayname |

  Scenario: Create a group room for participant1 as owner
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | name  | type | participantType | participants |
      | room1 | 2    | 1               | participant1-displayname |

  Scenario: Create a public room for participant1 as owner group1 as users
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --group group1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | name  | type | participantType | participants |
      | room1 | 3    | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | name  | type | participantType | participants |
      | room1 | 3    | 3               | participant1-displayname, participant2-displayname |

  Scenario: Create a public room for participant1 as owner group1 as users with password and readonly and listable
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --group group1 --readonly --listable 2 --password test"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | name  | type | readOnly | hasPassword | participantType | participants |
      | room1 | 3    | 1        | 1           | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | name  | type | readOnly | hasPassword | participantType | participants |
      | room1 | 3    | 1        | 1           | 3               | participant1-displayname, participant2-displayname |



  Scenario: Create a public room for participant1 as owner with a description of 500 characters
    When invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --description 012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C"
    Then the command was successful
    And the command output contains the text "Room successfully created"
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | participantType | description |
      | room1 | 3    | 1               | 012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C |

  Scenario: Create a public room for participant1 as owner with a description of more than 500 characters
    When invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --description 012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C1"
    Then the command failed with exit code 1
    And the command output contains the text "Invalid room description"

  Scenario: Create a public room for participant1 as owner with a description of 500 multibyte characters
    When invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --description ०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च"
    Then the command was successful
    And the command output contains the text "Room successfully created"
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | participantType | description |
      | room1 | 3    | 1               | ०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च |

  Scenario: Create a public room for participant1 as owner with a description of more than 500 multibyte characters
    When invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --description ०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०"
    Then the command failed with exit code 1
    And the command output contains the text "Invalid room description"
