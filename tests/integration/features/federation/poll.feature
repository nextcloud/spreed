Feature: chat-2/poll
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Create a public poll without max votes limit
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId                     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@http://localhost:8080 | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id   | name | type | remoteServer | remoteToken |
      | room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type |
      | room | 2    |
    When user "participant2" creates a poll in room "LOCAL::room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | {object} | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","server":"http:\/\/localhost:8180"},"object":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
    Then user "participant2" sees poll "What is the question?" in room "LOCAL::room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | []   |
      | numVoters  | 0    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | not voted |
    Then user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | federated_users |
      | actorId    | participant2@{$REMOTE_URL} |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | federated_users |
      | actorId    | participant2@{$REMOTE_URL} |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant2" sees poll "What is the question?" in room "LOCAL::room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | [] |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | not voted |
    Then user "participant2" closes poll "What is the question?" in room "LOCAL::room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-1":1}   |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | votedSelf  | not voted |
      | details    | [{"actorType":"federated_users","actorId":"participant1@{$BASE_URL}","actorDisplayName":"participant1-displayname","optionId":1}] |
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | federated_users |
      | actorId    | participant2@{$REMOTE_URL} |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | votedSelf  | [1] |
      | details    | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":1}] |
