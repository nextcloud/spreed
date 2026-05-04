Feature: conversation-4/meet
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    And user "participant1" sets the Talk profile visibility to "show"

  Scenario: Guest creates a meet room
    Given guest creates meet room "room" for "participant1" with 201 (v4)
    Then user "participant1" is participant of room "room" (v4)
      | name            | type | lobbyState |
      | Contact request | 3    | 1          |

  Scenario: Guest creates a meet room with display name
    Given guest creates meet room "room" for "participant1" with 201 (v4)
      | displayName | Guest User |
    Then user "participant1" is participant of room "room" (v4)
      | name                            | type | lobbyState |
      | Contact request from Guest User | 3    | 1          |

  Scenario: Guest creates a meet room with message and display name
    Given guest creates meet room "room" for "participant1" with 201 (v4)
      | message     | Hello, I need help! |
      | displayName | Guest User          |
    Then user "participant1" is participant of room "room" (v4)
      | name                            | type | lobbyState |
      | Contact request from Guest User | 3    | 1          |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId              | actorDisplayName | message             | messageParameters |
      | room | guests    | MEET_GUEST_ACTOR_ID  | Guest User       | Hello, I need help! | []                |

  Scenario: Guest creates a meet room with message but no display name
    Given guest creates meet room "room" for "participant1" with 201 (v4)
      | message | Hello there |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId              | actorDisplayName | message     | messageParameters |
      | room | guests    | MEET_GUEST_ACTOR_ID  |                  | Hello there | []                |

  Scenario: Guest creates a meet room without message does not leave a guest participant
    Given guest creates meet room "room" for "participant1" with 201 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | participantType |
      | users     | 1               |

  Scenario: Guest creates a meet room for a non-existing user
    Given guest creates meet room "room" for "non-existing-user" with 404 (v4)

  Scenario: Guest creates a meet room for a user that cannot create conversations
    Given the following "spreed" app config is set
      | start_conversations | ["admin"] |
    Given guest creates meet room "room" for "participant1" with 403 (v4)

  Scenario: Guest cannot create a meet room when Talk profile is hidden
    Given user "participant1" sets the Talk profile visibility to "hide"
    Given guest creates meet room "room" for "participant1" with 404 (v4)
