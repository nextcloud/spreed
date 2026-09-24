Feature: conversation-1/archive
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Archiving and unarchiving
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | isArchived |
      | group room      | room         | 0          |
      | one-to-one room | participant2 | 0          |
    And user "participant1" archives room "one-to-one room" with 200 (v4)
    And user "participant1" archives room "group room" with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | isArchived |
      | group room      | room         | 1          |
      | one-to-one room | participant2 | 1          |
    And user "participant1" unarchives room "one-to-one room" with 200 (v4)
    And user "participant1" unarchives room "group room" with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | isArchived |
      | group room      | room         | 0          |
      | one-to-one room | participant2 | 0          |

  Scenario: Unarchive setting is validated
    When user "participant1" sets setting "conversations_unarchive" to "never" with 200 (v1)
    And user "participant1" sets setting "conversations_unarchive" to "mention" with 200 (v1)
    And user "participant1" sets setting "conversations_unarchive" to "always" with 200 (v1)
    And user "participant1" sets setting "conversations_unarchive" to "invalid" with 400 (v1)
    And user "participant1" sets setting "conversations_unarchive" to "always" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>conversations=>unarchive" set to "always"

  Scenario: Archived conversations stay archived by default
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" archives room "group room" with 200 (v4)
    When user "participant1" sends message "Hello @participant2" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 1          |

  Scenario: Unarchive on mention
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sets setting "conversations_unarchive" to "mention" with 200 (v1)
    And user "participant2" archives room "group room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 1          |
    When user "participant1" silent sends message "Silent @participant2" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 0          |
    When user "participant2" archives room "group room" with 200 (v4)
    And user "participant1" sends message "Hello @participant2" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 0          |

  Scenario: Unarchive on @all mention and on reply
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sets setting "conversations_unarchive" to "mention" with 200 (v1)
    And user "participant2" sends message "Message by participant2" to room "group room" with 201
    And user "participant2" archives room "group room" with 200 (v4)
    When user "participant1" sends message "Hello @all" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 0          |
    When user "participant2" archives room "group room" with 200 (v4)
    And user "participant1" sends reply "Reply" on message "Message by participant2" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 0          |

  Scenario: Own messages keep the conversation archived
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sets setting "conversations_unarchive" to "always" with 200 (v1)
    And user "participant2" archives room "group room" with 200 (v4)
    When user "participant2" sends message "Message by participant2" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 1          |

  Scenario: Unarchive on any message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sets setting "conversations_unarchive" to "always" with 200 (v1)
    And user "participant2" archives room "group room" with 200 (v4)
    When user "participant1" silent sends message "Silent message" to room "group room" with 201
    Then user "participant2" is participant of the following unordered rooms (v4)
      | id         | name | isArchived |
      | group room | room | 0          |
