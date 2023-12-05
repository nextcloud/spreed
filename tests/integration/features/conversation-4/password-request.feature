Feature: conversation-2/password-request

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: create password-request room for file shared by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    When user "guest" creates the password request room for last share with 201 (v1)
    Then user "participant1" is participant of room "password request for last share room" (v4)
      | name        | type | participantType |
      | welcome.txt | 3    | 1               |
    And user "participant1" sees the following attendees in room "welcome.txt" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "guest" is not participant of room "password request for last share room" (v4)

  Scenario: create password-request room for folder shared by link
    Given user "participant1" creates folder "/test"
    And user "participant1" shares "test" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    When user "guest" creates the password request room for last share with 201 (v1)
    Then user "participant1" is participant of room "password request for last share room" (v4)
      | name | type | participantType |
      | test | 3    | 1               |
    And user "participant1" sees the following attendees in room "test" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "guest" is not participant of room "password request for last share room" (v4)

  Scenario: create password-request room for folder reshared by link
    Given user "participant1" creates folder "/test"
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" shares "test" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    When user "guest" creates the password request room for last share with 201 (v1)
    Then user "participant2" is participant of room "password request for last share room" (v4)
      | name | type | participantType |
      | test | 3    | 1               |
    And user "participant2" sees the following attendees in room "test" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant2 | 1               |
    And user "participant1" is not participant of room "password request for last share room" (v4)
    And user "guest" is not participant of room "password request for last share room" (v4)

  Scenario: create password-request room for file shared by link but not protected by Talk
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
    When user "guest" creates the password request room for last share with 404 (v1)



  # Creating and joining the password request room is a two steps process.
  # Technically one guest or user could create the room and a different one
  # join it, but it does not really matter who created the room, only who joins
  # it and talks with the owner (and, besides that, the WebUI joins the room
  # immediately after creating it).

  Scenario: guest can join the password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    When user "guest" joins room "password request for last share room" with 200 (v4)
    Then user "guest" is participant of room "password request for last share room" (v4)

  Scenario: user can join the password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "participant2" creates the password request room for last share with 201 (v1)
    When user "participant2" joins room "password request for last share room" with 200 (v4)
    Then user "participant2" is participant of room "password request for last share room" (v4)

  Scenario: owner can join the password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    When user "participant1" joins room "password request for last share room" with 200 (v4)

  Scenario: other guests can not join the password request room when a guest already joined
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    When user "guest2" joins room "password request for last share room" with 404 (v4)
    Then user "guest2" is not participant of room "password request for last share room" (v4)

  Scenario: other guests can not join the password request room when a user already joined
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "participant2" creates the password request room for last share with 201 (v1)
    And user "participant2" joins room "password request for last share room" with 200 (v4)
    When user "guest" joins room "password request for last share room" with 404 (v4)
    Then user "guest" is not participant of room "password request for last share room" (v4)

  Scenario: other users can not join the password request room when a guest already joined
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    When user "participant2" joins room "password request for last share room" with 404 (v4)
    Then user "participant2" is not participant of room "password request for last share room" (v4)

  Scenario: other users can not join the password request room when a user already joined
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "participant2" creates the password request room for last share with 201 (v1)
    And user "participant2" joins room "password request for last share room" with 200 (v4)
    When user "participant3" joins room "password request for last share room" with 404 (v4)
    Then user "participant3" is not participant of room "password request for last share room" (v4)



  Scenario: owner can not add other users to a password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "participant1" joins room "password request for last share room" with 200 (v4)
    When user "participant1" adds user "participant2" to room "password request for last share room" with 400 (v4)
    Then user "participant2" is not participant of room "password request for last share room" (v4)



  Scenario: guest leaves the password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    And user "participant1" joins room "password request for last share room" with 200 (v4)
    When user "guest" leaves room "password request for last share room" with 200 (v4)
    Then user "participant1" is not participant of room "password request for last share room" (v4)
    And user "guest" is not participant of room "password request for last share room" (v4)

  Scenario: user leaves the password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "participant2" creates the password request room for last share with 201 (v1)
    And user "participant2" joins room "password request for last share room" with 200 (v4)
    And user "participant1" joins room "password request for last share room" with 200 (v4)
    When user "participant2" leaves room "password request for last share room" with 200 (v4)
    Then user "participant1" is not participant of room "password request for last share room" (v4)
    And user "participant2" is not participant of room "password request for last share room" (v4)

  Scenario: owner leaves the password request room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    And user "participant1" joins room "password request for last share room" with 200 (v4)
    When user "participant1" leaves room "password request for last share room" with 200 (v4)
    Then user "participant1" is not participant of room "password request for last share room" (v4)
    And user "guest" is not participant of room "password request for last share room" (v4)



  Scenario: guest can start a call
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    When user "guest" joins call "password request for last share room" with 200 (v4)
    Then user "guest" sees 1 peers in call "password request for last share room" with 200 (v4)
    And user "participant1" sees 1 peers in call "password request for last share room" with 200 (v4)

  Scenario: owner can join a call
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    And user "participant1" joins room "password request for last share room" with 200 (v4)
    And user "guest" joins call "password request for last share room" with 200 (v4)
    When user "participant1" joins call "password request for last share room" with 200 (v4)
    Then user "guest" sees 2 peers in call "password request for last share room" with 200 (v4)
    And user "participant1" sees 2 peers in call "password request for last share room" with 200 (v4)



  Scenario: participants can send and receive chat messages
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "participant1" joins room "password request for last share room" with 200 (v4)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "password request for last share room" with 201
    And user "guest" sends message "Message 2" to room "password request for last share room" with 201
    Then user "participant1" sees the following messages in room "password request for last share room" with 200
      | room                                  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | password request for last share room | guests    | guest        |                          | Message 2 | []                |
      | password request for last share room | users     | participant1 | participant1-displayname | Message 1 | []                |
    And user "guest" sees the following messages in room "password request for last share room" with 200
      | room                                  | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | password request for last share room | guests    | guest        |                          | Message 2 | []                |
      | password request for last share room | users     | participant1 | participant1-displayname | Message 1 | []                |
