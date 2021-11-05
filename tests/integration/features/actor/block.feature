Feature: actor/block

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: user 1 block user 2 and list all blocked users
    When user "participant1" unblock user "participant2" with 200 (v1)
    And user "participant1" unblock user "participant3" with 200 (v1)
    And user "participant1" list all blocked with 200 (v1)
    And user "participant1" list all blocked users with 200 (v1)
    And user "participant1" block user "participant2" with 200 (v1)
    And user "participant1" list all blocked with 200 (v1)
      | actorType  | actorId      | blockedType  | blockedId    |
      | users      | participant1 | users        | participant2 |
    And user "participant1" list all blocked users with 200 (v1)
      | actorType  | actorId      | blockedType  | blockedId    |
      | users      | participant1 | users        | participant2 |
    And user "participant1" block user "participant3" with 200 (v1)
    And user "participant1" list all blocked with 200 (v1)
      | actorType  | actorId      | blockedType  | blockedId    |
      | users      | participant1 | users        | participant2 |
      | users      | participant1 | users        | participant3 |
    And user "participant1" list all blocked users with 200 (v1)
      | actorType  | actorId      | blockedType  | blockedId    |
      | users      | participant1 | users        | participant2 |
      | users      | participant1 | users        | participant3 |
    And user "participant1" unblock user "participant2" with 200 (v1)
    And user "participant1" unblock user "participant3" with 200 (v1)
    And user "participant1" list all blocked with 200 (v1)
    And user "participant1" list all blocked users with 200 (v1)