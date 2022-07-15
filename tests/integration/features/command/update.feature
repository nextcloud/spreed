Feature: update

  Background:
    Given user "participant1" exists

  Scenario: Create a public room with message expiration time and update removing the expiration time
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --message-expiration=3"
    And user "participant1" is participant of the following rooms (v4)
      | name  |
      | room1 |
    And the command output contains the text "Room successfully created"
    And the command was successful
    And user "participant1" sends message "Message 1" to room "room1" with 201
    And wait for 3 seconds
    And apply message expiration job manually
    And user "participant1" sees the following messages in room "room1" with 200
    And invoking occ with "talk:room:update room-name:room1 --message-expiration=0"
    And the command output contains the text "Room successfully updated"
    And the command was successful
    And user "participant1" sends message "Message 2" to room "room1" with 201
    And wait for 3 seconds
    And apply message expiration job manually
    And user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | room1 | users     | participant1 | participant1-displayname | Message 2   | []                |               |
