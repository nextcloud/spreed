Feature: chat/mentions

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: get mentions in a one-to-one room
    When user "participant1" creates room "one-to-one room"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" gets the following candidate mentions in room "one-to-one room" for "" with 200
      | id           | label                    | source |
      | participant2 | participant2-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |

  Scenario: get matched mentions in a one-to-one room
    When user "participant1" creates room "one-to-one room"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" gets the following candidate mentions in room "one-to-one room" for "part" with 200
      | id           | label                    | source |
      | participant2 | participant2-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |

  Scenario: get unmatched mentions in a one-to-one room
    When user "participant1" creates room "one-to-one room"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" gets the following candidate mentions in room "one-to-one room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "one-to-one room" for "unknown" with 200

  Scenario: get mentions in a one-to-one room with a participant not in the room
    When user "participant1" creates room "one-to-one room"
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant3" gets the following candidate mentions in room "one-to-one room" for "" with 404



  Scenario: get mentions in a group room with no other participant
    When user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |

  Scenario: get mentions in a group room
    When user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    Then user "participant1" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant1 | participant1-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant3" gets the following candidate mentions in room "group room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant1 | participant1-displayname | users  |
      | participant2 | participant2-displayname | users  |

  Scenario: get matched mentions in a group room
    When user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    Then user "participant1" gets the following candidate mentions in room "group room" for "part" with 200
      | id           | label                    | source |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "group room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant3" gets the following candidate mentions in room "group room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |
      | participant2 | participant2-displayname | users  |

  Scenario: get unmatched mentions in a group room
    When user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    Then user "participant1" gets the following candidate mentions in room "group room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "group room" for "unknown" with 200
    And user "participant3" gets the following candidate mentions in room "group room" for "unknown" with 200

  Scenario: get mentions in a group room with a participant not in the room
    When user "participant1" creates room "group room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "group room" with 200
    And user "participant1" adds "participant3" to room "group room" with 200
    Then user "participant4" gets the following candidate mentions in room "group room" for "" with 404



  Scenario: get mentions in a public room with no other participant
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |

  Scenario: get mentions in a public room
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant3" joins room "public room" with 200
    And user "guest" joins room "public room" with 200
    Then user "participant1" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |
      | GUEST_ID     | Guest                    | guests |
    And user "participant2" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant1 | participant1-displayname | users  |
      | participant3 | participant3-displayname | users  |
      | GUEST_ID     | Guest                    | guests |
    And user "participant3" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant1 | participant1-displayname | users  |
      | participant2 | participant2-displayname | users  |
      | GUEST_ID     | Guest                    | guests |
    And user "guest" gets the following candidate mentions in room "public room" for "" with 200
      | id           | label                    | source |
      | all          | room                     | calls  |
      | participant1 | participant1-displayname | users  |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |

  Scenario: get matched mentions in a public room
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant3" joins room "public room" with 200
    And user "guest" joins room "public room" with 200
    Then user "participant1" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |
      | participant3 | participant3-displayname | users  |
    And user "participant3" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |
      | participant2 | participant2-displayname | users  |
    And user "guest" gets the following candidate mentions in room "public room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |
      | participant2 | participant2-displayname | users  |
      | participant3 | participant3-displayname | users  |

  Scenario: get matched guest mentions in a public room
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant3" joins room "public room" with 200
    And user "guest1" joins room "public room" with 200
    And user "guest2" joins room "public room" with 200
    Then user "participant1" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source |
      | GUEST_ID     | Guest                    | guests |
      | GUEST_ID     | Guest                    | guests |
    And user "participant2" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source |
      | GUEST_ID     | Guest                    | guests |
      | GUEST_ID     | Guest                    | guests |
    And user "participant3" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source |
      | GUEST_ID     | Guest                    | guests |
      | GUEST_ID     | Guest                    | guests |
    And user "guest1" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source |
      | GUEST_ID     | Guest                    | guests |
    And user "guest2" gets the following candidate mentions in room "public room" for "uest" with 200
      | id           | label                    | source |
      | GUEST_ID     | Guest                    | guests |

  Scenario: get matched named guest mentions in a public room
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant3" joins room "public room" with 200
    And user "guest1" joins room "public room" with 200
    And guest "guest1" sets name to "FooBar" in room "public room" with 200
    And user "guest2" joins room "public room" with 200
    Then user "participant1" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source |
      | GUEST_ID     | FooBar                   | guests |
    And user "participant2" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source |
      | GUEST_ID     | FooBar                   | guests |
    And user "participant3" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source |
      | GUEST_ID     | FooBar                   | guests |
    And user "guest1" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source |
    And user "guest2" gets the following candidate mentions in room "public room" for "oob" with 200
      | id           | label                    | source |
      | GUEST_ID     | FooBar                   | guests |

  Scenario: get unmatched mentions in a public room
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant3" joins room "public room" with 200
    And user "guest" joins room "public room" with 200
    Then user "participant1" gets the following candidate mentions in room "public room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "public room" for "unknown" with 200
    And user "participant3" gets the following candidate mentions in room "public room" for "unknown" with 200
    And user "guest" gets the following candidate mentions in room "public room" for "unknown" with 200

  Scenario: get mentions in a public room with a participant not in the room
    When user "participant1" creates room "public room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "public room" with 200
    And user "participant3" joins room "public room" with 200
    And user "guest" joins room "public room" with 200
    Then user "participant4" gets the following candidate mentions in room "public room" for "" with 404
    And user "guest2" gets the following candidate mentions in room "public room" for "" with 404



  Scenario: get mentions in a file room with no other joined participant
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant1" is participant of room "file welcome.txt room"
    And user "participant2" is not participant of room "file welcome.txt room"
    Then user "participant1" gets the following candidate mentions in room "file welcome.txt room" for "" with 200
      | id           | label                    | source |
      | all          | welcome.txt              | calls  |
      | participant2 | participant2-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "file welcome.txt room" for "" with 404

  Scenario: get mentions in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    And user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"
    Then user "participant1" gets the following candidate mentions in room "file welcome (2).txt room" for "" with 200
      | id           | label                    | source |
      | all          | welcome.txt              | calls  |
      | participant2 | participant2-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "file welcome (2).txt room" for "" with 200
      | id           | label                    | source |
      | all          | welcome.txt              | calls  |
      | participant1 | participant1-displayname | users  |

  Scenario: get matched mentions in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    And user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"
    Then user "participant1" gets the following candidate mentions in room "file welcome (2).txt room" for "part" with 200
      | id           | label                    | source |
      | participant2 | participant2-displayname | users  |
    And user "participant2" gets the following candidate mentions in room "file welcome (2).txt room" for "part" with 200
      | id           | label                    | source |
      | participant1 | participant1-displayname | users  |

  Scenario: get unmatched mentions in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    And user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"
    Then user "participant1" gets the following candidate mentions in room "file welcome (2).txt room" for "unknown" with 200
    And user "participant2" gets the following candidate mentions in room "file welcome (2).txt room" for "unknown" with 200

  Scenario: get mentions in a file room with a participant without access to the file
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    And user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"
    Then user "participant3" gets the following candidate mentions in room "file welcome (2).txt room" for "" with 404

  Scenario: mention a participant with access to the file but not joined in a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant1" is participant of room "file welcome.txt room"
    And user "participant2" is not participant of room "file welcome.txt room"
    When user "participant1" sends message "hi @participant2" to room "file welcome.txt room" with 201
    Then user "participant2" is participant of room "file welcome.txt room"
