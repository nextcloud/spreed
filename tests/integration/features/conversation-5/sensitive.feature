Feature: conversation-5/sensitive
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Mark as (in-)sensitive
    Given user "participant1" creates room "group room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" sends message "Message 1" to room "group room" with 201
    And user "participant1" sends message "Message 2" to room "one-to-one room" with 201
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | lastMessage | isSensitive |
      | group room      | room         | Message 1   | 0           |
      | one-to-one room | participant2 | Message 2   | 0           |
    And user "participant1" marks room "one-to-one room" as sensitive with 200 (v4)
    And user "participant1" marks room "group room" as sensitive with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | lastMessage | isSensitive |
      | group room      | room         | UNSET       | 1           |
      | one-to-one room | participant2 | UNSET       | 1           |
    And user "participant1" marks room "one-to-one room" as insensitive with 200 (v4)
    And user "participant1" marks room "group room" as insensitive with 200 (v4)
    And user "participant1" is participant of the following unordered rooms (v4)
      | id              | name         | lastMessage | isSensitive |
      | group room      | room         | Message 1   | 0           |
      | one-to-one room | participant2 | Message 2   | 0           |

  Scenario: Message preview hidden in sensitive rooms for notifications
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" marks room "one-to-one room" as sensitive with 200 (v4)
    And user "participant1" sends message "Secret message" to room "one-to-one room" with 201
    And user "participant1" sends message "Secret mention for @participant2" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                          | message |
      | spreed | chat        | one-to-one room | Someone mentioned you in a private conversation  |         |
      | spreed | chat        | one-to-one room | You received a message in a private conversation |         |
    When user "participant2" marks room "one-to-one room" as insensitive with 200 (v4)
    And user "participant1" sends message "Nonsecret message" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                                        | subject                                                          | message                                      |
      | spreed | chat        | one-to-one room/Nonsecret message                | participant1-displayname sent you a private message              | Nonsecret message                            |
      | spreed | chat        | one-to-one room/Secret mention for @participant2 | participant1-displayname mentioned you in a private conversation | Secret mention for @participant2-displayname |
      | spreed | chat        | one-to-one room/Secret message                   | participant1-displayname sent you a private message              | Secret message                               |
