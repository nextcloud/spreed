Feature: conversation/ban
    Background:
        Given user "participant1" exists
        Given user "participant2" exists
        And guest accounts can be created
        And user "user-guest@example.com" is a guest account user

    Scenario: Ban a user from a room
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant2" joins room "room" with 200 (v4)
        When user "participant1" bans user "participant2" from room "room" with 200 (v4)
        Then user "participant2" joins room "room" with 403 (v4)