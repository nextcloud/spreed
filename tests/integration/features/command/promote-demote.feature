Feature: command/promote-demote

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Promote a participant to moderator and demote them again
    Given invoking occ with "talk:room:create room1 --user participant1 --user participant2 --owner participant1"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 3               |
    When invoking occ with "talk:room:promote room-name:room1 participant2"
    Then the command output contains the text "Participants successfully promoted to moderators"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 2               |
    When invoking occ with "talk:room:demote room-name:room1 participant2"
    Then the command output contains the text "Participants successfully demoted to regular users"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 3               |

  Scenario: Promote a participant to owner and demote them to moderator and user again
    Given invoking occ with "talk:room:create room1 --user participant1 --user participant2 --owner participant1"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 3               |
    When invoking occ with "talk:room:promote room-name:room1 participant2 --owner"
    Then the command output contains the text "Participants successfully promoted to owners"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 1               |
    # The original owner is not touched, a conversation can have several owners
    And user "participant1" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 1               |
    When invoking occ with "talk:room:demote room-name:room1 participant2 --to-moderator"
    Then the command output contains the text "Participants successfully demoted to moderators"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 2               |
    When invoking occ with "talk:room:demote room-name:room1 participant2"
    Then the command output contains the text "Participants successfully demoted to regular users"
    And the command was successful
    And user "participant2" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 3               |

  Scenario: Promoting to moderator does not demote an owner
    Given invoking occ with "talk:room:create room1 --user participant1 --user participant2 --owner participant1"
    And the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 1               |
    When invoking occ with "talk:room:promote room-name:room1 participant1"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 1               |

  Scenario: Demoting the only owner is not restricted on the command line
    Given invoking occ with "talk:room:create room1 --user participant1 --user participant2 --owner participant1"
    And the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 1               |
    When invoking occ with "talk:room:demote room-name:room1 participant1"
    Then the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 3               |

  Scenario: Promoting a user that is not a participant fails
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1"
    And the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | participantType |
      | room1 | 1               |
    When invoking occ with "talk:room:promote room-name:room1 participant2 --owner"
    Then the command output contains the text "User 'participant2' is no participant."
    And the command failed with exit code 1
