Feature: conversation-4/announcement

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: An announcement inherits all restrictions of a channel
    Given user "participant1" creates room "announcement" (v4)
      | roomType    | 2            |
      | roomName    | announcement |
      | listable    | 0            |
      | permissions | 257          |
      | preset      | announcement |
    And user "participant1" adds user "participant2" to room "announcement" with 200 (v4)
    # Attributes 8 (channel) and 16 (announcement) are both set, so all channel restrictions apply
    Then user "participant1" is participant of the following rooms (v4)
      | id           | type | participantType | attributes | listable | permissions |
      | announcement | 2    | 1               | 24         | 0        | LAVPMR      |
    Then user "participant2" is participant of the following rooms (v4)
      | id           | type | participantType | attributes | listable | permissions |
      | announcement | 2    | 3               | 24         | 0        | CR          |
    # Inherited from the channel: no calls and no participants list for non-moderators
    And user "participant1" joins room "announcement" with 200 (v4)
    And user "participant2" joins room "announcement" with 200 (v4)
    Then user "participant1" joins call "announcement" with 403 (v4)
    And user "participant2" sees the following attendees in room "announcement" with 403 (v4)

  Scenario: Announcements notify about all messages by default
    Given user "participant1" creates room "announcement" (v4)
      | roomType    | 2            |
      | roomName    | announcement |
      | permissions | 257          |
      | preset      | announcement |
    And user "participant1" adds user "participant2" to room "announcement" with 200 (v4)
    # 1 is "always notify", while a regular group conversation would use the admin default
    Then user "participant2" is participant of the following rooms (v4)
      | id           | type | notificationLevel |
      | announcement | 2    | 1                 |
    # It is only a default, so it can still be changed afterwards
    And user "participant2" sets notifications to mention for room "announcement" (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id           | type | notificationLevel |
      | announcement | 2    | 2                 |

  Scenario: Non-moderators can not leave an announcement
    Given user "participant1" creates room "announcement" (v4)
      | roomType    | 2            |
      | roomName    | announcement |
      | permissions | 257          |
      | preset      | announcement |
    And user "participant1" adds user "participant2" to room "announcement" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id           | type | canLeaveConversation |
      | announcement | 2    | no                   |
    And user "participant2" removes themselves from room "announcement" with 400 (v4)
    # But a moderator can still remove them
    And user "participant1" removes "participant2" from room "announcement" with 200 (v4)
    And user "participant2" is not participant of room "announcement" (v4)

  Scenario: Moderators can leave an announcement
    Given user "participant1" creates room "announcement" (v4)
      | roomType    | 2            |
      | roomName    | announcement |
      | permissions | 257          |
      | preset      | announcement |
    And user "participant1" adds user "participant2" to room "announcement" with 200 (v4)
    And user "participant1" promotes "participant2" in room "announcement" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id           | type | canLeaveConversation |
      | announcement | 2    | yes                  |
    And user "participant2" removes themselves from room "announcement" with 200 (v4)
