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

  Scenario: Quoting a message from before via direct API call must not work
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3     |
      | roomName | room1 |
    And user "participant1" sends message "Message 1" to room "room1" with 201
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    Then user "participant2" sends reply "Message 1-1" on message "Message 1" to room "room1" with 400

  Scenario: A request for older history must return 304 like when there is actually no history at all
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3     |
      | roomName | room1 |
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    And user "participant2" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage |
      | room1 | users     | participant1 | participant1-displayname | user_added    |
    Then user "participant2" sees the following messages in room "room1" before message "user_added" with 304 (v1)
