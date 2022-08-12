Feature: chat/limit-chat-history

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Participant cannot search by history previous join date in current room and in others room
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3     |
      | roomName | room1 |
    And user "participant1" sends message "abc" to room "room1" with 201
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    And user "participant1" sends message "def" to room "room1" with 201
    Then user "participant2" search for "abc" in room "room1"
    And user "participant2" search for "abc"
