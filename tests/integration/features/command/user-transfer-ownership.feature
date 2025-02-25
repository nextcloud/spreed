Feature: command/user-transfer-ownership

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: Only transfer when moderator permissions
    Given user "participant1" creates room "one-to-one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant4" creates room "one-to-one former" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant1" creates room "user" (v4)
      | roomType | 3 |
      | roomName | user |
    And user "participant1" adds user "participant2" to room "user" with 200 (v4)
    Given user "participant1" creates room "moderator" (v4)
      | roomType | 2 |
      | roomName | moderator |
    And user "participant1" adds user "participant2" to room "moderator" with 200 (v4)
    And user "participant1" promotes "participant2" in room "moderator" with 200 (v4)
    Given user "participant2" creates room "owner" (v4)
      | roomType | 2 |
      | roomName | owner |
    Given user "participant1" creates room "self-joined" (v4)
      | roomType | 3 |
      | roomName | self-joined |
    And user "participant2" joins room "self-joined" with 200 (v4)
    And invoking occ with "talk:user:remove --user participant4"
    And invoking occ with "talk:user:transfer-ownership participant2 participant3"
    And the command output contains the text "Added or promoted user participant3 in 2 rooms."
    Then the command was successful
    And user "participant2" is participant of the following unordered rooms (v4)
      | id          | name         | type | participantType |
      | one-to-one  | participant1 | 1    | 1               |
      | one-to-one former | participant4-displayname | 5 | 1 |
      | user        | user         | 3    | 3               |
      | moderator   | moderator    | 2    | 2               |
      | owner       | owner        | 2    | 1               |
      | self-joined | self-joined  | 3    | 5               |
    And user "participant3" is participant of the following unordered rooms (v4)
      | id          | name         | type | participantType |
      | moderator   | moderator    | 2    | 2               |
      | owner       | owner        | 2    | 1               |

  Scenario: Also transfer without moderator permissions
    Given user "participant1" creates room "one-to-one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant4" creates room "one-to-one former" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant1" creates room "user" (v4)
      | roomType | 3 |
      | roomName | user |
    And user "participant1" adds user "participant2" to room "user" with 200 (v4)
    Given user "participant1" creates room "moderator" (v4)
      | roomType | 2 |
      | roomName | moderator |
    And user "participant1" adds user "participant2" to room "moderator" with 200 (v4)
    And user "participant1" promotes "participant2" in room "moderator" with 200 (v4)
    Given user "participant2" creates room "owner" (v4)
      | roomType | 2 |
      | roomName | owner |
    Given user "participant1" creates room "self-joined" (v4)
      | roomType | 3 |
      | roomName | self-joined |
    And user "participant2" joins room "self-joined" with 200 (v4)
    And invoking occ with "talk:user:remove --user participant4"
    And invoking occ with "talk:user:transfer-ownership --include-non-moderator participant2 participant3"
    And the command output contains the text "Added or promoted user participant3 in 3 rooms."
    Then the command was successful
    And user "participant2" is participant of the following unordered rooms (v4)
      | id          | name         | type | participantType |
      | one-to-one  | participant1 | 1    | 1               |
      | one-to-one former | participant4-displayname | 5 | 1 |
      | user        | user         | 3    | 3               |
      | moderator   | moderator    | 2    | 2               |
      | owner       | owner        | 2    | 1               |
      | self-joined | self-joined  | 3    | 5               |
    And user "participant3" is participant of the following unordered rooms (v4)
      | id          | name         | type | participantType |
      | user        | user         | 3    | 3               |
      | moderator   | moderator    | 2    | 2               |
      | owner       | owner        | 2    | 1               |

  Scenario: Remove source user on successful transfer
    Given user "participant1" creates room "one-to-one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant4" creates room "one-to-one former" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant1" creates room "user" (v4)
      | roomType | 3 |
      | roomName | user |
    And user "participant1" adds user "participant2" to room "user" with 200 (v4)
    Given user "participant1" creates room "moderator" (v4)
      | roomType | 2 |
      | roomName | moderator |
    And user "participant1" adds user "participant2" to room "moderator" with 200 (v4)
    And user "participant1" promotes "participant2" in room "moderator" with 200 (v4)
    Given user "participant2" creates room "owner" (v4)
      | roomType | 2 |
      | roomName | owner |
    Given user "participant1" creates room "self-joined" (v4)
      | roomType | 3 |
      | roomName | self-joined |
    And user "participant2" joins room "self-joined" with 200 (v4)
    And invoking occ with "talk:user:remove --user participant4"
    And invoking occ with "talk:user:transfer-ownership --remove-source-user participant2 participant3"
    And the command output contains the text "Added or promoted user participant3 in 2 rooms."
    Then the command was successful
    And user "participant2" is participant of the following unordered rooms (v4)
      | id          | name         | type | participantType |
      | one-to-one  | participant1 | 1    | 1               |
      | one-to-one former | participant4-displayname | 5 | 1 |
      | user        | user         | 3    | 3               |
      | self-joined | self-joined  | 3    | 5               |
    And user "participant3" is participant of the following unordered rooms (v4)
      | id          | name         | type | participantType |
      | moderator   | moderator    | 2    | 2               |
      | owner       | owner        | 2    | 1               |

  Scenario: Promote user if source had privileges
    Given user "participant1" creates room "moderator" (v4)
      | roomType | 2 |
      | roomName | moderator |
    And user "participant1" adds user "participant2" to room "moderator" with 200 (v4)
    And user "participant1" promotes "participant2" in room "moderator" with 200 (v4)
    Given user "participant2" creates room "owner" (v4)
      | roomType | 2 |
      | roomName | owner |
    Given user "participant2" creates room "moderator to owner" (v4)
      | roomType | 2 |
      | roomName | moderator to owner |
    And user "participant2" adds user "participant3" to room "moderator to owner" with 200 (v4)
    And user "participant2" promotes "participant3" in room "moderator to owner" with 200 (v4)
    Given user "participant1" creates room "from self-joined to moderator" (v4)
      | roomType | 3 |
      | roomName | from self-joined to moderator |
    And user "participant1" adds user "participant2" to room "from self-joined to moderator" with 200 (v4)
    And user "participant1" promotes "participant2" in room "from self-joined to moderator" with 200 (v4)
    Given user "participant1" creates room "from self-joined to user" (v4)
      | roomType | 3 |
      | roomName | from self-joined to user |
    And user "participant1" adds user "participant2" to room "from self-joined to user" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id                            | name                          | type | participantType |
      | moderator to owner            | moderator to owner            | 2    | 2               |
    And user "participant3" joins room "from self-joined to user" with 200 (v4)
    And user "participant3" is participant of the following unordered rooms (v4)
      | id                            | name                          | type | participantType |
      | moderator to owner            | moderator to owner            | 2    | 2               |
      | from self-joined to user      | from self-joined to user      | 3    | 5               |
    And user "participant3" joins room "from self-joined to moderator" with 200 (v4)
    And user "participant3" is participant of the following unordered rooms (v4)
      | id                            | name                          | type | participantType |
      | moderator to owner            | moderator to owner            | 2    | 2               |
      | from self-joined to user      | from self-joined to user      | 3    | 5               |
      | from self-joined to moderator | from self-joined to moderator | 3    | 5               |
    And invoking occ with "talk:user:transfer-ownership --include-non-moderator participant2 participant3"
    And the command output contains the text "Added or promoted user participant3 in 5 rooms."
    Then the command was successful
    And user "participant2" is participant of the following unordered rooms (v4)
      | id                            | name                          | type | participantType |
      | moderator                     | moderator                     | 2    | 2               |
      | owner                         | owner                         | 2    | 1               |
      | moderator to owner            | moderator to owner            | 2    | 1               |
      | from self-joined to moderator | from self-joined to moderator | 3    | 2               |
      | from self-joined to user      | from self-joined to user      | 3    | 3               |
    And user "participant3" is participant of the following unordered rooms (v4)
      | id                            | name                          | type | participantType |
      | moderator                     | moderator                     | 2    | 2               |
      | owner                         | owner                         | 2    | 1               |
      | moderator to owner            | moderator to owner            | 2    | 1               |
      | from self-joined to moderator | from self-joined to moderator | 3    | 2               |
      | from self-joined to user      | from self-joined to user      | 3    | 3               |
