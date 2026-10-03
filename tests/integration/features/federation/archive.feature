Feature: federation/archive
  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    Given user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: Federated conversation is unarchived on mention and reply but not on own message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" sets setting "conversations_unarchive" to "mention" with 200 (v1)
    And user "participant2" archives room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    When user "participant1" sends message "Message 1" to room "room" with 201
    And using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id          | isArchived |
      | LOCAL::room | 1          |
    And using server "LOCAL"
    When user "participant1" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "room" with 201
    And using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id          | isArchived |
      | LOCAL::room | 0          |
    When user "participant2" archives room "LOCAL::room" with 200 (v4)
    And user "participant2" sends message "Message by participant2" to room "LOCAL::room" with 201
    Then user "participant2" is participant of the following rooms (v4)
      | id          | isArchived |
      | LOCAL::room | 1          |
    And using server "LOCAL"
    And user "participant1" sends reply "Reply" on message "Message by participant2" to room "room" with 201
    And using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id          | isArchived |
      | LOCAL::room | 0          |

  Scenario: Federated conversation is unarchived on any message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant2" sets setting "conversations_unarchive" to "always" with 200 (v1)
    And user "participant2" archives room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    When user "participant1" silent sends message "Silent message" to room "room" with 201
    And using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id          | isArchived |
      | LOCAL::room | 0          |
