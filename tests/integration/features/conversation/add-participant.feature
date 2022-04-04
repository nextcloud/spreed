Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                                                            |
      | spreed | room        | room      | participant1-displayname invited you to a group conversation: room |
    And user "participant3" is not participant of room "room" (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |

  Scenario: User invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant3" is not participant of room "room" (v4)
    When user "participant2" adds user "participant3" to room "room" with 403 (v4)
    Then user "participant3" has the following notifications
      | app | object_type | object_id | subject |
    And user "participant3" is not participant of room "room" (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |

  Scenario: Moderator invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant3" is not participant of room "room" (v4)
    When user "participant2" adds user "participant3" to room "room" with 200 (v4)
    Then user "participant3" has the following notifications
      | app    | object_type | object_id | subject                                                            |
      | spreed | room        | room      | participant2-displayname invited you to a group conversation: room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 2               |
      | users      | participant3 | 3               |

  Scenario: Moderator invites a user who self-joined
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 5               |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |

  Scenario: Moderator invites a group containing a self-joined user
    Given group "group1" exists
    And user "participant2" is member of group "group1"
    And user "participant3" is member of group "group1"
    And user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    # participant3 already present, so it will be skipped
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    When user "participant1" adds group "group1" to room "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
      | groups     | group1       | 3               |
      | users      | participant3 | 3               |
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: Stranger invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" adds user "participant2" to room "room" with 404 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant2" is not participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
