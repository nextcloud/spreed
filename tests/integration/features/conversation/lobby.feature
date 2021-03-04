Feature: conversation/lobby

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: set lobby state in group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    And user "participant1" sets lobby state for room "room" to "no lobby" with 200 (v4)
    And user "participant2" sets lobby state for room "room" to "non moderators" with 200 (v4)
    And user "participant2" sets lobby state for room "room" to "no lobby" with 200 (v4)
    And user "participant3" sets lobby state for room "room" to "non moderators" with 403 (v4)
    And user "participant3" sets lobby state for room "room" to "no lobby" with 403 (v4)

  Scenario: set lobby state in public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    And user "participant1" sets lobby state for room "room" to "no lobby" with 200 (v4)
    And user "participant2" sets lobby state for room "room" to "non moderators" with 200 (v4)
    And user "participant2" sets lobby state for room "room" to "no lobby" with 200 (v4)
    And user "participant3" sets lobby state for room "room" to "non moderators" with 403 (v4)
    And user "participant3" sets lobby state for room "room" to "no lobby" with 403 (v4)
    And user "participant4" sets lobby state for room "room" to "non moderators" with 403 (v4)
    And user "participant4" sets lobby state for room "room" to "no lobby" with 403 (v4)
    And user "guest" sets lobby state for room "room" to "non moderators" with 401 (v4)
    And user "guest" sets lobby state for room "room" to "no lobby" with 401 (v4)
    And user "guest2" sets lobby state for room "room" to "non moderators" with 401 (v4)
    And user "guest2" sets lobby state for room "room" to "no lobby" with 401 (v4)

  Scenario: set lobby state in one-to-one room
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" sets lobby state for room "room" to "non moderators" with 400 (v4)
    And user "participant1" sets lobby state for room "room" to "no lobby" with 400 (v4)
    And user "participant2" sets lobby state for room "room" to "non moderators" with 400 (v4)
    And user "participant2" sets lobby state for room "room" to "no lobby" with 400 (v4)

  Scenario: set lobby state in file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant2" joins room "file welcome (2).txt room" with 200 (v4)
    When user "participant1" sets lobby state for room "file welcome.txt room" to "non moderators" with 403 (v4)
    And user "participant1" sets lobby state for room "file welcome.txt room" to "no lobby" with 403 (v4)
    And user "participant2" sets lobby state for room "file welcome (2).txt room" to "non moderators" with 403 (v4)
    And user "participant2" sets lobby state for room "file welcome (2).txt room" to "no lobby" with 403 (v4)

  Scenario: set lobby state of a room not joined to
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant2" sets lobby state for room "room" to "non moderators" with 404 (v4)
    And user "participant2" sets lobby state for room "room" to "no lobby" with 404 (v4)



  Scenario: participants can join the room when the lobby is active
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)

  Scenario: participants can join a password protected room when the lobby is active
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "room" with 200
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
      | password | foobar |
    And user "guest" joins room "room" with 200 (v4)
      | password | foobar |
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)
      | password | foobar |

  Scenario: lobby prevents chats for non moderators
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant2" sends message "Message 2" to room "room" with 201
    And user "participant3" sends message "Message 3" to room "room" with 412
    And user "participant4" sends message "Message 4" to room "room" with 412
    And user "guest" sends message "Message 5" to room "room" with 201
    And user "guest2" sends message "Message 6" to room "room" with 412
    And user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | guests    | guest        |                          | Message 5 | []                |
      | room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | guests    | guest        |                          | Message 5 | []                |
      | room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "participant3" sees the following messages in room "room" with 412
    And user "participant4" sees the following messages in room "room" with 412
    And user "guest" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | guests    | guest        |                          | Message 5 | []                |
      | room | users     | participant2 | participant2-displayname | Message 2 | []                |
      | room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "guest2" sees the following messages in room "room" with 412

  Scenario: lobby prevents calls for non moderators
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" joins call "room" with 200
    And user "participant2" joins call "room" with 200
    And user "participant3" joins call "room" with 412
    And user "participant4" joins call "room" with 412
    And user "guest" joins call "room" with 200
    And user "guest2" joins call "room" with 412
    And user "participant1" sees 3 peers in call "room" with 200
    And user "participant2" sees 3 peers in call "room" with 200
    And user "participant3" sees 0 peers in call "room" with 412
    And user "participant4" sees 0 peers in call "room" with 412
    And user "guest" sees 3 peers in call "room" with 200
    And user "guest2" sees 0 peers in call "room" with 412
    And user "participant1" leaves call "room" with 200
    And user "participant2" leaves call "room" with 200
    And user "guest" leaves call "room" with 200

  Scenario: lobby prevents some room actions for non moderators
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant1" leaves room "room" with 200 (v4)
    And user "participant2" leaves room "room" with 200 (v4)
    And user "participant3" leaves room "room" with 200 (v4)
    And user "participant4" leaves room "room" with 200 (v4)
    And user "guest" leaves room "room" with 200 (v4)
    And user "guest2" leaves room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "guest2" joins room "room" with 200 (v4)
    And user "participant2" removes themselves from room "room" with 200 (v4)
    And user "participant3" removes themselves from room "room" with 200 (v4)



  # Not all the values are checked in the test, only the most relevant ones
  Scenario: participants can get some room information when the lobby is active
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant4" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200 (v4)
    When user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sets description for room "room" to "the description" with 200
    Then user "participant1" is participant of room "room" (v4)
      | name | description     | type | participantType | lastMessage                                  |
      | room | the description | 3    | 1               | You set the description to "the description" |
    And user "participant2" is participant of room "room" (v4)
      | name | description     | type | participantType | lastMessage                                      |
      | room | the description | 3    | 2               | {actor} set the description to "the description" |
    And user "participant3" is participant of room "room" (v4)
      | name | description     | type | participantType | lastMessage |
      | room | the description | 3    | 3               |             |
    And user "participant4" is participant of room "room" (v4)
      | name | description     | type | participantType | lastMessage |
      | room | the description | 3    | 5               |             |
    And user "guest" is participant of room "room" (v4)
      | name | description     | type | participantType | lastMessage                                       |
      | room | the description | 3    | 6               | {actor} set the description to "the description"  |
    And user "guest2" is participant of room "room" (v4)
      | name | description     | type | participantType | lastMessage |
      | room | the description | 3    | 4               |             |
