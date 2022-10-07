
Feature: chat/search
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Can not search when not a participant
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant2" searches for "essa" in room "room" with 200

  Scenario: Search for message when being a participant
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant2" searches for "essa" in room "room" with 200
      | title                    | subline   | attributes.conversation | attributes.messageId |
      | participant1-displayname | Message 1 | room                    | Message 1            |

  Scenario: Can not search when being blocked by the lobby
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    When user "participant2" searches for "essa" in room "room" with 200
