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
