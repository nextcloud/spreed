Feature: conversation-5/mute
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Mark as (un-)mute
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | muteUntil |
      | group room      | room         | 0         |
    And user "participant1" mutes room "group room" until OFFSET(3600) with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | muteUntil         |
      | group room      | room         | GREATER_THAN_ZERO |
    And user "participant1" unmutes room "group room" with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | muteUntil |
      | group room      | room         | 0         |

  Scenario: No notifications for muted rooms
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Message1" to room "one-to-one room" with 201
    And user "participant2" mutes room "one-to-one room" until OFFSET(3600) with 200 (v4)
    And user "participant1" sends message "Message with mention for @participant2" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                              | message  |
      | spreed | chat        | one-to-one room/Message1 | participant1-displayname sent you a private message  | Message1 |
    And user "participant2" unmutes room "one-to-one room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                              | message  |
      | spreed | chat        | one-to-one room/Message1 | participant1-displayname sent you a private message  | Message1 |

  Scenario: Participant1 calls while participant2 has muted the conversation
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant2" mutes room "room" until OFFSET(3600) with 200 (v4)
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
