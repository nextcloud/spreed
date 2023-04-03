Feature: command/create

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists
    Given user "participant2" is member of group "group1"

  Scenario: Create a group room for participant1
    Given invoking occ with "talk:room:create room1 --user participant1"
    And the command output contains the text "Room token:"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | participantType |
      | room1 | 2    | 3               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 3               |

  Scenario: Create a group room for participant1 as moderator
    Given invoking occ with "talk:room:create room1 --user participant1 --moderator participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | participantType |
      | room1 | 2    | 2               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 2               |

  Scenario: Create a group room for participant1 as owner
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | participantType |
      | room1 | 2    | 1               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Create a public room for participant1 as owner group1 as users
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --group group1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | participantType |
      | room1 | 3    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | name  | type | participantType |
      | room1 | 3    | 3               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |

  Scenario: Create a public room for participant1 as owner group1 as users with password and readonly and listable
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --group group1 --readonly --listable 2 --password test"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | type | readOnly | hasPassword | participantType |
      | room1 | 3    | 1        | 1           | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | name  | type | readOnly | hasPassword | participantType |
      | room1 | 3    | 1        | 1           | 3               |
    And user "participant1" sees the following attendees in room "room1" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |



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

  Scenario: Create a public room with message expiration time
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --message-expiration=3"
    And the command output contains the text "Room successfully created"
    And the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | messageExpiration |
      | room1 | 3                 |
