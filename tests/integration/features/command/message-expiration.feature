Feature: command/message-expiration
  Background:
    Given user "participant1" exists

  Scenario: Enable message expiration and check after expire
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3     |
      | roomName | room1 |
    And user "participant1" sends message "Message 1" to room "room1" with 201
    And user "participant1" set the message expiration to 3 of room "room1" with 200 (v4)
    And invoking occ with "talk:room:message-expiration room-name:room1 --seconds=3"
    And the command output contains the text "Message expiration enabled successful as 3 seconds."
    And user "participant1" sends message "Message 2" to room "room1" with 201
    And wait for 3 seconds
    And apply message expiration job manually
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room1 | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And invoking occ with "talk:room:message-expiration room-name:room1 --seconds=0"
    And the command output contains the text "Message expiration disabled successful."
    And user "participant1" sends message "Message 3" to room "room1" with 201
    And wait for 3 seconds
    And apply message expiration job manually
    And user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room1 | users     | participant1 | participant1-displayname | Message 3   | []                |               |
      | room1 | users     | participant1 | participant1-displayname | Message 1   | []                |               |
