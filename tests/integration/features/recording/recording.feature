Feature: recording/recording
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Start and stop video recording
    When the following "spreed" app config is set
      | signaling_dev | yes |
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" start "video" recording in room "room1" with 200 (v1)
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" stop recording in room "room1" with 200 (v1)
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_stopped    |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Start and stop audio recording
    When the following "spreed" app config is set
      | signaling_dev | yes |
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" start "audio" recording in room "room1" with 200 (v1)
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage           |
      | room1 | users     | participant1 | participant1-displayname | audio_recording_started |
      | room1 | users     | participant1 | participant1-displayname | conversation_created    |
    When user "participant1" stop recording in room "room1" with 200 (v1)
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage           |
      | room1 | users     | participant1 | participant1-displayname | audio_recording_stopped |
      | room1 | users     | participant1 | participant1-displayname | audio_recording_started |
      | room1 | users     | participant1 | participant1-displayname | conversation_created    |

  Scenario: Manager try without success to start recording when signaling is internal
    When the following "spreed" app config is set
      | signaling_dev | no |
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    Then user "participant1" start "video" recording in room "room1" with 403 (v1)
    Then user "participant1" start "audio" recording in room "room1" with 403 (v1)

  Scenario: Get error when non manager try to start recording
    When the following "spreed" app config is set
      | signaling_dev | yes |
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    Then user "participant2" start "video" recording in room "room1" with 403 (v1)
    Then user "participant2" start "audio" recording in room "room1" with 403 (v1)
