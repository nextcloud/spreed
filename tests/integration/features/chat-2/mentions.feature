Feature: chat/mentions

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: get mentions in a one-to-one room
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" gets the following candidate mentions in room "one-to-one room" for "" with 200
      | id           | label                    | source | mentionId    |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "" with 404
    When user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |

  Scenario: get matched mentions in a one-to-one room
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" gets the following candidate mentions in room "one-to-one room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "part" with 404
    When user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |

  Scenario: get unmatched mentions in a one-to-one room
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" gets the following candidate mentions in room "one-to-one room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "unknown" with 404
    When user "participant2" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "unknown" with 200

  Scenario: get mentions in a one-to-one room with a participant not in the room
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant3" gets the following candidate mentions in room "one-to-one room" for "" with 404



  Scenario: get mentions in a group room with no other participant
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    | details           |
      | all          | room                     | calls  | all          | 1 participant |

  Scenario: get mentions in a group room
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant2" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant3" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |

  Scenario: get matched mentions in a group room
    Given team "1234" exists
    Given add user "participant1" to team "1234"
    Given add user "participant3" to team "1234"
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds team "1234" to room "group room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "group room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant2" gets the following candidate mentions in room "group room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant3" gets the following candidate mentions in room "group room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant1" gets the following candidate mentions in room "group room" for "1234" with 200
      | id           | label                    | source  | mentionId    |
      | 1234         | 1234                     | teams   | team/1234    |
    And user "participant2" gets the following candidate mentions in room "group room" for "1234" with 200
      | id           | label                    | source  | mentionId    |
      | 1234         | 1234                     | teams   | team/1234    |
    And user "participant3" gets the following candidate mentions in room "group room" for "1234" with 200
      | id           | label                    | source  | mentionId    |
      | 1234         | 1234                     | teams   | team/1234    |

  Scenario: get unmatched mentions in a group room
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "group room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "group room" for "unknown" with 200
    And user "participant3" gets the following candidate mentions in room "group room" for "unknown" with 200

  Scenario: get mentions in a group room with a participant not in the room
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    Then user "participant4" gets the following candidate mentions in room "group room" for "" with 404



  Scenario: get mentions in a public room with no other participant
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |

  Scenario: get mentions in a public room
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant3" joins room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "participant2" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant3 | participant3-displayname | users  | participant3 |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "participant3" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "guest" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |

  Scenario: get matched mentions in a public room
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant3" joins room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant2" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant3" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "guest" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |

  Scenario: get matched guest mentions in a public room
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant3" joins room "public room" with 200 (v4)
    And user "guest1" joins room "public room" with 200 (v4)
    And user "guest2" joins room "public room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "participant2" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "participant3" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "guest1" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "guest2" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |

  Scenario: get matched named guest mentions in a public room
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant3" joins room "public room" with 200 (v4)
    And user "guest1" joins room "public room" with 200 (v4)
    And guest "guest1" sets name to "FooBar" in room "public room" with 200
    And user "guest2" joins room "public room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | FooBar                   | guests | GUEST_ID     |
    And user "participant2" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | FooBar                   | guests | GUEST_ID     |
    And user "participant3" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | FooBar                   | guests | GUEST_ID     |
    And user "guest1" gets the following candidate mentions in room "public room" for "oob" with 200
    And user "guest2" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source | mentionId    |
      | GUEST_ID     | FooBar                   | guests | GUEST_ID     |

  Scenario: get unmatched mentions in a public room
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant3" joins room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "public room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "public room" for "unknown" with 200
    And user "participant3" gets the following candidate mentions in room "public room" for "unknown" with 200
    And user "guest" gets the following candidate mentions in room "public room" for "unknown" with 200

  Scenario: get mentions in a public room with a participant not in the room
    When user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant3" joins room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    Then user "participant4" gets the following candidate mentions in room "public room" for "" with 404
    And user "guest2" gets the following candidate mentions in room "public room" for "" with 404



  Scenario: get mentions in a file room with no other joined participant
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome.txt room" (v4)
    And user "participant2" is not participant of room "file welcome.txt room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file welcome.txt room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome.txt              | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "file welcome.txt room" for "" with 404

  Scenario: get mentions in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant2" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file welcome (2).txt room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome (2).txt          | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "file welcome (2).txt room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome (2).txt          | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |

  Scenario: get matched mentions in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant2" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file welcome (2).txt room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "file welcome (2).txt room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |

  Scenario: get unmatched mentions in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant2" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file welcome (2).txt room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "file welcome (2).txt room" for "unknown" with 200

  Scenario: get mentions in a file room with a participant without access to the file
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant2" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)
    Then user "participant3" gets the following candidate mentions in room "file welcome (2).txt room" for "" with 404

  Scenario: mention a participant with access to the file but not joined in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome.txt room" (v4)
    And user "participant2" is not participant of room "file welcome.txt room" (v4)
    When user "participant1" sends message "hi @participant2" to room "file welcome.txt room" with 201
    Then user "participant2" is participant of room "file welcome.txt room" (v4)



  Scenario: get mentions in a room for a file shared by link with no other joined participant
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file last share room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome.txt              | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "file last share room" for "" with 404
    And user "participant3" gets the following candidate mentions in room "file last share room" for "" with 404
    And user "guest" gets the following candidate mentions in room "file last share room" for "" with 404

  Scenario: get mentions in a room for a file shared by link
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with user "participant4" with OCS 100
    And user "participant4" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant3" logs in
    And user "participant3" gets the room for last share with 200 (v1)
    And user "participant3" joins room "file last share room" with 200 (v4)
    # Guests need to get the room (so the share token is stored in the session)
    # to be able to join it.
    And user "guest" gets the room for last share with 200 (v1)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    And user "participant3" is participant of room "file last share room" (v4)
    And user "guest" is participant of room "file last share room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file last share room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome.txt              | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant4 | participant4-displayname | users  | participant4 |
      | participant3 | participant3-displayname | users  | participant3 |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    And user "participant2" gets the following candidate mentions in room "file last share room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome.txt              | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant4 | participant4-displayname | users  | participant4 |
      | participant3 | participant3-displayname | users  | participant3 |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    # Self-joined users can not mention users with access to the file that have
    # not joined the room.
    And user "participant3" gets the following candidate mentions in room "file last share room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome.txt              | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
      | GUEST_ID     | Guest                    | guests | GUEST_ID     |
    # Guests can not mention users with access to the file that have not joined
    # the room.
    And user "guest" gets the following candidate mentions in room "file last share room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | welcome.txt              | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |

  Scenario: get matched mentions in a room for a file shared by link
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with user "participant4" with OCS 100
    And user "participant4" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant2" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant3" logs in
    And user "participant3" gets the room for last share with 200 (v1)
    And user "participant3" joins room "file last share room" with 200 (v4)
    # Guests need to get the room (so the share token is stored in the session)
    # to be able to join it.
    And user "guest" gets the room for last share with 200 (v1)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    And user "participant3" is participant of room "file last share room" (v4)
    And user "guest" is participant of room "file last share room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file last share room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant4 | participant4-displayname | users  | participant4 |
      | participant3 | participant3-displayname | users  | participant3 |
    And user "participant2" gets the following candidate mentions in room "file last share room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant4 | participant4-displayname | users  | participant4 |
      | participant3 | participant3-displayname | users  | participant3 |
    # Self-joined users can not mention users with access to the file that have
    # not joined the room.
    And user "participant3" gets the following candidate mentions in room "file last share room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
    # Guests can not mention users with access to the file that have not joined
    # the room.
    And user "guest" gets the following candidate mentions in room "file last share room" for "part" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
      | participant2 | participant2-displayname | users  | participant2 |
      | participant3 | participant3-displayname | users  | participant3 |

  Scenario: get unmatched mentions in a room for a file shared by link
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with user "participant4" with OCS 100
    And user "participant4" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant2" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant3" logs in
    And user "participant3" gets the room for last share with 200 (v1)
    And user "participant3" joins room "file last share room" with 200 (v4)
    # Guests need to get the room (so the share token is stored in the session)
    # to be able to join it.
    And user "guest" gets the room for last share with 200 (v1)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    And user "participant3" is participant of room "file last share room" (v4)
    And user "guest" is participant of room "file last share room" (v4)
    Then user "participant1" gets the following candidate mentions in room "file last share room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "file last share room" for "unknown" with 200
    And user "participant3" gets the following candidate mentions in room "file last share room" for "unknown" with 200
    And user "guest" gets the following candidate mentions in room "file last share room" for "unknown" with 200

  Scenario: get mentions in a room for a file shared by link with a participant without access to the file and not joined
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant2" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    Then user "participant3" gets the following candidate mentions in room "file last share room" for "" with 404
    And user "guest" gets the following candidate mentions in room "file last share room" for "" with 404

  Scenario: mention a participant with access to the file but not joined in a room for a file shared by link
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    When user "participant1" sends message "hi @participant2" to room "file last share room" with 201
    Then user "participant2" is participant of room "file last share room" (v4)

  Scenario: mention a participant with access to the file but not joined by self-joined user and guest in a room for a file shared by link
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant3" logs in
    And user "participant3" gets the room for last share with 200 (v1)
    And user "participant3" joins room "file last share room" with 200 (v4)
    # Guests need to get the room (so the share token is stored in the session)
    # to be able to join it.
    And user "guest" gets the room for last share with 200 (v1)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    When user "participant3" sends message "hi @participant2" to room "file last share room" with 201
    And user "guest" sends message "hello @participant2" to room "file last share room" with 201
    Then user "participant2" is not participant of room "file last share room" (v4)

  Scenario: mention a participant without access to the file but joined in a room for a file shared by link
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant3" logs in
    And user "participant3" gets the room for last share with 200 (v1)
    And user "participant3" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    And user "participant3" is participant of room "file last share room" (v4)
    When user "participant1" sends message "hi @participant3" to room "file last share room" with 201
    And user "participant3" leaves room "file last share room" with 200 (v4)
    Then user "participant3" is not participant of room "file last share room" (v4)

  Scenario: check direct mention marker after room mention
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" sends message "Room mention @all no direct mention" to room "group room" with 201
    And user "participant2" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 0                   |
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 0                   |

  Scenario: check direct mention marker after user mention
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" sends message "Direction mention for @participant3 only" to room "group room" with 201
    And user "participant2" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 0             | 0                   |
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 1                   |

  Scenario: check direct mention marker after mixed mention
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" sends message "Direction mention for @participant3 and @all for participant2" to room "group room" with 201
    And user "participant2" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 0                   |
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 1                   |
    When user "participant3" reads message "Direction mention for @participant3 and @all for participant2" in room "group room" with 200
    And user "participant2" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 0                   |
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 0             | 0                   |

  Scenario: check direct mention marker after reading partly
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" sends message "Test @participant3 #1" to room "group room" with 201
    And user "participant1" sends message "Test @participant3 #2" to room "group room" with 201
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 1                   |
    When user "participant3" reads message "Test @participant3 #1" in room "group room" with 200
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 1                   |
    When user "participant3" reads message "Test @participant3 #2" in room "group room" with 200
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 0             | 0                   |

  Scenario: check direct mention marker after reading partly with mixed mention
    When user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "group room" with 200 (v4)
    And user "participant1" sends message "Test @participant3 #1" to room "group room" with 201
    And user "participant1" sends message "Test @all" to room "group room" with 201
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 1                   |
    When user "participant3" reads message "Test @participant3 #1" in room "group room" with 200
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 1             | 0                   |
    When user "participant3" reads message "Test @all" in room "group room" with 200
    And user "participant3" is participant of the following rooms (v4)
      | id         | unreadMention | unreadMentionDirect |
      | group room | 0             | 0                   |

  Scenario: At-all in note-to-self broke the mention parsing
    And user "participant1" creates note-to-self (v4)
    And user "participant1" sends message "Test @all" to room "participant1-note-to-self" with 201
    And user "participant1" is participant of the following note-to-self rooms (v4)
      | id                        | type | name          |
      | participant1-note-to-self | 6    | Note to self  |
    Then user "participant1" sees the following messages in room "participant1-note-to-self" with 200
      | room                      | actorType | actorId      | actorDisplayName         | message                | messageParameters |
      | participant1-note-to-self | users     | participant1 | participant1-displayname | Test {mention-call1}   | "IGNORE"          |

  Scenario: get mentions with different mention permissions available
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant1 | participant1-displayname | users  | participant1 |
    When user "participant1" sets mention permissions for room "group room" to moderators with 200 (v4)
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | all          | room                     | calls  | all          |
      | participant2 | participant2-displayname | users  | participant2 |
    And user "participant2" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source | mentionId    |
      | participant1 | participant1-displayname | users  | participant1 |
