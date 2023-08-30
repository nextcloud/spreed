Feature: chat/bots
  Background:
    Given user "participant1" exists

  Scenario: Installing the call summary bot
    Given invoking occ with "talk:bot:list"
    Then the command was successful
    And the command output is empty
    Given invoking occ with "app:disable call_summary_bot"
    And the command was successful
    And invoking occ with "app:enable call_summary_bot"
    And the command was successful
    When invoking occ with "talk:bot:list"
    Then the command was successful
    And the command output contains the text "Call summary"
    And read bot ids from OCC
    And set state no-setup for bot "Call summary" via OCC
      | feature  |
      | webhook  |
      | response |

  Scenario: Simple call summary bot run
    # Populate default options again
    And invoking occ with "app:disable call_summary_bot"
    And the command was successful
    And invoking occ with "app:enable call_summary_bot"
    And the command was successful
    And invoking occ with "talk:bot:list"
    And the command was successful
    And the command output contains the text "Call summary"

    # Set up in room
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And read bot ids from OCC
    And setup bot "Call summary" for room "room" via OCC
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Call summary"

    # Call summary
    Given the following call_summary_bot app config is set
      | min-length | -1 |
    And user "participant1" sends message "- [ ] Before call" to room "room" with 201
    And wait for 2 seconds
    Then user "participant1" joins room "room" with 200 (v4)
    Then user "participant1" joins call "room" with 200 (v4)
      | flags | 1 |
    And user "participant1" sends message "- [ ] Task 1" to room "room" with 201
    And user "participant1" sends message "- [ ] Task 2\n- [ ] Task 3" to room "room" with 201
    And set state enabled for bot "Call summary" via OCC
      | feature  |
      | webhook  |
    And user "participant1" sends message "- [ ] Received but no reaction permission" to room "room" with 201
    And set state enabled for bot "Call summary" via OCC
      | feature  |
      | none     |
    And user "participant1" sends message "- [ ] Not received due to permission" to room "room" with 201
    And set state enabled for bot "Call summary" via OCC
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
      | room | bots      | BOT(Call summary) | Call summary (Bot)       | # Call summary - room\n\n{DATE}\n\n## Attendees\n- participant1-displayname\n\n## Tasks\n- [ ] Task 1\n- [ ] Task 2\n- [ ] Task 3\n- [ ] Received but no reaction permission | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Not received due to permission           | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Received but no reaction permission      | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Task 2\n- [ ] Task 3                     | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Task 1                                   | []                |
      | room | users     | participant1      | participant1-displayname | - [ ] Before call                              | []                |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Before call" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Task 1" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
      | bots      | BOT(Call summary) | Call summary (Bot) | 👍       |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Task 2\n- [ ] Task 3" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
      | bots      | BOT(Call summary) | Call summary (Bot) | 👍       |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Received but no reaction permission" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |
    Then user "participant1" retrieve reactions "👍" of message "- [ ] Not received due to permission" in room "room" with 200
      | actorType | actorId           | actorDisplayName   | reaction |

    # Different states bot
    # Already enabled
    And user "participant1" sets up bot "Call summary" for room "room" with 200 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Call summary"
    # Disabling
    And user "participant1" removes bot "Call summary" for room "room" with 200 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
    # Enabling
    And user "participant1" sets up bot "Call summary" for room "room" with 201 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Call summary"

    # No-setup
    And set state no-setup for bot "Call summary" via OCC

    ## Failed removing
    And user "participant1" removes bot "Call summary" for room "room" with 400 (v1)
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output contains the text "Call summary"

    ## Failed adding
    And remove bot "Call summary" for room "room" via OCC
    Given invoking occ with "talk:bot:list room-name:room"
    And the command was successful
    And the command output is empty
    And user "participant1" sets up bot "Call summary" for room "room" with 400 (v1)
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
