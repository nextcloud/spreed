Feature: chat/poll
  Background:
    Given user "participant1" exists

  Scenario: create a public poll without max votes limit
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 200
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | {object}  | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"type":"highlight","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
