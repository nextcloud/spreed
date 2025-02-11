Feature: conversation/add-participant
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
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And reset signaling server requests
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"invite","invite":{"userids":["participant2"],"alluserids":["participant1","participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"participant-list":"refresh"}}} |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
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

  Scenario: Getting participant suggestions in a private room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" gets the following collaborator suggestions in room "room" for "particip" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
      | users  | participant3 | participant3-displayname |
    And user "participant1" gets the following collaborator suggestions in room "room" for "participant2" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
    And user "participant3" gets the following collaborator suggestions in room "room" for "participant2" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" gets the following collaborator suggestions in room "room" for "participant2" with 200
    And user "participant3" gets the following collaborator suggestions in room "room" for "participant2" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |

  Scenario: Filter out already added entries
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And group "Filtered group" exists
    And team "Filtered team" exists
    And add user "participant1" to team "Filtered team"
    When user "participant1" gets the following collaborator suggestions in room "room" for "Filtered" with 200
      | source  | id                     | label          |
      | circles | TEAM_ID(Filtered team) | Filtered team  |
      | groups  | Filtered group         | Filtered group |
    And user "participant1" adds group "Filtered group" to room "room" with 200 (v4)
    And user "participant1" adds team "Filtered team" to room "room" with 200 (v4)
    When user "participant1" gets the following collaborator suggestions in room "room" for "Filtered" with 200
      | source  | id           | label                    |

  Scenario: Getting participant suggestions in a public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" gets the following collaborator suggestions in room "room" for "particip" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
      | users  | participant3 | participant3-displayname |
    And user "participant1" gets the following collaborator suggestions in room "room" for "participant2" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
    And user "participant3" gets the following collaborator suggestions in room "room" for "participant2" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
    And user "guest" joins room "room" with 200 (v4)
    And user "guest" gets the following collaborator suggestions in room "room" for "participant2" with 401
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" gets the following collaborator suggestions in room "room" for "participant2" with 200
    And user "participant3" gets the following collaborator suggestions in room "room" for "participant2" with 200
      | source | id           | label                    |
      | users  | participant2 | participant2-displayname |
    And user "guest" gets the following collaborator suggestions in room "room" for "participant2" with 401
