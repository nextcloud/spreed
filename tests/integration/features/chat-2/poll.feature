Feature: chat-2/poll
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
      | votes      | {"option-1":1}   |
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
      | votes      | {"option-1":1}   |
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
      | votes      | {"option-1":1}   |
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
      | votes      | {"option-1":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | not voted |
      | details    | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":1}] |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | poll_closed          | You ended the poll {poll}        | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests        | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users         | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Participants can update their votes but only while open
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
      | votes      | {"option-0":1}   |
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
      | votes      | {"option-1":1}   |
      | numVoters  | 1    |
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
      | votes      | {"option-1":1}   |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | [1] |
      | details    | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":1}] |
    Then user "participant1" votes for options "[0]" on poll "What is the question?" in room "room" with 400
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | poll_closed          | You ended the poll {poll}        | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests        | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests        | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

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
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

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
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

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
      | votes      | {"option-0":1,"option-1":1} |
      | numVoters  | 1    |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0,1] |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | guests        | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

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
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

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
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant2 | poll_closed          | {actor} ended the poll {poll}    | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users         | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Non-moderators can not create polls without chat permission
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
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | poll_closed          | You ended the poll {poll}        | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users         | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

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
      | votes      | {"option-0":1} |
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
      | votes      | {"option-0":1,"option-1":1} |
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
      | votes      | {"option-0":1,"option-1":1} |
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
      | room | actorType | actorId      | systemMessage        | message                          | messageParameters |
      | room | users     | participant1 | poll_closed          | You ended the poll {poll}        | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests    | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests    | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users     | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users     | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
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
      | numVoters  | 1 |
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
      | votes      | {"option-0":1,"option-1":1} |
      | numVoters  | 2 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | [0] |
    Then user "participant1" closes poll "What is the question?" in room "room" with 400
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
      | numVoters  | 1 |
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
      | votes      | {"option-1":1}   |
      | numVoters  | 1 |
      | resultMode | hidden |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | votedSelf  | not voted |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | systemMessage        | message                          | messageParameters |
      | room | users     | participant1 | poll_closed          | You ended the poll {poll}        | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users     | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users     | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Number of voters and votes are restricted to the very same poll
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
      | votes      | {"option-0":1} |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0] |
    When user "participant2" creates a poll in room "room" with 201
      | question   | Another one ... |
      | options    | ["... bites the dust!","... bites de_dust!"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant2" votes for options "[1]" on poll "Another one ..." in room "room" with 200
      | id         | POLL_ID(Another one ...) |
      | question   | Another one ... |
      | options    | ["... bites the dust!","... bites de_dust!"] |
      | votes      | {"option-1":1} |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant2" sees poll "Another one ..." in room "room" with 200
      | id         | POLL_ID(Another one ...) |
      | question   | Another one ... |
      | options    | ["... bites the dust!","... bites de_dust!"] |
      | votes      | {"option-1":1} |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | [1] |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | systemMessage        | message                          | messageParameters |
      | room | guests    | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(Another one ...),"name":"Another one ..."}} |
      | room | guests    | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users     | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users     | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Remove all votes
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
      | votes      | {"option-0":1} |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | votedSelf  | [0] |
    Then user "participant1" votes for options "{}" on poll "What is the question?" in room "room" with 200
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
      | votedSelf  | [] |
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
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
      | votedSelf  | [] |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | systemMessage        | message                          | messageParameters |
      | room | guests    | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests    | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users     | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users     | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Empty question and options
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 400
      | question   | Also we need at least 2 non empty options |
      | options    | ["\t"," ","a"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    When user "participant1" creates a poll in room "room" with 400
      | question   |  |
      | options    | ["Empty question is not","allowed either"] |
      | resultMode | public |
      | maxVotes   | unlimited |

  Scenario: Can not poll in one-to-one
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" creates a poll in room "room" with 400
      | question   | Can I poll in one-to-one? |
      | options    | ["No","Nope"] |
      | resultMode | public |
      | maxVotes   | unlimited |

  Scenario: Deleting a user neutralizes their details
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    And user "participant2" votes for options "[0]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-0":1} |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | open |
      | votedSelf  | [0] |
    And user "participant2" closes poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-0":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | votedSelf  | [0] |
      | details    | [{"actorType":"users","actorId":"participant2","actorDisplayName":"participant2-displayname","optionId":0}] |
    When user "participant2" is deleted
    Then user "participant1" sees poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | votes      | {"option-0":1}   |
      | numVoters  | 1 |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | deleted_users |
      | actorId    | deleted_users |
      | actorDisplayName    | |
      | status     | closed |
      | votedSelf  | [] |
      | details    | [{"actorType":"deleted_users","actorId":"deleted_users","actorDisplayName":"","optionId":0}] |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | admin        | user_removed         | {actor} removed {user}           | "IGNORE" |
      | room | deleted_users | deleted_users | poll_closed         | {actor} ended the poll {poll}        | {"actor":{"type":"highlight","id":"deleted_user","name":"Deleted user"},"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | guests        | system       | poll_voted           | Someone voted on the poll {poll} | {"poll":{"type":"talk-poll","id":POLL_ID(What is the question?),"name":"What is the question?"}} |
      | room | users         | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"highlight","id":"deleted_user","name":"Deleted user"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Deleting the poll message removes all details
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    And user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant2 | participant2-displayname | {object} | "IGNORE"          |
    And user "participant1" deletes message "{object}" from room "room" with 200 (v1)
    And user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 404
    And user "participant2" votes for options "[0]" on poll "What is the question?" in room "room" with 404
    And user "participant1" closes poll "What is the question?" in room "room" with 404
    And user "participant2" closes poll "What is the question?" in room "room" with 404
    Then user "participant1" sees poll "What is the question?" in room "room" with 404
    Then user "participant2" sees poll "What is the question?" in room "room" with 404
    And user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message                | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message deleted by you | "IGNORE"          |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | message_deleted      | You deleted a message            | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
      | room | users         | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Deleting a closed poll message removes also the close message
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    And user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant2 | participant2-displayname | {object} | "IGNORE"          |
    And user "participant2" closes poll "What is the question?" in room "room" with 200
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
      | votedSelf  | [] |
      | details    | [] |
    And user "participant1" deletes message "{object}" from room "room" with 200 (v1)
    And user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 404
    And user "participant2" votes for options "[0]" on poll "What is the question?" in room "room" with 404
    And user "participant1" closes poll "What is the question?" in room "room" with 404
    And user "participant2" closes poll "What is the question?" in room "room" with 404
    Then user "participant1" sees poll "What is the question?" in room "room" with 404
    Then user "participant2" sees poll "What is the question?" in room "room" with 404
    And user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message                | messageParameters |
      | room | users     | participant2 | participant2-displayname | Message deleted by you | "IGNORE"          |
      | room | users     | participant2 | participant2-displayname | Message deleted by you | "IGNORE"          |
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | message_deleted      | You deleted a message            | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
      | room | users         | participant1 | message_deleted      | You deleted a message            | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
      | room | users         | participant1 | user_added           | You added {user}                 | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"user":{"type":"user","id":"participant2","name":"participant2-displayname"}} |
      | room | users         | participant1 | conversation_created | You created the conversation     | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |

  Scenario: Deleting the chat history also deletes polls
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    And user "participant1" sees the following messages in room "room" with 200 (v1)
      | room | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | room | users     | participant2 | participant2-displayname | {object} | "IGNORE"          |
    And user "participant1" deletes chat history for room "room" with 200
    Then user "participant1" sees poll "What is the question?" in room "room" with 404
    Then user "participant2" sees poll "What is the question?" in room "room" with 404
    And user "participant1" sees the following messages in room "room" with 200 (v1)
    Then user "participant1" sees the following system messages in room "room" with 200 (v1)
      | room | actorType     | actorId      | systemMessage        | message                          | messageParameters |
      | room | users         | participant1 | history_cleared      | You cleared the history of the conversation | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}} |
