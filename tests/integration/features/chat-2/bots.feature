Feature: chat/bots
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Installing the Webhook Demo bot
    Given invoking occ with "talk:bot:list"
    Then the command was successful
    And the command output is empty
    Given invoking occ with "app:disable talk_webhook_demo"
    And the command was successful
    And invoking occ with "app:enable talk_webhook_demo"
    And the command was successful
    When invoking occ with "talk:bot:list"
    Then the command was successful
    And the command output contains the text "Webhook Demo"
    And read bot ids from OCC
    And set state no-setup for bot "Webhook Demo" via OCC
      | feature  |
      | webhook  |
      | response |

  Scenario: Simple Webhook Demo bot run
    # Populate default options again
    And invoking occ with "app:disable talk_webhook_demo"
    And the command was successful
    And invoking occ with "app:enable talk_webhook_demo"
    And the command was successful
    And invoking occ with "talk:bot:list"
    And the command was successful
    And the command output contains the text "Webhook Demo"

    # Set up in room
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And read bot ids from OCC
    And setup bot "Webhook Demo" for room "room" via OCC
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Webhook Demo"

    # Webhook Demo
    Given the following talk_webhook_demo app config is set
      | min-length | -1 |
    And user "participant1" sends message "- [ ] Before call" to room "room" with 201
    And wait for 2 seconds
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
      | flags | 1 |
    And user "participant1" sends message "- [ ] Task 1" to room "room" with 201
    And user "participant1" sends message "- [ ] Task 2\n- [ ] Task 3" to room "room" with 201
    And set state enabled for bot "Webhook Demo" via OCC
      | feature  |
      | webhook  |
    And user "participant1" sends message "- [ ] Received but no reaction permission" to room "room" with 201
    And set state enabled for bot "Webhook Demo" via OCC
      | feature  |
      | none     |
    And user "participant1" sends message "- [ ] Not received due to permission" to room "room" with 201
    And set state enabled for bot "Webhook Demo" via OCC
      | feature  |
      | webhook  |
      | response |
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message                                        | messageParameters |
      | room | users     | participant1 | participant1-displayname | - [ ] Not received due to permission           | []                |
      | room | users     | participant1 | participant1-displayname | - [ ] Received but no reaction permission      | []                |
      | room | users     | participant1 | participant1-displayname | - [ ] Task 2\n- [ ] Task 3                     | []                |
      | room | users     | participant1 | participant1-displayname | - [ ] Task 1                                   | []                |
      | room | users     | participant1 | participant1-displayname | - [ ] Before call                              | []                |
    Then user "participant1" leaves call "room" with 200 (v4)
    Then user "participant1" leaves room "room" with 200 (v4)
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType | actorId           | actorDisplayName         | message                                        | messageParameters |
      | room | bots      | BOT(Webhook Demo) | Webhook Demo (Bot)       | # Call summary - room\n\n{DATE}\n\n## Attendees\n- participant1-displayname\n\n## Tasks\n- [ ] Task 1\n- [ ] Task 2\n- [ ] Task 3\n- [ ] Received but no reaction permission | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Not received due to permission           | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Received but no reaction permission      | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Task 2\n- [ ] Task 3                     | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Task 1                                   | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Before call                              | []                |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Before call" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Task 1" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
      | bots      | BOT(Webhook Demo) | Webhook Demo (Bot) | 👍       |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Task 2\n- [ ] Task 3" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
      | bots      | BOT(Webhook Demo) | Webhook Demo (Bot) | 👍       |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Received but no reaction permission" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Not received due to permission" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |

    # Different states bot
    # Already enabled
    And user "participant1" sets up bot "Webhook Demo" for room "room" with 200 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Webhook Demo"
    # Disabling
    And user "participant1" removes bot "Webhook Demo" for room "room" with 200 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
    # Enabling
    And user "participant1" sets up bot "Webhook Demo" for room "room" with 201 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Webhook Demo"

    # No-setup
    And set state no-setup for bot "Webhook Demo" via OCC

    ## Failed removing
    And user "participant1" removes bot "Webhook Demo" for room "room" with 400 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Webhook Demo"

    ## Failed adding
    And remove bot "Webhook Demo" for room "room" via OCC
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
    And user "participant1" sets up bot "Webhook Demo" for room "room" with 400 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty

  Scenario: Registering a bot with invalid parameters
    When invoking occ with "talk:bot:install  S3CR3T U"
    Then the command failed with exit code 1
    And the command output contains the text "The provided name is too short"
    When invoking occ with "talk:bot:install Bot S3CR3T U"
    Then the command failed with exit code 1
    And the command output contains the text "The provided secret is too short"
    When invoking occ with "talk:bot:install Bot Secret:1234567890123456789012345678901234567890 U"
    Then the command failed with exit code 1
    And the command output contains the text "The provided URL is not a valid URL"

  Scenario: Registering the same webhook or secret twice
    Given invoking occ with "talk:bot:install Bot Secret:1234567890123456789012345678901234567890 https://localhost/bot1"
    And the command was successful
    When invoking occ with "talk:bot:install Bot Secret:1234567890123456789012345678901234567890 https://localhost/bot1"
    Then the command failed with exit code 2
    And the command output contains the text "Bot with the same URL is already registered"
    When invoking occ with "talk:bot:install Bot Secret:1234567890123456789012345678901234567890 https://localhost/bot2"
    Then the command failed with exit code 3
    And the command output contains the text "Bot with the same secret is already registered"

  Scenario: Set up conversation bot errors
    Given invoking occ with "talk:bot:install ErrorBot Secret:1234567890123456789012345678901234567890 https://localhost/bot1"
    And the command was successful
    And read bot ids from OCC
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When invoking occ with "talk:bot:setup 2147483647 invalid-token"
    Then the command failed with exit code 1
    And the command output contains the text "Bot could not be found by id: 2147483647"
    When invoking occ with "talk:bot:setup BOT(ErrorBot) invalid-token"
    Then the command failed with exit code 2
    And the command output contains the text "Conversation could not be found by token: invalid-token"
    When invoking occ with "talk:bot:setup BOT(ErrorBot) ROOM(room)"
    And the command was successful
    And invoking occ with "talk:bot:setup BOT(ErrorBot) ROOM(room)"
    Then the command failed with exit code 3
    And the command output contains the text "Bot is already set up for the conversation"

  Scenario: Bot with all features
    Given invoking occ with "talk:bot:install Bot Secret1234567890123456789012345678901234567890 https://localhost/bot1"
    And the command was successful
    And read bot ids from OCC
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And invoking occ with "talk:bot:setup BOT(Bot) ROOM(room1)"
    And the command was successful
    And user "participant1" sends message "Message 1" to room "room1" with 201
    When Bot "Bot" sends a message for room "room1" with 201 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | message   | Response 1 |
      | replyTo   | Message 1  |
    When Bot "Bot" sends a reaction for room "room1" with 201 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId           | actorDisplayName         | message    | messageParameters | parentMessage |
      | room1 | bots      | BOT(Bot)          | Bot (Bot)                | Response 1 | []                | Message 1     |
      | room1 | users     | participant1      | participant1-displayname | Message 1  | []                |               |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId           | actorDisplayName | reaction |
      | bots      | BOT(Bot)          | Bot (Bot)        | 👍       |
    When Bot "Bot" removes a reaction for room "room1" with 200 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId           | actorDisplayName | reaction |

  # Unchanged from above
  Scenario: Bot with response only feature
    Given invoking occ with "talk:bot:install Bot1 Secret1234567890123456789012345678901234567890 https://localhost/bot1 --feature=response"
    And the command was successful
    And read bot ids from OCC
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And invoking occ with "talk:bot:setup BOT(Bot1) ROOM(room1)"
    And the command was successful
    And user "participant1" sets notifications to all for room "room1" (v4)
    And user "participant1" sends message "Message 1" to room "room1" with 201
    When Bot "Bot1" sends a message for room "room1" with 201 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | message   | Response 1 |
      | replyTo   | Message 1  |
    When Bot "Bot1" sends a reaction for room "room1" with 201 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId           | actorDisplayName         | message    | messageParameters | parentMessage |
      | room1 | bots      | BOT(Bot1)         | Bot1 (Bot)               | Response 1 | []                | Message 1     |
      | room1 | users     | participant1      | participant1-displayname | Message 1  | []                |               |
    And user participant1 has the following notifications
      | app    | object_type | object_id        | subject                                                          |
      | spreed | chat        | room1/Message 1  | Bot1 (Bot) reacted with 👍 to your message in conversation room1 |
      | spreed | chat        | room1/Response 1 | Bot1 (Bot) replied to your message in conversation room1         |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId            | actorDisplayName  | reaction |
      | bots      | BOT(Bot1)          | Bot1 (Bot)        | 👍       |
    When Bot "Bot1" removes a reaction for room "room1" with 200 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId           | actorDisplayName | reaction |

  Scenario: Bot with receive-only feature
    Given invoking occ with "talk:bot:install Bot Secret1234567890123456789012345678901234567890 https://localhost/bot1 --feature=webhook"
    And the command was successful
    And read bot ids from OCC
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And invoking occ with "talk:bot:setup BOT(Bot) ROOM(room1)"
    And the command was successful
    And user "participant1" sends message "Message 1" to room "room1" with 201
    When Bot "Bot" sends a message for room "room1" with 401 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | message   | Response 1 |
      | replyTo   | Message 1  |
    When Bot "Bot" sends a reaction for room "room1" with 401 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId           | actorDisplayName         | message    | messageParameters | parentMessage |
      | room1 | users     | participant1      | participant1-displayname | Message 1  | []                |               |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId           | actorDisplayName | reaction |
    When Bot "Bot" removes a reaction for room "room1" with 401 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |

  Scenario: Bot from another room
    Given invoking occ with "talk:bot:install Bot Secret1234567890123456789012345678901234567890 https://localhost/bot1 --feature=response"
    And the command was successful
    And read bot ids from OCC
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" creates room "room2" (v4)
      | roomType | 2 |
      | roomName | room2 |
    And invoking occ with "talk:bot:setup BOT(Bot) ROOM(room2)"
    And the command was successful
    And user "participant1" sends message "Message 1" to room "room1" with 201
    # Unauthenticated on actual room
    When Bot "Bot" sends a message for room "room1" with 401 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | message   | Response 1 |
      | replyTo   | Message 1  |
    # Bad requeston bot room
    When Bot "Bot" sends a message for room "room2" with 400 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | message   | Response 1 |
      | replyTo   | Message 1  |
    # Unauthenticated on actual room
    When Bot "Bot" sends a reaction for room "room1" with 401 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    # Bad request on bot room
    When Bot "Bot" sends a reaction for room "room2" with 400 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" sees the following messages in room "room1" with 200
      | room  | actorType | actorId           | actorDisplayName         | message    | messageParameters | parentMessage |
      | room1 | users     | participant1      | participant1-displayname | Message 1  | []                |               |
    Then user "participant1" sees the following messages in room "room2" with 200
      | room  | actorType | actorId           | actorDisplayName         | message    | messageParameters | parentMessage |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId           | actorDisplayName | reaction |
    # Unauthenticated on actual room
    When Bot "Bot" removes a reaction for room "room1" with 401 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    # Bad request on bot room
    When Bot "Bot" removes a reaction for room "room2" with 404 (v1)
      | secret | Secret1234567890123456789012345678901234567890 |
      | messageId | Message 1 |
      | reaction  | 👍 |
    Then user "participant1" retrieve reactions "👍" of message "Message 1" in room "room1" with 200
      | actorType | actorId           | actorDisplayName | reaction |

  Scenario: Editing and deleting messages returns a 202 status code with a bot
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    And user "participant1" deletes message "Message 1 - Edit 1" from room "room" with 200
    Given invoking occ with "talk:bot:install Bot Secret1234567890123456789012345678901234567890 https://localhost/bot1 --feature=webhook"
    And the command was successful
    And read bot ids from OCC
    And invoking occ with "talk:bot:setup BOT(Bot) ROOM(room)"
    And the command was successful
    When user "participant1" sends message "Message 2" to room "room" with 201
    Then user "participant1" edits message "Message 2" in room "room" to "Message 2 - Edit 2" with 202
    And user "participant1" deletes message "Message 2 - Edit 2" from room "room" with 202

  Scenario: Uninstalling the bot with invalid parameters
    Given invoking occ with "talk:bot:list"
    Then the command was successful
    And the command output is empty

    # Test uninstall with no parameters
    When invoking occ with "talk:bot:uninstall"
    Then the command failed with exit code 1
    And the command output contains the text "URL is required when no ID is given"

    # Test uninstall with invalid ID
    When invoking occ with "talk:bot:uninstall 2147483647"
    Then the command failed with exit code 1
    And the command output contains the text "Bot not found"

    # Test uninstall with invalid URL
    When invoking occ with "talk:bot:uninstall --url=https://example.tld"
    Then the command failed with exit code 1
    And the command output contains the text "Bot not found"

  Scenario: Cannot enable bots in a former one-to-one room
    Given invoking occ with "talk:bot:list"
    Then the command was successful
    And the command output is empty
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room" (v4)
    When user "participant2" is deleted
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 5    | 1               |
    And invoking occ with "app:enable talk_webhook_demo"
    And the command was successful
    And invoking occ with "talk:bot:list room-name:room"
    Then the command was successful
    And the command output is empty
    And read bot ids from OCC
    And user "participant1" sets up bot "Webhook Demo" for room "room" with 400 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
