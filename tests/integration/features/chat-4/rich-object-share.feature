Feature: chat-2/rich-object-share
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Share a rich object to a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"object":{"name":"Another room","call-type":"group","type":"call","id":"R4nd0mT0k3n","icon-url":"{VALIDATE_ICON_URL_PATTERN}"}} |

  Scenario: Can not share without chat permission
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "public room" to "CSJLAVP" with 200 (v4)
    When user "participant2" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 403 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |

  Scenario: Delete a rich object from a chat
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    And user "participant1" deletes message "shared::call::R4nd0mT0k3n" from room "public room" with 200
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                  | messageParameters | parentMessage |
      | public room | users     | participant1 | participant1-displayname | Message deleted by you   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                |               |

  Scenario: Can not delete without chat permission
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    When user "participant2" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    # Removing chat permission only
    Then user "participant1" sets permissions for "participant2" in room "public room" to "CSJLAVP" with 200 (v4)
    And user "participant2" deletes message "shared::call::R4nd0mT0k3n" from room "public room" with 403
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message                  | messageParameters |
      | public room | users     | participant2 | participant2-displayname | {object} | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"},"object":{"name":"Another room","call-type":"group","type":"call","id":"R4nd0mT0k3n","icon-url":"{VALIDATE_ICON_URL_PATTERN}"}} |

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
    Then user "participant1" sees the following shared summarized overview in room "public room" with 200
    When user "participant1" shares rich-object "call" "R4nd0mT0k3n" '{"name":"Another room","call-type":"group"}' to room "public room" with 201 (v1)
    Then user "participant1" sees the following shared other in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {object} | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"object":{"name":"Another room","call-type":"group","type":"call","id":"R4nd0mT0k3n","icon-url":"{VALIDATE_ICON_URL_PATTERN}"}} |
    When user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    Then user "participant1" sees the following shared file in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {file}   | "IGNORE" |
    And user "guest" joins room "public room" with 200 (v4)
    Then user "guest" sees the following shared file in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {file}   | "IGNORE" |
