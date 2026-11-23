# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: federation/poll-visibility
  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    And user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId | inviterDisplayName |
      | LOCAL | room | 0 | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2 | LOCAL | room |

  Scenario Outline: Forward visibility flags and enforce them on both servers
    When user "participant2" creates a poll in room "LOCAL::room" with 201
      | question | Federated visibility? |
      | options | ["A","B"] |
      | resultMode | <mode> |
      | maxVotes | 1 |
    And using server "LOCAL"
    Then user "participant1" votes for options "[0]" on poll "Federated visibility?" in room "room" with 200
      | id | POLL_ID(Federated visibility?) |
      | question | Federated visibility? |
      | options | ["A","B"] |
      | actorType | federated_users |
      | actorId | participant2@{$REMOTE_URL} |
      | actorDisplayName | participant2-displayname |
      | status | open |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | {"option-0":1} |
      | numVoters | 1 |
      | votedSelf | [0] |
      | details | <localDetails> |
    And using server "REMOTE"
    Then user "participant2" sees poll "Federated visibility?" in room "LOCAL::room" with 200
      | id | POLL_ID(Federated visibility?) |
      | question | Federated visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant2 |
      | actorDisplayName | participant2-displayname |
      | status | open |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | {"option-0":1} |
      | numVoters | 1 |
      | votedSelf | not voted |
      | details | <remoteDetails> |
    Then user "participant2" closes poll "Federated visibility?" in room "LOCAL::room" with 200
      | id | POLL_ID(Federated visibility?) |
      | question | Federated visibility? |
      | options | ["A","B"] |
      | actorType | users |
      | actorId | participant2 |
      | actorDisplayName | participant2-displayname |
      | status | closed |
      | resultMode | <mode> |
      | maxVotes | 1 |
      | votes | {"option-0":1} |
      | numVoters | 1 |
      | votedSelf | not voted |
      | details | <closedDetails> |

    Examples:
      | mode | localDetails | remoteDetails | closedDetails |
      | 6 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | [{"actorType":"federated_users","actorId":"participant1@{$LOCAL_URL}","actorDisplayName":"participant1-displayname","optionId":0}] | [{"actorType":"federated_users","actorId":"participant1@{$LOCAL_URL}","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 10 | [{"actorType":"users","actorId":"participant1","actorDisplayName":"participant1-displayname","optionId":0}] | absent | [{"actorType":"federated_users","actorId":"participant1@{$LOCAL_URL}","actorDisplayName":"participant1-displayname","optionId":0}] |
      | 9 | absent | absent | absent |
