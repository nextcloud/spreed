Feature: conversation-4/channel

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Creating a channel restricts posting and calls
    Given user "participant1" creates room "channel" (v4)
      | roomType    | 2       |
      | roomName    | channel |
      | listable    | 1       |
      | permissions | 257     |
      | preset      | channel |
    And user "participant1" adds user "participant2" to room "channel" with 200 (v4)
    # Attribute 8 (channel) is set and calls are stripped for the moderator
    Then user "participant1" is participant of the following rooms (v4)
      | id      | type | participantType | attributes | listable | permissions |
      | channel | 2    | 1               | 8          | 1        | LAVPMR      |
    # Everybody else can only react
    Then user "participant2" is participant of the following rooms (v4)
      | id      | type | participantType | attributes | listable | permissions |
      | channel | 2    | 3               | 8          | 1        | CR          |

  Scenario: Calls are not possible in a channel
    Given user "participant1" creates room "channel" (v4)
      | roomType    | 2       |
      | roomName    | channel |
      | permissions | 257     |
      | preset      | channel |
    And user "participant1" adds user "participant2" to room "channel" with 200 (v4)
    And user "participant1" joins room "channel" with 200 (v4)
    And user "participant2" joins room "channel" with 200 (v4)
    # Not even a moderator can start a call
    Then user "participant1" joins call "channel" with 403 (v4)
    And user "participant2" joins call "channel" with 403 (v4)

  Scenario: The participants list of a channel is only available to moderators
    Given user "participant1" creates room "channel" (v4)
      | roomType    | 2       |
      | roomName    | channel |
      | permissions | 257     |
      | preset      | channel |
    And user "participant1" adds user "participant2" to room "channel" with 200 (v4)
    Then user "participant1" sees the following attendees in room "channel" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
    But user "participant2" sees the following attendees in room "channel" with 403 (v4)

  Scenario: A channel can still be left
    Given user "participant1" creates room "channel" (v4)
      | roomType    | 2       |
      | roomName    | channel |
      | permissions | 257     |
      | preset      | channel |
    And user "participant1" adds user "participant2" to room "channel" with 200 (v4)
    Then user "participant2" removes themselves from room "channel" with 200 (v4)
