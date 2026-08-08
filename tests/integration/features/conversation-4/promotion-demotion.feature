Feature: conversation-4/promotion-demotion
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Owner promotes/demotes moderator
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And reset signaling server requests
    When user "participant1" promotes "participant2" in room "room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true,"comment":[]}}}} |
    # TODO remove handler with "roomModified" in favour of handler with
    # "participantsModified" once the clients no longer expect a
    # "roomModified" message for participant type changes.
      | room  | {"type":"update","update":{"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":""}}} |
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant1" demotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: Moderator promotes/demotes moderator
    Given user "participant3" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" sets permissions for "participant3" in room "room" to "L" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | participant1 | SJLAVPMR    |
      | users      | participant2 | SJLAVPMR    |
      | users      | participant3 | CL           |
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant2" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | participant1 | SJLAVPMR    |
      | users      | participant2 | SJLAVPMR    |
      | users      | participant3 | SJLAVPMR    |
    When user "participant2" demotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | participant1 | SJLAVPMR    |
      | users      | participant2 | SJLAVPMR    |
      | users      | participant3 | SJAVPMR     |

  Scenario: Moderator promotes self-joined user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 5               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant1" demotes "participant2" in room "room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |

  Scenario: User promotes/demotes moderator
    Given user "participant3" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" promotes "participant3" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant1" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |

  Scenario: Owner promotes a user and a moderator to owner and demotes them again
    Given user "participant3" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant3" in room "room" with 200 (v4)
    When user "participant1" promotes "participant2" to "OWNER" in room "room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    When user "participant1" promotes "participant3" to "OWNER" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    When user "participant1" demotes "participant2" to "MODERATOR" in room "room" with 200 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 2               |
    When user "participant1" demotes "participant3" to "USER" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 3               |

  Scenario: Owner demotes themselves to moderator but not to user
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" to "OWNER" in room "room" with 200 (v4)
    When user "participant1" demotes "participant1" to "USER" in room "room" with 403 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |
    When user "participant1" demotes "participant1" to "MODERATOR" in room "room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 2               |

  Scenario: Moderators can not promote to owner or demote an owner
    Given user "participant3" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    When user "participant2" promotes "participant3" to "OWNER" in room "room" with 403 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 3               |
    When user "participant2" demotes "participant1" to "MODERATOR" in room "room" with 403 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 2    | 1               |

  Scenario: Guests and guest moderators can not become owners
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant1" promotes "guest" to "OWNER" in room "room" with 400 (v4)
    And user "participant1" promotes "guest" in room "room" with 200 (v4)
    Then user "participant1" promotes "guest" to "OWNER" in room "room" with 400 (v4)

  Scenario: Owner level can not be changed in a one-to-one conversation
    Given user "participant1" creates room "one-to-one" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" creates room "one-to-one" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" loads attendees attendee ids in room "one-to-one" (v4)
    When user "participant1" demotes "participant2" to "MODERATOR" in room "one-to-one" with 400 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id         | type | participantType |
      | one-to-one | 1    | 1               |

  Scenario: The last moderator can not be demoted
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    And user "participant1" promotes "guest" in room "room" with 200 (v4)
    And user "participant1" promotes "participant2" to "OWNER" in room "room" with 200 (v4)
    And user "participant1" demotes "participant1" to "MODERATOR" in room "room" with 200 (v4)
    And user "participant2" demotes "participant2" to "MODERATOR" in room "room" with 200 (v4)
    # Guest moderators do not count towards the moderators of a conversation
    And user "guest" demotes "participant1" to "USER" in room "room" with 200 (v4)
    When user "guest" demotes "participant2" to "USER" in room "room" with 400 (v4)
    Then user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |

  Scenario: Stranger promotes/demotes moderator
    Given user "participant3" exists
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" promotes "participant3" in room "room" with 404 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    When user "participant1" promotes "participant3" in room "room" with 200 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    When user "participant2" demotes "participant3" in room "room" with 404 (v4)
    Then user "participant3" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
