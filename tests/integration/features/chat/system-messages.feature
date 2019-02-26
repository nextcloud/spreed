Feature: System messages
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"
    And user "participant3" is member of group "attendees1"

  Scenario: Creating an empty room
    When user "participant1" creates room "room"
      | roomType | 2 |
      | roomName | room |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Rename a room
    Given user "participant1" creates room "room"
      | roomType | 2 |
      | roomName | room |
    When user "participant1" renames room "room" to "system test" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_renamed |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Toggle guests
    Given user "participant1" creates room "room"
      | roomType | 2 |
      | roomName | room |
    When user "participant1" makes room "room" public with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | guests_allowed |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" makes room "room" private with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | guests_disallowed |
      | room | users     | participant1 | participant1-displayname | guests_allowed |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Toggle password
    Given user "participant1" creates room "room"
      | roomType | 3 |
      | roomName | room |
    When user "participant1" sets password "123456" for room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | password_set |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" sets password "" for room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | password_removed |
      | room | users     | participant1 | participant1-displayname | password_set |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Creating a group room
    When user "participant1" creates room "room"
      | roomType | 2 |
      | invite   | attendees1 |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Creating a one2one room
    When user "participant1" creates room "room"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Participant escalation
    Given user "participant1" creates room "room"
      | roomType | 2 |
      | roomName | room |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" adds "participant2" to room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" promotes "participant2" in room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | moderator_promoted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" demotes "participant2" in room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | moderator_demoted |
      | room | users     | participant1 | participant1-displayname | moderator_promoted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" removes "participant2" from room "room" with 200
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_removed |
      | room | users     | participant1 | participant1-displayname | moderator_demoted |
      | room | users     | participant1 | participant1-displayname | moderator_promoted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
