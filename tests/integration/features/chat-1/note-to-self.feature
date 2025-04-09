Feature: chat/note-to-self

  Background:
    Given user "participant1" exists

  Scenario: Created manually via the endpoint
    When user "participant1" reset note-to-self preference
    When user "participant1" creates note-to-self (v4)
    And user "participant1" is participant of the following note-to-self rooms (v4)
      | id                        | type | name          |
      | participant1-note-to-self | 6    | Note to self  |
    Then user "participant1" sees the following system messages in room "participant1-note-to-self" with 200
      | room                      | actorType | actorId      | actorDisplayName         | message                        | messageParameters                                                               | systemMessage        |
      | participant1-note-to-self | users     | participant1 | participant1-displayname | You created the conversation   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} | conversation_created |


  Scenario: Created automatically when fetching the room list
    When user "participant1" reset note-to-self preference
    And user "participant1" is participant of the following note-to-self rooms (v4)
      | id           | type | name          |
      | Note to self | 6    | Note to self  |
    Then user "participant1" sees the following system messages in room "Note to self" with 200
      | room         | actorType | actorId | actorDisplayName | message                         | messageParameters                                              | systemMessage        |
      | Note to self | guests    | system  |                  | System created the conversation | {"actor":{"type":"guest","id":"guest\/system","name":"System","mention-id":"guest\/system"}} | conversation_created |

  Scenario: Edit messages forever in note-to-self room
    When user "participant1" creates note-to-self (v4)
    And user "participant1" is participant of the following note-to-self rooms (v4)
      | id                        | type | name         |
      | participant1-note-to-self | 6    | Note to self |
    Then user "participant1" sends message "Initial message" to room "participant1-note-to-self" with 201
    And user "participant1" sees the following messages in room "participant1-note-to-self" with 200
      | room                      | actorType | actorId      | actorDisplayName         | message          | messageParameters |
      | participant1-note-to-self | users     | participant1 | participant1-displayname | Initial message  | []                |
    When aging messages 24 hours in room "participant1-note-to-self"
    And user "participant1" edits message "Initial message" in room "participant1-note-to-self" to "Edited after 24 hours" with 200
    Then user "participant1" sees the following messages in room "participant1-note-to-self" with 200
      | room                      | actorType | actorId      | actorDisplayName         | message                | messageParameters | lastEditActorType | lastEditActorId | lastEditActorDisplayName |
      | participant1-note-to-self | users     | participant1 | participant1-displayname | Edited after 24 hours  | []                | users             | participant1    | participant1-displayname |
