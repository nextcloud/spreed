Feature: callapi/recording
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Start and stop video recording
    Given recording server is started
    Given signaling server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And reset signaling server requests
    When user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    Then signaling server received the following requests
      | token | data |
      | room1 | {"type":"message","message":{"data":{"type":"recording","recording":{"status":3}}}} |
      | room1 | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":2,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":{"date":"ACTIVE_SINCE()","timezone_type":3,"timezone":"UTC"},"sip-enabled":0,"description":""}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    And reset signaling server requests
    When user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    Then signaling server received the following requests
      # Nothing changes until the recording backend confirms the stop
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    And reset signaling server requests
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    Then signaling server received the following requests
      | token | data |
      | room1 | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | room1 | {"type":"message","message":{"data":{"type":"recording","recording":{"status":0}}}} |
      | room1 | {"type":"update","update":{"userids":["participant1"],"properties":{"name":"Private conversation","type":2,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":{"date":"ACTIVE_SINCE()","timezone_type":3,"timezone":"UTC"},"sip-enabled":0,"description":""}}} |
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_stopped    |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Start and stop audio recording
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    When user "participant1" starts "audio" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":2,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 4             |
    And recording server sent started request for "audio" recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage           |
      | room1 | users     | participant1 | participant1-displayname | audio_recording_started |
      | room1 | users     | participant1 | participant1-displayname | call_started            |
      | room1 | users     | participant1 | participant1-displayname | conversation_created    |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    When user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage           |
      | room1 | users     | participant1 | participant1-displayname | audio_recording_stopped |
      | room1 | users     | participant1 | participant1-displayname | audio_recording_started |
      | room1 | users     | participant1 | participant1-displayname | call_started            |
      | room1 | users     | participant1 | participant1-displayname | conversation_created    |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: No system message should be shown when the call was just ended for everyone
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    When user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" ends call "room1" with 200 (v4)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":[]} |
    And recording server sent stopped request for recording in room "room1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | call_ended_everyone  |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: No system message should be shown when the call was ended by the last one leaving
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    When user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" leaves call "room1" with 200 (v4)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":[]} |
    And recording server sent stopped request for recording in room "room1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | call_ended           |
      | room1 | users     | participant1 | participant1-displayname | call_left            |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Recording failed to start
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    When recording server sent failed request for recording in room "room1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 5             |

  Scenario: Video recording failed
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    When recording server sent failed request for recording in room "room1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | systemMessage        |
      | room1 | guests    | failed-to-get-session |                          | recording_failed     |
      | room1 | users     | participant1          | participant1-displayname | recording_started    |
      | room1 | users     | participant1          | participant1-displayname | call_started         |
      | room1 | users     | participant1          | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 5             |

  Scenario: Start and stop recording again after the previous one failed to start
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    And recording server sent failed request for recording in room "room1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 5             |
    When user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    When user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1 | participant1-displayname | recording_stopped    |
      | room1 | users     | participant1 | participant1-displayname | recording_started    |
      | room1 | users     | participant1 | participant1-displayname | call_started         |
      | room1 | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Start and stop recording again after the previous one failed
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    And recording server sent failed request for recording in room "room1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 5             |
    When user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 3             |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1          | participant1-displayname | recording_started    |
      | room1 | guests    | failed-to-get-session |                          | recording_failed     |
      | room1 | users     | participant1          | participant1-displayname | recording_started    |
      | room1 | users     | participant1          | participant1-displayname | call_started         |
      | room1 | users     | participant1          | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    When user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1          | participant1-displayname | recording_stopped    |
      | room1 | users     | participant1          | participant1-displayname | recording_started    |
      | room1 | guests    | failed-to-get-session |                          | recording_failed     |
      | room1 | users     | participant1          | participant1-displayname | recording_started    |
      | room1 | users     | participant1          | participant1-displayname | call_started         |
      | room1 | users     | participant1          | participant1-displayname | conversation_created |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Get error when start|stop recording and already did this
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    When user "participant1" starts "audio" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":2,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "audio" recording in room "room1" as "participant1" with 200
    And user "participant1" starts "audio" recording in room "room1" with 400 (v1)
    Then the response error matches with "recording"
    And recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    When user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    And user "participant1" stops recording in room "room1" with 200 (v1)
    Then recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    When user "participant1" starts "video" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":1,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "video" recording in room "room1" as "participant1" with 200
    And user "participant1" starts "video" recording in room "room1" with 400 (v1)
    Then the response error matches with "recording"
    And recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 1             |
    When user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And user "participant1" stops recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":{"actor":{"type":"users","id":"participant1"}}} |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    And user "participant1" stops recording in room "room1" with 200 (v1)
    Then recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Get error when try to start recording with invalid status
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    When user "participant1" starts "invalid" recording in room "room1" with 400 (v1)
    Then the response error matches with "status"
    And recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Manager try without success to start recording when signaling is internal
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    When user "participant1" starts "video" recording in room "room1" with 400 (v1)
    Then the response error matches with "config"
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    When user "participant1" starts "audio" recording in room "room1" with 400 (v1)
    Then the response error matches with "config"
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Get error when non moderator/owner try to start recording
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" adds user "participant2" to room "room1" with 200 (v4)
    And user "participant2" joins room "room1" with 200 (v4)
    And user "participant2" joins call "room1" with 200 (v4)
    When user "participant2" starts "video" recording in room "room1" with 403 (v1)
    And user "participant2" starts "audio" recording in room "room1" with 403 (v1)
    Then recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Get error when try to start recording and no call started
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    When user "participant1" starts "video" recording in room "room1" with 400 (v1)
    Then the response error matches with "call"
    And recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    When user "participant1" starts "audio" recording in room "room1" with 400 (v1)
    Then the response error matches with "call"
    And recording server received the following requests
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Store recording with success and create transcript
    Given Fake summary task provider is enabled
    Given the following spreed app config is set
      | call_recording_transcription | yes |
      | call_recording_summary       | yes |
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    When user "participant1" store recording file "/img/join_call.ogg" in room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                      |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call.ogg. |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    And repeating run "OC\TaskProcessing\SynchronousBackgroundJob" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                       |
      | spreed | recording   | room1     | Call summary now available   | The summary for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call - summary.md. |
      | spreed | recording   | room1     | Transcript now available     | The transcript for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call.md. |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call.ogg.  |
    When user "participant1" shares file from the last notification to room "room1" with 200 (v1)
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1          | participant1-displayname | conversation_created |
    And user "participant1" sees the following messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | messageType  | message | messageParameters |
      | room1 | users     | participant1          | participant1-displayname | record-audio | {file}  | "IGNORE"          |
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                       |
      | spreed | recording   | room1     | Call summary now available   | The summary for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call - summary.md. |
      | spreed | recording   | room1     | Transcript now available     | The transcript for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call.md. |
    When user "participant1" shares file from the last notification to room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                       |
      | spreed | recording   | room1     | Call summary now available   | The summary for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call - summary.md. |
    When user "participant1" shares file from the first notification to room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                       |
    Then user "participant1" sees the following system messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | systemMessage        |
      | room1 | users     | participant1          | participant1-displayname | conversation_created |
    And user "participant1" sees the following messages in room "room1" with 200 (v1)
      | room  | actorType | actorId               | actorDisplayName         | messageType  | message | messageParameters |
      | room1 | users     | participant1          | participant1-displayname | record-audio | {file}  | "IGNORE"          |
      | room1 | users     | participant1          | participant1-displayname | record-audio | {file}  | "IGNORE"          |
      | room1 | users     | participant1          | participant1-displayname | record-audio | {file}  | "IGNORE"          |

  Scenario: Store recording with success but fail to transcript
    Given Fake summary task provider is enabled
    Given the following spreed app config is set
      | call_recording_transcription | yes |
      | call_recording_summary       | yes |
    Given the following testing app config is set
      | fail-testing-audio2text | yes |
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    When user "participant1" store recording file "/img/leave_call.ogg" in room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                      |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.ogg. |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    And repeating run "OC\TaskProcessing\SynchronousBackgroundJob" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                             | message                                                                                        |
      | spreed | recording   | room1     | Failed to transcript call recording | The server failed to transcript the recording at /Talk/Recording/ROOM(room1)/leave_call.ogg for the call in room1. Please reach out to the administration. |
      | spreed | recording   | room1     | Call recording now available        | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.ogg.  |

  Scenario: Store recording and transcript with success but fail to summarize
    Given Fake summary task provider is enabled
    Given the following spreed app config is set
      | call_recording_transcription | yes |
      | call_recording_summary       | yes |
    Given the following testing app config is set
      | fail-testing-text2text-summary | yes |
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    When user "participant1" store recording file "/img/leave_call.ogg" in room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                      |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.ogg. |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    And repeating run "OC\TaskProcessing\SynchronousBackgroundJob" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                             | message                                                                                        |
      | spreed | recording   | room1     | Failed to summarize call recording  | The server failed to summarize the recording at /Talk/Recording/ROOM(room1)/leave_call.ogg for the call in room1. Please reach out to the administration. |
      | spreed | recording   | room1     | Transcript now available            | The transcript for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.md. |
      | spreed | recording   | room1     | Call recording now available        | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.ogg.  |

  Scenario: Store recording and transcript with success but summarize is off
    Given Fake summary task provider is enabled
    Given the following spreed app config is set
      | call_recording_transcription | yes |
      | call_recording_summary       | no |
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    When user "participant1" store recording file "/img/leave_call.ogg" in room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                      |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.ogg. |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    And repeating run "OC\TaskProcessing\SynchronousBackgroundJob" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                             | message                                                                                          |
      | spreed | recording   | room1     | Transcript now available            | The transcript for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.md.  |
      | spreed | recording   | room1     | Call recording now available        | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/leave_call.ogg.  |

  Scenario: Store recording and summarize with success but transcript is off
    Given Fake summary task provider is enabled
    Given the following spreed app config is set
      | call_recording_transcription | no  |
      | call_recording_summary       | yes |
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    When user "participant1" store recording file "/img/join_call.ogg" in room "room1" with 200 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                        |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call.ogg. |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |
    And repeating run "OC\TaskProcessing\SynchronousBackgroundJob" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id | subject                      | message                                                                                               |
      | spreed | recording   | room1     | Call summary now available   | The summary for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call - summary.md. |
      | spreed | recording   | room1     | Call recording now available | The recording for the call in room1 was uploaded to /Talk/Recording/ROOM(room1)/join_call.ogg.        |

  Scenario: Store recording with failure exceeding the upload_max_filesize
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    When user "participant1" store recording file "big" in room "room1" with 400 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type           | object_id | subject                         |
      | spreed | recording_information | room1     | Failed to upload call recording |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Store recording with failure exceeding the post_max_size
    Given recording server is started
    Given user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "audio" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":2,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "audio" recording in room "room1" as "participant1" with 200
    When user "participant1" ends call "room1" with 200 (v4)
    Then recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":[]} |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    When user "NULL" store recording file "big" in room "room1" with 400 (v1)
    Then user "participant1" has the following notifications
      | app    | object_type           | object_id | subject                         |
      | spreed | recording_information | room1     | Failed to upload call recording |

  Scenario: Stop recording automatically when end the call
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "audio" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":2,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "audio" recording in room "room1" as "participant1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    When user "participant1" ends call "room1" with 200 (v4)
    Then recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":[]} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    And recording server sent stopped request for recording in room "room1" as "participant1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Stop recording automatically when the last participant leaves the call
    Given recording server is started
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    And user "participant1" starts "audio" recording in room "room1" with 200 (v1)
    And recording server received the following requests
      | token | data                                                         |
      | room1 | {"type":"start","start":{"status":2,"owner":"participant1","actor":{"type":"users","id":"participant1"}}} |
    And recording server sent started request for "audio" recording in room "room1" as "participant1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    When user "participant1" leaves call "room1" with 200 (v4)
    Then recording server received the following requests
      | token | data             |
      | room1 | {"type":"stop","stop":[]} |
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 2             |
    And recording server sent stopped request for recording in room "room1" with 200
    And user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | callRecording |
      | 2    | room1 | 0             |

  Scenario: Recording consent required by admin
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 1 |
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 400 (v4)
    And user "participant1" joins call "room1" with 400 (v4)
      | recordingConsent | 0 |
    And user "participant1" joins call "room1" with 200 (v4)
      | recordingConsent | 1 |

  Scenario: Recording consent optional by admin enabled by moderator
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 2 |
    And user "participant1" creates room "room1" (v4)
      | roomType | 2 |
      | roomName | room1 |
    And user "participant1" joins room "room1" with 200 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
    # Can not enable consent when a call is on-going …
    When user "participant1" sets the recording consent to 1 for room "room1" with 400 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room1 | 0                |
    When user "participant1" leaves call "room1" with 200 (v4)
    And user "participant1" sets the recording consent to 1 for room "room1" with 200 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room1 | 1                |
    And user "participant1" joins call "room1" with 400 (v4)
    And user "participant1" joins call "room1" with 200 (v4)
      | recordingConsent | 1 |
    # … but can disable consent when a call is on-going
    When user "participant1" sets the recording consent to 0 for room "room1" with 200 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room1 | 0                |
    When user "participant1" leaves call "room1" with 200 (v4)
    # Invalid value on conversation level
    When user "participant1" sets the recording consent to 2 for room "room1" with 400 (v4)
    Then user "participant1" is participant of the following unordered rooms (v4)
      | type | name  | recordingConsent |
      | 2    | room1 | 0                |
    And the following recording consent is recorded for room "room1"
      | token | actorType | actorId      |
      | room1 | users     | participant1 |
    And the following recording consent is recorded for user "participant1"
      | token | actorType | actorId      |
      | room1 | users     | participant1 |
