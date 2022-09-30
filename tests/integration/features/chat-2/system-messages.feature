Feature: chat-2/system-messages
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"
    And user "participant3" is member of group "attendees1"

  Scenario: Creating an empty room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Rename a room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" renames room "room" to "system test" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_renamed |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Set a description
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" sets description for room "room" to "New description" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | description_set |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Removes a description
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sets description for room "room" to "New description" with 200 (v4)
    When user "participant1" sets description for room "room" to "" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | description_removed |
      | room | users     | participant1 | participant1-displayname | description_set |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Toggle guests
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" makes room "room" public with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | guests_allowed |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" makes room "room" private with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | guests_disallowed |
      | room | users     | participant1 | participant1-displayname | guests_allowed |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Toggle password
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" sets password "123456" for room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | password_set |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" sets password "" for room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | password_removed |
      | room | users     | participant1 | participant1-displayname | password_set |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Creating a group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | invite   | attendees1 |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | group_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Creating a one2one room
    When user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Adding participant to room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Joining public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Joining room for file
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    When user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant2" joins room "file welcome.txt room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "file welcome.txt room" with 200
      | room                  | actorType | actorId      | actorDisplayName         | systemMessage |
      | file welcome.txt room | users     | participant2 | participant2-displayname | user_added |
      | file welcome.txt room | users     | participant1 | participant1-displayname | user_added |
      | file welcome.txt room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "file welcome.txt room" with 200
      | room                  | actorType | actorId      | actorDisplayName         | systemMessage |
      | file welcome.txt room | users     | participant2 | participant2-displayname | user_added |
      | file welcome.txt room | users     | participant1 | participant1-displayname | user_added |
      | file welcome.txt room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Joining room for link share
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant2" logs in
    And user "participant2" gets the room for last share with 200 (v1)
    When user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "file last share room" with 200
      | room                 | actorType | actorId      | actorDisplayName         | systemMessage |
      | file last share room | users     | participant1 | participant1-displayname | user_added |
      | file last share room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "file last share room" with 200
      | room                 | actorType | actorId      | actorDisplayName         | systemMessage |
      | file last share room | users     | participant1 | participant1-displayname | user_added |
      | file last share room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Joining listed room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" allows listing room "room" for "all" with 200 (v4)
    When user "participant2" joins room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant2 | participant2-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | listable_all         |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant2 | participant2-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | listable_all         |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Ending call for all
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "participant1" joins call "room" with 200 (v4)
    And user "participant2" joins call "room" with 200 (v4)
    And user "participant3" joins call "room" with 200 (v4)
    When user "participant1" ends call "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | call_ended_everyone  |
      | room | users     | participant3 | participant3-displayname | call_joined          |
      | room | users     | participant2 | participant2-displayname | call_joined          |
      | room | users     | participant1 | participant1-displayname | call_started         |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | call_ended_everyone  |
      | room | users     | participant3 | participant3-displayname | call_joined          |
      | room | users     | participant2 | participant2-displayname | call_joined          |
      | room | users     | participant1 | participant1-displayname | call_started         |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant3" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | call_ended_everyone  |
      | room | users     | participant3 | participant3-displayname | call_joined          |
      | room | users     | participant2 | participant2-displayname | call_joined          |
      | room | users     | participant1 | participant1-displayname | call_started         |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Participant escalation
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" promotes "participant2" in room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | moderator_promoted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" demotes "participant2" in room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | moderator_demoted |
      | room | users     | participant1 | participant1-displayname | moderator_promoted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    When user "participant1" removes "participant2" from room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room       | actorType | actorId      | actorDisplayName         | systemMessage |
      | room | users     | participant1 | participant1-displayname | user_removed |
      | room | users     | participant1 | participant1-displayname | moderator_demoted |
      | room | users     | participant1 | participant1-displayname | moderator_promoted |
      | room | users     | participant1 | participant1-displayname | user_added |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Changing listable scope of room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" allows listing room "room" for "all" with 200 (v4)
    And user "participant1" allows listing room "room" for "users" with 200 (v4)
    And user "participant1" allows listing room "room" for "none" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | listable_none        |
      | room | users     | participant1 | participant1-displayname | listable_users       |
      | room | users     | participant1 | participant1-displayname | listable_all         |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | listable_none        |
      | room | users     | participant1 | participant1-displayname | listable_users       |
      | room | users     | participant1 | participant1-displayname | listable_all         |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |

  Scenario: Locking a room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" locks room "room" with 200 (v4)
    And user "participant1" unlocks room "room" with 200 (v4)
    Then user "participant1" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | read_only_off        |
      | room | users     | participant1 | participant1-displayname | read_only            |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
    And user "participant2" sees the following system messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | systemMessage        |
      | room | users     | participant1 | participant1-displayname | read_only_off        |
      | room | users     | participant1 | participant1-displayname | read_only            |
      | room | users     | participant1 | participant1-displayname | user_added           |
      | room | users     | participant1 | participant1-displayname | conversation_created |
