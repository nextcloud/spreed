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
            | internalNote | BannedP2 |
        And user "participant1" bans user "participant3" from room "room" with 200 (v1)
            | internalNote | BannedP3 |
        And user "participant1" sees the following bans in room "room" with 200 (v1)
          | actorType | actorId      | bannedType | bannedId     | internalNote |
          | users     | participant1 | users      | participant2 | BannedP2     |
          | users     | participant1 | users      | participant3 | BannedP3     |
        And user "participant2" joins room "room" with 403 (v4)
        And user "participant3" joins room "room" with 403 (v4)
        And user "participant1" unbans user "participant2" from room "room" with 200 (v1)
        And user "participant1" unbans user "participant3" from room "room" with 200 (v1)
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant3" joins room "room" with 200 (v4)

    Scenario: Users trying to ban moderator
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant3" joins room "room" with 200 (v4)
        And user "participant2" bans user "participant1" from room "room" with 403 (v1)
            | internalNote | BannedP1 |
        And user "participant3" bans user "participant1" from room "room" with 403 (v1)
            | internalNote | BannedP1 |

    Scenario: Users trying to ban other users
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant3" joins room "room" with 200 (v4)
        And user "participant2" bans user "participant3" from room "room" with 403 (v1)
            | internalNote | BannedP3 |
        And user "participant3" bans user "participant2" from room "room" with 403 (v1)
            | internalNote | BannedP2 |

    Scenario: User trying to ban themselves
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant1" joins room "room" with 200 (v4)
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant2" bans user "participant2" from room "room" with 403 (v1)
            | internalNote | BannedP2 |

    Scenario: Moderator trying to ban an invalid user
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant1" bans user "participant3" from room "room" with 404 (v1)
            | internalNote | BannedInvalid |

    Scenario: Moderator trying to ban themselves
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant1" joins room "room" with 200 (v4)
        And user "participant1" bans user "participant1" from room "room" with 400 (v1)
            | internalNote | BannedP1 |
    
    Scenario: Moderator trying to ban moderator
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant1" joins room "room" with 200 (v4)
        And user "participant2" joins room "room" with 200 (v4)
        And user "participant1" adds user "participant2" to room "room" with 200 (v4)
        And user "participant1" promotes "participant2" in room "room" with 200 (v4)
        And user "participant1" bans user "participant2" from room "room" with 400 (v1)
            | internalNote | BannedP2 |
        And user "participant1" demotes "participant2" in room "room" with 200 (v4)
        And user "participant1" bans user "participant2" from room "room" with 200 (v1)
            | internalNote | BannedP2 |
        And user "participant1" sees the following bans in room "room" with 200 (v1)
          | actorType | actorId      | bannedType | bannedId     | internalNote |
          | users     | participant1 | users      | participant2 | BannedP2     |



    




