Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "group1" exists
    And user "participant2" is member of group "group1"

  Scenario: Owner invites a group
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |

  Scenario: Owner start a chat with a group
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |

  Scenario: User is added to a group which is a member of a chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
    And user "participant3" is member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | users      | participant3 | 3               |

  Scenario: User is removed from a group which is a member of a chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
    And user "participant2" is not member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |

  Scenario: User that was already a member has their group added to a chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
      | groups     | group1       | 3               |

  Scenario: User that was self-joined has their group added to a chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 5               |
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
      | groups     | group1       | 3               |

  Scenario: User that was already a member is added to a group which is a member of a chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | users      | participant3 | 3               |
    And user "participant3" is member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | users      | participant3 | 3               |

  Scenario: User that was self-joined is added to a group which is a member of a chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | users      | participant3 | 5               |
    And user "participant3" is member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | users      | participant3 | 3               |

  Scenario: User that was already a member is removed from a group which is a member of a chat
    # This might not be what most people desire but fixing this would mean we
    # need to keep multiple records per user whether they were added manually before etc.
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds group "group1" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
      | groups     | group1       | 3               |
    And user "participant2" is not member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
