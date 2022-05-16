Feature: chat/poll
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Create a public poll without max votes limit
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
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
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
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
      | actorDisplayName    | participant1-displayname |
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
      | actorDisplayName    | participant1-displayname |
      | status     | closed |
      | voted      | [1] |

  Scenario: Participants can update their votes
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" creates a poll in room "room" with 201
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
    Then user "participant1" votes for options "[1]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | voted      | [1] |
    Then user "participant1" votes for options "[0]" on poll "What is the question?" in room "room" with 200
      | id         | POLL_ID(What is the question?) |
      | question   | What is the question? |
      | options    | ["Where are you?","How much is the fish?"] |
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | voted      | [0] |

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
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant1 |
      | actorDisplayName    | participant1-displayname |
      | status     | open |
      | voted      | [0,1] |

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
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | voted      | not voted |

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
      | resultMode | public |
      | maxVotes   | unlimited |
      | actorType  | users |
      | actorId    | participant2 |
      | actorDisplayName    | participant2-displayname |
      | status     | closed |
      | voted      | not voted |

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
