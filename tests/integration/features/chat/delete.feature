Feature: chat/reply
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: moderator deletes their own message
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200
    And user "participant1" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: user deletes their own message
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200
    And user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant2" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: moderator deletes other user message
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200
    And user "participant2" sends message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant2 | participant2-displayname | Message deleted by {actor}   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: moderator deletes their own message which got replies
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by you     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by {actor}     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by {actor}   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: user deletes their own message which got replies
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant2" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by author     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by author   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by you     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"

  Scenario: moderator deletes other user message
    Given user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200
    And user "participant2" sends message "Message 1" to room "group room" with 201
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "group room" with 201
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1   | []                |               |
    And user "participant1" deletes message "Message 1" from room "group room" with 200
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by you     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message     | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Message 1-1 | []                | Message deleted by {actor}     |
      | group room | users     | participant2 | participant2-displayname | Message deleted by {actor}   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |
    Then user "participant1" received a system messages in room "group room" to delete "Message 1"
    Then user "participant2" received a system messages in room "group room" to delete "Message 1"
