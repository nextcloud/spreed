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
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | status     | open |
      | voted      | not voted |
    Then user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | status     | open |
      | voted      | [1] |
    Then user "participant1" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | status     | closed |
      | voted      | [1] |
