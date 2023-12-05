Feature: conversation/group-participants
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "group1" exists
    And group "group2" exists
    And group "rename-group" exists
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

  Scenario: User is removed from a group which is a member of a chat but has a second group
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant2" is member of group "group2"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
    And user "participant1" adds group "group2" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | groups     | group2       | 3               |
    And user "participant2" is not member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | groups     | group2       | 3               |

  Scenario: User is not removed from a chat when one group is removed but has a second group
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant2" is member of group "group2"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
    And user "participant1" adds group "group2" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
      | groups     | group2       | 3               |
    And user "participant1" removes group "group1" from room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 3               |
      | groups     | group2       | 3               |

  Scenario: User is removed from when their last group which is a member of a chat is removed
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
    And user "participant1" removes group "group1" from room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |

  Scenario: Moderator is removed from a group which is a member of a chat but stays in the chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 3               |
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 2               |
    And user "participant2" is not member of group "group1"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 2               |

  Scenario: Group of a moderator is removed from a chat but moderator stays in the chat
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | source | group |
      | invite |group1 |
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | groups     | group1       | 3               |
      | users      | participant2 | 2               |
    And user "participant1" removes group "group1" from room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 2               |

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

  Scenario: Renaming a group reflects in the participant list
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds group "rename-group" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType | displayName              |
      | users      | participant1 | 1               | participant1-displayname |
      | groups     | rename-group | 3               | rename-group             |
    When set display name of group "rename-group" to "rename-group-displayname"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType | displayName              |
      | users      | participant1 | 1               | participant1-displayname |
      | groups     | rename-group | 3               | rename-group-displayname |
