Feature: create

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists
    Given user "participant2" is member of group "group1"

  Scenario: Create a room group room for participant1
    Given invoking occ with "talk:room:create room1 --user participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms
      | name  | type | participantType | participants |
      | room1 | 2    | 3               | participant1-displayname |

  Scenario: Create a room group room for participant1 as moderator
    Given invoking occ with "talk:room:create room1 --user participant1 --moderator participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms
      | name  | type | participantType | participants |
      | room1 | 2    | 2               | participant1-displayname |

  Scenario: Create a room group room for participant1 as owner
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms
      | name  | type | participantType | participants |
      | room1 | 2    | 1               | participant1-displayname |

  Scenario: Create a room public room for participant1 as owner group1 as users
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --group group1"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms
      | name  | type | participantType | participants |
      | room1 | 3    | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms
      | name  | type | participantType | participants |
      | room1 | 3    | 3               | participant1-displayname, participant2-displayname |

  Scenario: Create a room public room for participant1 as owner group1 as users with password and readonly
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --group group1 --readonly --password test"
    And the command output contains the text "Room successfully created"
    Then the command was successful
    And user "participant1" is participant of the following rooms
      | name  | type | readOnly | hasPassword | participantType | participants |
      | room1 | 3    | 1        | 1           | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms
      | name  | type | readOnly | hasPassword | participantType | participants |
      | room1 | 3    | 1        | 1           | 3               | participant1-displayname, participant2-displayname |
