Feature: conversation-3/tags
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Listing tags auto-provisions the two built-in tags
    When user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Other     | other     | 1         | 0         |

  Scenario: Creating, renaming and deleting a custom tag
    When user "participant1" creates tag "Work" with 201 (v4)
    And user "participant1" creates tag "Family" with 201 (v4)
    Then user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Work      | custom    | 1         | 0         |
      | Family    | custom    | 2         | 0         |
      | Other     | other     | 3         | 0         |
    When user "participant1" renames tag "Work" to "Projects" with 200 (v4)
    And user "participant1" deletes tag "Family" with 200 (v4)
    Then user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Projects  | custom    | 1         | 0         |
      | Other     | other     | 3         | 0         |

  Scenario: Tags are scoped per user
    Given user "participant1" creates tag "Work" with 201 (v4)
    When user "participant2" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Other     | other     | 1         | 0         |
    And user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Work      | custom    | 1         | 0         |
      | Other     | other     | 2         | 0         |

  Scenario: Rejects empty or duplicate tag names
    Given user "participant1" creates tag "Work" with 201 (v4)
    When user "participant1" creates tag "" with 400 (v4)
      | error | name |
    And user "participant1" creates tag "   " with 400 (v4)
      | error | name |
    And user "participant1" creates tag "Work" with 400 (v4)
      | error | name |

  Scenario: Built-in tags cannot be renamed or deleted
    Given user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Other     | other     | 1         | 0         |
    When user "participant1" renames tag "favorites" to "Starred" with 400 (v4)
      | error | type |
    And user "participant1" deletes tag "other" with 400 (v4)
      | error | type |

  Scenario: Updating a non-existent tag returns 404
    When user "participant1" renames tag "999999999" to "Nope" with 404 (v4)
    And user "participant1" deletes tag "999999999" with 404 (v4)

  Scenario: Rename rejects empty name and duplicate of another custom tag
    Given user "participant1" creates tag "Work" with 201 (v4)
    And user "participant1" creates tag "Family" with 201 (v4)
    When user "participant1" renames tag "Work" to "" with 400 (v4)
      | error | name |
    And user "participant1" renames tag "Work" to "Family" with 400 (v4)
      | error | name |

  Scenario: Collapsing and expanding a tag
    Given user "participant1" creates tag "Work" with 201 (v4)
    When user "participant1" collapses tag "Work" with 200 (v4)
    And user "participant1" collapses tag "favorites" with 200 (v4)
    Then user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 1         |
      | Work      | custom    | 1         | 1         |
      | Other     | other     | 2         | 0         |
    When user "participant1" expands tag "favorites" with 200 (v4)
    Then user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder | collapsed |
      | Favorites | favorites | 0         | 0         |
      | Work      | custom    | 1         | 1         |
      | Other     | other     | 2         | 0         |

  Scenario: Reordering tags
    Given user "participant1" creates tag "Work" with 201 (v4)
    And user "participant1" creates tag "Family" with 201 (v4)
    And user "participant1" creates tag "Hobbies" with 201 (v4)
    And user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder |
      | Favorites | favorites | 0         |
      | Work      | custom    | 1         |
      | Family    | custom    | 2         |
      | Hobbies   | custom    | 3         |
      | Other     | other     | 4         |
    When user "participant1" reorders tags to "favorites, Hobbies, Work, Family, other" with 200 (v4)
    Then user "participant1" sees the following tags with 200 (v4)
      | name      | type      | sortOrder |
      | Favorites | favorites | 0         |
      | Hobbies   | custom    | 1         |
      | Work      | custom    | 2         |
      | Family    | custom    | 3         |
      | Other     | other     | 4         |

  Scenario: Assigning and unassigning tags on a conversation
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" creates tag "Work" with 201 (v4)
    And user "participant1" creates tag "Family" with 201 (v4)
    When user "participant1" assigns tags "Work, Family" to room "group room" with 200 (v4)
    Then user "participant1" sees tags "Work, Family" on room "group room" with 200 (v4)
    When user "participant1" assigns tags "Work" to room "group room" with 200 (v4)
    Then user "participant1" sees tags "Work" on room "group room" with 200 (v4)
    When user "participant1" assigns tags "" to room "group room" with 200 (v4)
    Then user "participant1" sees tags "" on room "group room" with 200 (v4)

  Scenario: Tag assignments are scoped per participant
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" creates tag "Work" with 201 (v4)
    And user "participant2" creates tag "Personal" with 201 (v4)
    When user "participant1" assigns tags "Work" to room "group room" with 200 (v4)
    And user "participant2" assigns tags "Personal" to room "group room" with 200 (v4)
    Then user "participant1" sees tags "Work" on room "group room" with 200 (v4)
    And user "participant2" sees tags "Personal" on room "group room" with 200 (v4)

  Scenario: Assigning a tag owned by another user is silently dropped
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant2" creates tag "Foreign" with 201 (v4)
    And user "participant1" creates tag "Own" with 201 (v4)
    When user "participant1" assigns tags "Foreign, Own" to room "group room" with 200 (v4)
    Then user "participant1" sees tags "Own" on room "group room" with 200 (v4)

  Scenario: Non-existent tag ids are silently dropped
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" creates tag "Work" with 201 (v4)
    When user "participant1" assigns tags "999999999, Work" to room "group room" with 200 (v4)
    Then user "participant1" sees tags "Work" on room "group room" with 200 (v4)

  Scenario: Deleting a tag also removes it from assigned conversations
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" creates tag "Work" with 201 (v4)
    And user "participant1" creates tag "Family" with 201 (v4)
    And user "participant1" assigns tags "Work, Family" to room "group room" with 200 (v4)
    When user "participant1" deletes tag "Work" with 200 (v4)
    Then user "participant1" sees tags "Family" on room "group room" with 200 (v4)
