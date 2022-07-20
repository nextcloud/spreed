Feature: command/update

  Background:
    Given user "participant1" exists

  Scenario: Create a public room with message expiration time and update removing the expiration time
    Given invoking occ with "talk:room:create room1 --user participant1 --owner participant1 --public --message-expiration=3"
    And user "participant1" is participant of the following rooms (v4)
      | name  | messageExpiration |
      | room1 | 3                 |
    And the command output contains the text "Room successfully created"
    And the command was successful
    And invoking occ with "talk:room:update room-name:room1 --message-expiration=0"
    And the command output contains the text "Room successfully updated"
    And the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | messageExpiration |
      | room1 | 0                 |
    And invoking occ with "talk:room:update room-name:room1 --message-expiration=4"
    And the command output contains the text "Room successfully updated"
    And the command was successful
    And user "participant1" is participant of the following rooms (v4)
      | name  | messageExpiration |
      | room1 | 4                 |
