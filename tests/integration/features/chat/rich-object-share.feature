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

  Scenario: Delete a rich object from a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    And user "participant1" deletes message "shared::call::R4nd0mT0k3n" from room "public room" with 200
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                  | messageParameters | parentMessage |
      | public room | users     | participant1 | participant1-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"}}                |               |

  Scenario: Share an invalid rich object to a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"MISSINGname":"Another room","call-type":"group"}' to room "public room" with 400 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200

  Scenario: Share an invalid location to a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "geo-location" "https://nextcloud.com/" '{"name":"Location name"}' to room "public room" with 400 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200

  Scenario: Get rich object and file shares for media tab
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    Then user "participant1" sees the following shared media in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {file}   | "IGNORE" |
      | public room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname"},"object":{"name":"Another room","call-type":"group","type":"call","id":"R4nd0mT0k3n"}} |
