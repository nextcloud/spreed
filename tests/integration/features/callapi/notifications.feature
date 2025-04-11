Feature: callapi/notifications

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Normal call notification
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Then user "participant2" checks call notification for "room" with 201 (v4)
    Given user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" checks call notification for "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |
    Given user "participant2" joins call "room" with 200 (v4)
    Then user "participant2" checks call notification for "room" with 404 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Missed call notification
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Then user "participant2" checks call notification for "room" with 201 (v4)
    Given user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" checks call notification for "room" with 200 (v4)
    Then user "participant2" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                          | silent | messageParameters |
      | room | users         | participant1 | call_started         | {actor} started a call           | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
      | room | users         | participant1 | user_added           | {actor} added you                | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | {actor} created the conversation | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |
    Given user "participant1" leaves call "room" with 200 (v4)
    Then user "participant2" checks call notification for "room" with 201 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                         |
      | spreed | call        | room      | You missed a group call in room |

  Scenario: Silent call does not trigger notifications
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then user "participant2" sees the following system messages in room "room" with 200
      | room | actorType     | actorId      | systemMessage        | message                          | silent | messageParameters |
      | room | users         | participant1 | call_started         | {actor} started a silent call    | true   | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
      | room | users         | participant1 | user_added           | {actor} added you                | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"},"user":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"participant2"}} |
      | room | users         | participant1 | conversation_created | {actor} created the conversation | !ISSET | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}} |
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" leaves call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Silent call with dedicated ping does trigger notifications
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" loads attendees attendee ids in room "room" (v4)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" pings user "participant2" to join call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |

  Scenario: Calling an attendee that is in DND throws an error 'status' message with 400
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant2" sets status to "dnd" with 200 (v1)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" pings user "participant2" to join call "room" with 400 (v4)

  Scenario: Lobby: No call notification sent for users that are blocked by the lobby
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Lobby: Call notification sent to users with ignore lobby permissions
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sets permissions for "participant2" in room "room" to "L" with 200 (v4)
    And user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |

  Scenario: Lobby: Call notification sent to moderators
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |

  Scenario: Lobby: Call notification wiped if lobby enabled afterwards
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    When user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |
    And user "participant1" sets lobby state for room "room" to "non moderators" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Pinging a user that is removed gives 404
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant1" loads attendees attendee ids in room "room" (v4)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    And user "participant1" removes user "participant2" from room "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" pings user "participant2" to join call "room" with 404 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Pinging a user that is removed gives 404
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant2" creates room "room2" (v4)
      | roomType | 2 |
      | roomName | room2 |
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant1" loads attendees attendee ids in room "room" (v4)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" pings user "participant1" attendeeIdPlusOne to join call "room" with 404 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
