Feature: chat/poll
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Create a public poll without max votes limit
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | {object}  | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | []   |
      | numVoters  | 0    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | not voted |
    Then user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant2" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | [] |
      | numVoters  | 0 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | not voted |
    Then user "participant1" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"1":1}   |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | [1] |
      | details    | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":1}] |
    Then user "participant2" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | not voted |
      | details    | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":1}] |

  Scenario: Participants can update their votes
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[0]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"0":1}   |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0] |
    Then user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"1":1}   |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |

  Scenario: Participants can only vote for valid options
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[-1]" on poll "What is the question?" in room "room" with 400
    Then user "participant1" votes for options "[2]" on poll "What is the question?" in room "room" with 400

  Scenario: Participants can not exceed the maxVotes
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | 1 |
    Then user "participant1" votes for options "[0,1]" on poll "What is the question?" in room "room" with 400

  Scenario: Participants can vote for multiple options
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[0,1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"0":1,"1":1} |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0,1] |

  Scenario: Participants can not vote for the same option multiple times
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[1,1]" on poll "What is the question?" in room "room" with 400

  Scenario: Non-moderators can also create polls and close it themselves
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant2 | participant2-displayname | {object}  | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"},"object":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
    Then user "participant2" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | [] |
      | numVoters  | 0 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | votedSelf  | not voted |
      | details    | {} |

  Scenario: Non-moderators can note create polls without chat permission
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "room" to "CSJLAVP" with 200 (v4)
    When user "participant2" creates a poll in room "room" with 403
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |

  Scenario: Moderators can close polls of others
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | [] |
      | numVoters  | 0 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | votedSelf  | not voted |
      | details    | {} |

  Scenario: There are system messages for opening, voting and closing on public polls
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[0]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"0":1} |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0] |
    Then user "participant2" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"0":1,"1":1} |
      | numVoters  | 2 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"0":1,"1":1} |
      | numVoters  | 2 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | [0] |
      | details    | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0},{"actorType":"users","actorId":"participant2","actorDisplayName":"participant2-displayname","optionId":1}] |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | poll_closed          |
      | room | users     | participant2 | participant2-displayname | poll_voted           |
      | room | users     | participant1 | participant1-displayname | poll_voted           |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    Then user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |


  Scenario: There are only system messages for opening and closing on hidden polls
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | hidden |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[0]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | [] |
      | numVoters  | 0 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0] |
    Then user "participant2" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | [] |
      | numVoters  | 0 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"0":1,"1":1} |
      | numVoters  | 2 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | [0] |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | poll_closed          |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    Then user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |

  Scenario: Non-moderators can not close polls of others
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant2" closes poll "What is the question?" in room "room" with 403

  Scenario: Votes and details are not accessible in hidden result mode
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | hidden |
      | maxVotes   | unlimited |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message   | messageParameters |
      | room | users     | participant1 | participant1-displayname | {object}  | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
    Then user "participant2" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | []   |
      | numVoters  | 0 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | []   |
      | numVoters  | 0 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | not voted |
    Then user "participant2" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | []   |
      | numVoters  | 0 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"1":1}   |
      | numVoters  | 1 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | not voted |
