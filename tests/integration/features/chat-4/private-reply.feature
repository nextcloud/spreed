Feature: chat-4/private-reply
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "attendees1" exists
    And user "participant1" is member of group "attendees1"
    And user "participant2" is member of group "attendees1"

  Scenario: user can send a private reply from a group room to a one-to-one room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" joins room "one-to-one room" with 200 (v4)
    And user "participant1" sends message "Original Message" to room "group room" with 201
    When user "participant2" sends private reply "Private Response" on message "Original Message" from room "group room" to room "one-to-one room" with 201
    Then user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message          | messageParameters | parentMessage    |
      | one-to-one room | users     | participant2 | participant2-displayname | Private Response | []                | Original Message |
    And user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message          | messageParameters | parentMessage    |
      | one-to-one room | users     | participant2 | participant2-displayname | Private Response | []                | Original Message |

  Scenario: user can send a private reply to their own message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" sends message "My Message" to room "group room" with 201
    When user "participant1" sends private reply "Follow-up" on message "My Message" from room "group room" to room "one-to-one room" with 400

  Scenario: multiple private replies to the same message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | group room |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    When user "participant2" sends private reply "Reply 1" on message "Original" from room "group room" to room "one-to-one room" with 201
    And user "participant2" sends private reply "Reply 2" on message "Original" from room "group room" to room "one-to-one room" with 201
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message | messageParameters | parentMessage | parentMetaData.replyToConversationName | parentMetaData.replyToActorDisplayName | parentMetaData.replyToMessageId | parentMetaData.replyToConversationToken |
      | one-to-one room | users     | participant2 | participant2-displayname | Reply 2 | []                | Original      | group room                             | participant1-displayname               | Original                        | group room                              |
      | one-to-one room | users     | participant2 | participant2-displayname | Reply 1 | []                | Original      | group room                             | participant1-displayname               | Original                        | group room                              |

  Scenario: private reply parent contains correct metadata
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | group room |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Source Message" to room "group room" with 201
    When user "participant2" sends private reply "Private Reply" on message "Source Message" from room "group room" to room "one-to-one room" with 201
    Then user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage  | parentMetaData.replyToConversationName | parentMetaData.replyToActorDisplayName | parentMetaData.replyToMessageId | parentMetaData.replyToConversationToken |
      | one-to-one room | users     | participant2 | participant2-displayname | Private Reply | []                | Source Message | group room                             | participant1-displayname               | Source Message                  | group room                              |

  Scenario: private reply does not show the link message in the message list
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    When user "participant2" sends private reply "Reply" on message "Original" from room "group room" to room "one-to-one room" with 201
    # Only the actual reply should be visible, not the internal link message
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message | messageParameters | parentMessage |
      | one-to-one room | users     | participant2 | participant2-displayname | Reply   | []                | Original      |

  Scenario: private reply does not affect the source conversation
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    When user "participant2" sends private reply "Reply" on message "Original" from room "group room" to room "one-to-one room" with 201
    # Source conversation should still only have the original message
    Then user "participant1" sees the following messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | message  | messageParameters |
      | group room | users     | participant1 | participant1-displayname | Original | []                |

  Scenario: user can reply normally to a private reply message in the one-to-one room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    And user "participant2" sends private reply "Private Reply" on message "Original" from room "group room" to room "one-to-one room" with 201
    When user "participant1" sends reply "Follow-up" on message "Private Reply" to room "one-to-one room" with 201
    Then user "participant1" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage |
      | one-to-one room | users     | participant1 | participant1-displayname | Follow-up     | []                | Private Reply |
      | one-to-one room | users     | participant2 | participant2-displayname | Private Reply | []                | Original      |

  Scenario: cannot send a private reply to a group room
    Given user "participant1" creates room "group room1" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" creates room "group room2" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" sends message "Original" to room "group room1" with 201
    When user "participant1" sends private reply "Reply" on message "Original" from room "group room1" to room "group room2" with 400

  Scenario: cannot send a private reply to a public room
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | invite   | attendees1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    When user "participant1" sends private reply "Reply" on message "Original" from room "group room" to room "public room" with 400

  Scenario: cannot send a private reply when sender is not in the source conversation
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant3" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    # participant3 is not in "group room"
    When user "participant3" sends private reply "Reply" on message "Original" from room "group room" to room "one-to-one room" with 403

  Scenario: cannot send a private reply when original author left the source conversation
    Given user "participant2" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original" to room "group room" with 201
    # Remove participant1 (original message author) from the group room
    And user "participant1" removes themselves from room "group room" with 200 (v4)
    When user "participant2" sends private reply "Reply" on message "Original" from room "group room" to room "one-to-one room" with 403

  Scenario: cannot send a private reply to a system message
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    # Get system messages so they are added to the known messages list
    And user "participant1" sees the following system messages in room "group room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage        |
      | group room | users     | participant1 | participant1-displayname | user_added           |
      | group room | users     | participant1 | participant1-displayname | group_added          |
      | group room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant2" sends private reply "Reply" on message "conversation_created" from room "group room" to room "one-to-one room" with 400

  Scenario: editing the original message does not change the private reply parent snapshot
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original Message" to room "group room" with 201
    And user "participant2" sends private reply "Private Reply" on message "Original Message" from room "group room" to room "one-to-one room" with 201
    When user "participant1" edits message "Original Message" in room "group room" to "Edited Message" with 200
    # The private reply parent should still show the original text (snapshot)
    Then user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage    |
      | one-to-one room | users     | participant2 | participant2-displayname | Private Reply | []                | Original Message |

  Scenario: deleting the original message does not affect the private reply parent snapshot
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" sends message "Original Message" to room "group room" with 201
    And user "participant2" sends private reply "Private Reply" on message "Original Message" from room "group room" to room "one-to-one room" with 201
    When user "participant1" deletes message "Original Message" from room "group room" with 200
    # The private reply parent should still show the original text (snapshot)
    Then user "participant2" sees the following messages in room "one-to-one room" with 200
      | room            | actorType | actorId      | actorDisplayName         | message       | messageParameters | parentMessage    |
      | one-to-one room | users     | participant2 | participant2-displayname | Private Reply | []                | Original Message |

  Scenario: user can not send a private reply from a group room to one-to-one room other than the author
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    And user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant2" joins room "one-to-one room" with 200 (v4)
    And user "participant1" sends message "Original Message" to room "group room" with 201
    When user "participant2" sends private reply "Private Response" on message "Original Message" from room "group room" to room "one-to-one room" with 403
