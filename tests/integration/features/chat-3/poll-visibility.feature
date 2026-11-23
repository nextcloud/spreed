# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: chat-3/poll-visibility
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    Given user "participant1" adds user "participant2" to room "room" with 200 (v4)

  Scenario Outline: Persist visibility flags when creating and editing drafts
    When user "participant1" creates a poll in room "room" with 200
      | question   | Draft visibility? |
      | options    | ["A","B"] |
      | resultMode | <initial> |
      | maxVotes   | 1 |
      | draft      | 1 |
    Then user "participant1" gets poll drafts for room "room" with 200
      | id | question | options | actorType | actorId | actorDisplayName | status | resultMode | maxVotes |
      | POLL_ID(Draft visibility?) | Draft visibility? | ["A","B"] | users | participant1 | participant1-displayname | draft | <initial> | 1 |
    When user "participant1" updates a draft poll in room "room" with 200
      | id         | POLL_ID(Draft visibility?) |
      | question   | Draft visibility? |
      | options    | ["A","B"] |
      | resultMode | <updated> |
      | maxVotes   | 1 |
    Then user "participant1" gets poll drafts for room "room" with 200
      | id | question | options | actorType | actorId | actorDisplayName | status | resultMode | maxVotes |
      | POLL_ID(Draft visibility?) | Draft visibility? | ["A","B"] | users | participant1 | participant1-displayname | draft | <updated> | 1 |
    Then user "participant2" sees poll "Draft visibility?" in room "room" with 404

    Examples:
      | initial | updated |
      | 0 | 14 |
      | 1 | 10 |
      | 2 | 9 |
      | 6 | 0 |
      | 8 | 1 |
      | 9 | 6 |
      | 10 | 8 |
      | 14 | 2 |

  Scenario: Reject unknown visibility flags
    When user "participant1" creates a poll in room "room" with 400
      | question   | Invalid visibility? |
      | options    | ["A","B"] |
      | resultMode | 16 |
      | maxVotes   | 1 |

  Scenario Outline: Result and voter visibility before voting, after voting, after withdrawal, and after closing
    When user "participant1" creates a poll in room "room" with 201
      | question   | Visibility? |
      | options    | ["A","B"] |
      | resultMode | <mode> |
      | maxVotes   | 1 |
    When user "participant1" votes for options "[0]" on poll "Visibility?" in room "room" with 200
      | id | POLL_ID(Visibility?) |
      | question | Visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant1 |
      | actorDisplayName | participant1-displayname |
      | status | open |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | <votedVotes1> |
      | numVoters | 1 |
      | votedSelf | [0] |
      | details | <votedDetails1> |
    Then user "participant2" sees poll "Visibility?" in room "room" with 200
      | id | POLL_ID(Visibility?) |
      | question | Visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant1 |
      | actorDisplayName | participant1-displayname |
      | status | open |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | <beforeVotes> |
      | numVoters | <beforeCount> |
      | votedSelf | not voted |
      | details | <beforeDetails> |
    When user "participant2" votes for options "[0]" on poll "Visibility?" in room "room" with 200
      | id | POLL_ID(Visibility?) |
      | question | Visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant1 |
      | actorDisplayName | participant1-displayname |
      | status | open |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | <votedVotes2> |
      | numVoters | <votedCount> |
      | votedSelf | [0] |
      | details | <votedDetails2> |
    When user "participant2" votes for options "[]" on poll "Visibility?" in room "room" with 200
      | id | POLL_ID(Visibility?) |
      | question | Visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant1 |
      | actorDisplayName | participant1-displayname |
      | status | open |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | <beforeVotes> |
      | numVoters | <beforeCount> |
      | votedSelf | not voted |
      | details | <beforeDetails> |
    When user "participant1" closes poll "Visibility?" in room "room" with 200
      | id | POLL_ID(Visibility?) |
      | question | Visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant1 |
      | actorDisplayName | participant1-displayname |
      | status | closed |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | {"option-0":1} |
      | numVoters | 1 |
      | votedSelf | [0] |
      | details | <closedDetails> |
    Then user "participant2" sees poll "Visibility?" in room "room" with 200
      | id | POLL_ID(Visibility?) |
      | question | Visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant1 |
      | actorDisplayName | participant1-displayname |
      | status | closed |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | {"option-0":1} |
      | numVoters | 1 |
      | votedSelf | not voted |
      | details | <closedDetails> |

    Examples:
      | mode | votedVotes1 | votedDetails1 | beforeVotes | beforeCount | beforeDetails | votedVotes2 | votedCount | votedDetails2 | closedDetails |
      | 0 | {"option-0":1} | absent | [] | 0 | absent | {"option-0":2} | 2 | absent | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 1 | [] | absent | [] | 0 | absent | [] | 0 | absent | absent |
      | 2 | {"option-0":1} | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | [] | 0 | absent | {"option-0":2} | 2 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0},{"actorType":"users","actorId":"participant2","actorDisplayName":"participant2-displayname","optionId":0}] | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 6 | {"option-0":1} | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | {"option-0":1} | 1 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | {"option-0":2} | 2 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0},{"actorType":"users","actorId":"participant2","actorDisplayName":"participant2-displayname","optionId":0}] | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 8 | {"option-0":1} | absent | {"option-0":1} | 1 | absent | {"option-0":2} | 2 | absent | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 9 | {"option-0":1} | absent | {"option-0":1} | 1 | absent | {"option-0":2} | 2 | absent | absent |
      | 10 | {"option-0":1} | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | {"option-0":1} | 1 | absent | {"option-0":2} | 2 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0},{"actorType":"users","actorId":"participant2","actorDisplayName":"participant2-displayname","optionId":0}] | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 14 | {"option-0":1} | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | {"option-0":1} | 1 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | {"option-0":2} | 2 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0},{"actorType":"users","actorId":"participant2","actorDisplayName":"participant2-displayname","optionId":0}] | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 11 | {"option-0":1} | absent | {"option-0":1} | 1 | absent | {"option-0":2} | 2 | absent | absent |
      | 15 | {"option-0":1} | absent | {"option-0":1} | 1 | absent | {"option-0":2} | 2 | absent | absent |
