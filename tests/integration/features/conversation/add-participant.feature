Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |
    And user "participant3" is not participant of room "room"

  Scenario: User invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |
    And user "participant3" is not participant of room "room"
    When user "participant2" adds "participant3" to room "room" with 403
    Then user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |
    And user "participant3" is not participant of room "room"

  Scenario: Moderator invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname, participant2-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname |
    And user "participant3" is not participant of room "room"
    When user "participant2" adds "participant3" to room "room" with 200
    Then user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname, participant2-displayname, participant3-displayname |
    And user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 2               | participant1-displayname, participant2-displayname, participant3-displayname |
    And user "participant3" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |

  Scenario: Moderator invites a user who self-joined
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200
    When user "participant1" adds "participant2" to room "room" with 200
    Then user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname |

  Scenario: Moderator invites a group containing a self-joined user
    Given group "group1" exists
    And user "participant2" is member of group "group1"
    And user "participant3" is member of group "group1"
    And user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200
    # participant3 already present, so it will be skipped
    And user "participant1" adds "participant3" to room "room" with 200
    When user "participant1" adds group "group1" to room "room" with 200
    Then user "participant2" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |
    And user "participant3" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 3               | participant1-displayname, participant2-displayname, participant3-displayname |

  Scenario: Stranger invites a user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" adds "participant2" to room "room" with 404
    Then user "participant1" is participant of the following rooms (v4)
    # FIXME
      | id   | type | participantType | participants |
      | room | 3    | 1               | participant1-displayname |
    And user "participant2" is not participant of room "room"
    And user "participant3" is not participant of room "room"
