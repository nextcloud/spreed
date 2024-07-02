Feature: conversation/ban
    Background:
        Given user "participant1" exists
        Given user "participant2" exists
        Given user "participant3" exists
        And guest accounts can be created
        And user "user-guest@example.com" is a guest account user

    Scenario: Moderator banning and unbanning multiple users
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant3" joins room "room" with 200 (v4)
        And user "participant1" bans user "participant2" from room "room" with 200 (v1)
            | internalNote | BannedP1 |
        And user "participant1" bans user "participant3" from room "room" with 200 (v1)
            | internalNote | BannedP2 |
        #And user "participant2" joins room "room" with 403 (v4)
        #And user "participant3" joins room "room" with 403 (v4)
        And user "participant1" unbans user "participant2" from room "room" with 200 (v1)
        And user "participant1" unbans user "participant3" from room "room" with 200 (v1)
    
    # Scenario: Moderator banning and unbanning guest account
    #     Given user "participant1" creates room "room" (v4)
    #     | roomType | 3 |
    #     | roomName | room |
    #     And user "user-guest@example.com" joins room "room" with 200 (v4)
    #     And user "participant1" bans user "user-guest@example.com" from room "room" with 200 (v1)
    #         | internalNote | BannedG1 |
    #     And user "participant1" unbans user "user-guest@exmaple.com" from room "room" with 200 (v1)