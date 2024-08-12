Feature: conversation/ban
    Background:
        Given user "participant1" exists
        Given user "participant2" exists
        Given user "participant3" exists
        Given group "group1" exists

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
          | moderatorActorType | moderatorActorId | moderatorDisplayName     | bannedActorType | bannedActorId | bannedDisplayName        | internalNote |
          | users              | participant1     | participant1-displayname | users           | participant2  | participant2-displayname | BannedP2     |
          | users              | participant1     | participant1-displayname | users           | participant3  | participant3-displayname | BannedP3     |
        And user "participant2" joins room "room" with 403 (v4)
        And user "participant3" joins room "room" with 403 (v4)
        And user "participant1" removes "participant2" from room "room" with 404 (v4)
        And user "participant1" removes "participant3" from room "room" with 404 (v4)
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
        And user "participant1" bans user "participant3" from room "room" with 200 (v1)
            | internalNote | BannedInvalid |

    Scenario: Moderator trying to ban themselves
        Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        And user "participant1" joins room "room" with 200 (v4)
        And user "participant1" bans user "participant1" from room "room" with 400 (v1)
            | internalNote | BannedP1 |
            | error | self |

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
            | error | moderator |
        And user "participant1" demotes "participant2" in room "room" with 200 (v4)
        And user "participant1" bans user "participant2" from room "room" with 200 (v1)
            | internalNote | BannedP2 |
        And user "participant1" sees the following bans in room "room" with 200 (v1)
          | moderatorActorType | moderatorActorId | moderatorDisplayName     | bannedActorType | bannedActorId | bannedDisplayName        | internalNote |
          | users              | participant1     | participant1-displayname | users           | participant2  | participant2-displayname | BannedP2     |

  Scenario: Banned user can not join conversation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant2" joins room "room" with 200 (v4)
    Then user "participant2" leaves room "room" with 200 (v4)
    When user "participant1" bans user "participant2" from room "room" with 200 (v1)
      | internalNote | BannedP2 |
    Then user "participant2" joins room "room" with 403 (v4)

  Scenario: Banned user can not send reactions or messages
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "room" with 201
    When user "participant1" bans user "participant2" from room "room" with 200 (v1)
      | internalNote | BannedP2 |
    And user "participant2" sends message "Message 2" to room "room" with 404
    And user "participant2" react with "👍" on message "Message 1" to room "room" with 404

  Scenario: Banning a guest bans their IP as well
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "guest" joins room "room" with 200 (v4) session name "guest1"
    And user "participant1" bans guest "guest1" from room "room" with 200 (v1)
      | internalNote | Banned guest |
    And user "guest" joins room "room" with 403 (v4) session name "guest2"
    And user "participant1" sees the following bans in room "room" with 200 (v1)
      | moderatorActorType | moderatorActorId | moderatorDisplayName     | bannedActorType | bannedActorId   | bannedDisplayName   | internalNote |
      | users              | participant1     | participant1-displayname | guests          | SESSION(guest1) | SESSION(guest1)     | Banned guest |
      | users              | participant1     | participant1-displayname | ip              | LOCAL_IP        | LOCAL_IP            | Banned guest |

  Scenario: Banned user cannot be added to the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" bans user "participant2" from room "room" with 200 (v1)
      | internalNote | BannedP2 |
    And user "participant1" sees the following bans in room "room" with 200 (v1)
      | moderatorActorType | moderatorActorId | moderatorDisplayName     | bannedActorType | bannedActorId | bannedDisplayName        | internalNote |
      | users              | participant1     | participant1-displayname | users           | participant2  | participant2-displayname | BannedP2     |
    And user "participant1" adds user "participant2" to room "room" with 400 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId                     |
      | users      | participant1                |
    And user "participant1" unbans user "participant2" from room "room" with 200 (v1)
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId                     |
      | users      | participant1                |
      | users      | participant2                |

  Scenario: Banned user is not added when adding a group they are a member of
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" is member of group "group1"
    And user "participant1" bans user "participant2" from room "room" with 200 (v1)
      | internalNote | BannedP2 |
    And user "participant1" sees the following bans in room "room" with 200 (v1)
      | moderatorActorType | moderatorActorId | moderatorDisplayName     | bannedActorType | bannedActorId | bannedDisplayName        | internalNote |
      | users              | participant1     | participant1-displayname | users           | participant2  | participant2-displayname | BannedP2     |
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId                     |
      | users      | participant1                |
      | groups     | group1                      |

  Scenario: Banned user is not added when adding them to a group that is member in a room they are banned in
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" bans user "participant2" from room "room" with 200 (v1)
      | internalNote | BannedP2 |
    And user "participant1" sees the following bans in room "room" with 200 (v1)
      | moderatorActorType | moderatorActorId | moderatorDisplayName     | bannedActorType | bannedActorId | bannedDisplayName        | internalNote |
      | users              | participant1     | participant1-displayname | users           | participant2  | participant2-displayname | BannedP2     |
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId                     |
      | users      | participant1                |
      | groups     | group1                      |
    And user "participant2" is member of group "group1"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId                     |
      | users      | participant1                |
      | groups     | group1                      |

  Scenario: Can not ban in one-to-one conversations
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" bans user "participant2" from room "one-to-one room" with 400 (v1)
      | error | room |

  Scenario: Invalid banned actor type
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" bans range "participant2" from room "room" with 400 (v1)
      | error | bannedActor |

  Scenario: Invalid IP address
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" bans ip "participant2" from room "room" with 400 (v1)
      | error | bannedActor |

  Scenario: Invalid IP address range
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" bans ip "127.0.0.1/64" from room "room" with 400 (v1)
      | error | bannedActor |

  Scenario: Test valid IP bans
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" bans ip "127.0.0.1" from room "room" with 200 (v1)
    And user "participant1" bans ip "127.0.0.1/24" from room "room" with 200 (v1)
    And user "participant1" bans ip "127.0.0.1/32" from room "room" with 200 (v1)
    And user "participant1" bans ip "::1" from room "room" with 200 (v1)
    And user "participant1" bans ip "::1/32" from room "room" with 200 (v1)
    And user "participant1" bans ip "::1/64" from room "room" with 200 (v1)
