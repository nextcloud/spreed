Feature: chat/public
  Background:
    Given user "participant1" exists

  Scenario: Share a rich object to a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"name":"Another room","call-type":"group","type":"call","id":"R4nd0mT0k3n"}} |


  Scenario: Share an invalid rich object to a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"MISSINGname":"Another room","call-type":"group"}' to room "public room" with 400 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200
