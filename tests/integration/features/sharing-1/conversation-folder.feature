Feature: sharing-1/conversation-folder

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Upload file to conversation folder and post as attachment to group room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" creates conversation folder for room "group room" with name "Group room"
    And user "participant1" uploads file "test.txt" with content "Hello!" to conversation folder for room "group room" with name "Group room"
    And user "participant1" posts file "test.txt" from conversation folder of room "group room" with name "Group room" with 200 (v1)
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | group room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |
    And user "participant2" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | group room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |

  Scenario: Upload file to conversation folder and post as attachment to public room
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "participant1" renames room "public room" to "Public room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    When user "participant1" creates conversation folder for room "public room" with name "Public room"
    And user "participant1" uploads file "test.txt" with content "Hello!" to conversation folder for room "public room" with name "Public room"
    And user "participant1" posts file "test.txt" from conversation folder of room "public room" with name "Public room" with 200 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | public room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |

  Scenario: Post with caption and as a reply
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" renames room "group room" to "Group room" with 200 (v4)
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "group room" with 201
    And user "participant1" creates conversation folder for room "group room" with name "Group room"
    And user "participant1" uploads file "test.txt" with content "Hello!" to conversation folder for room "group room" with name "Group room"
    When user "participant1" posts file "test.txt" from conversation folder of room "group room" with name "Group room" with 200 (v1)
      | talkMetaData.caption | Caption text |
      | talkMetaData.replyTo | Message 1    |
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message      | messageParameters | parentMessage |
      | group room | users     | participant1 | participant1-displayname | Caption text | "IGNORE"          | Message 1     |
      | group room | users     | participant2 | participant2-displayname | Message 1    | []                |               |

  Scenario: Room name with a hyphen does not confuse token extraction
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2        |
      | roomName | My-Group |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" creates conversation folder for room "group room" with name "My-Group"
    And user "participant1" uploads file "test.txt" with content "Hello!" to conversation folder for room "group room" with name "My-Group"
    And user "participant1" posts file "test.txt" from conversation folder of room "group room" with name "My-Group" with 200 (v1)
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | group room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |

  Scenario: Room name with a slash is sanitized to a space in the folder name
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2         |
      | roomName | Team/Chat |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" creates conversation folder for room "group room" with name "Team/Chat"
    And user "participant1" uploads file "test.txt" with content "Hello!" to conversation folder for room "group room" with name "Team/Chat"
    And user "participant1" posts file "test.txt" from conversation folder of room "group room" with name "Team/Chat" with 200 (v1)
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | group room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |

  Scenario: Posting file outside conversation folder is rejected
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant1" posts file "welcome.txt" from their home to room "group room" with 422 (v1)

  Scenario: Non-participant cannot post attachment
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    When user "participant3" posts file "welcome.txt" from their home to room "group room" with 403 (v1)
