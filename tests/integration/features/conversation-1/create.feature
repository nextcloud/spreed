Feature: conversation-1/create
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Set password during creation
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | room1 |
      | password | P4$$w0rd |
    Given user "participant1" creates room "room2" (v4)
      | roomType | 2 |
      | roomName | room2 |
      | password | P4$$w0rd |
    Then user "participant1" is participant of the following unordered rooms (v4)
      | id    | name  | type | participantType | hasPassword |
      | room1 | room1 | 3    | 1               | 1           |
      | room2 | room2 | 2    | 1               |             |

  Scenario: Read only during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | readOnly | 1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | readOnly |
      | room | 3    | 1               | 1        |

  Scenario: Listable during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | listable | 1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | listable |
      | room | 3    | 1               | 1        |

  Scenario: Set message expiration during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | messageExpiration | 3600 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | messageExpiration |
      | room | 3    | 1               | 3600              |

  Scenario: Set lobby during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | lobbyState | 1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | lobbyState | lobbyTimer |
      | room | 3    | 1               | 1          | 0          |

    Scenario: Set lobby with timer during creation
      Given user "participant1" creates room "room" (v4)
        | roomType | 3 |
        | roomName | room |
        | lobbyState | 1 |
        | lobbyTimer | OFFSET(3600) |
      Then user "participant1" is participant of the following rooms (v4)
        | id   | type | participantType | lobbyState | lobbyTimer        |
        | room | 3    | 1               | 1          | GREATER_THAN_ZERO |

  Scenario: Enable SIP during creation
    Given the following "spreed" app config is set
      | sip_bridge_dialin_info | +49-1234-567890 |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups | ["group1"] |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | sipEnabled | 1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | sipEnabled |
      | room | 3    | 1               | 1          |

  Scenario: Set permissions during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | permissions | AV |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | defaultPermissions |
      | room | 3    | 1               | CAV                |

  Scenario: Set recording consent during creation
    Given recording server is started
    Given signaling server is started
    And the following "spreed" app config is set
      | recording_consent | 2 |
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | recordingConsent | 1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | recordingConsent |
      | room | 3    | 1               | 1 |

  Scenario: Set mention permissions during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | mentionPermissions | 1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | mentionPermissions |
      | room | 3    | 1               | 1 |

  Scenario: Set description during creation
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | description | Lorem ipsum |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | description |
      | room | 3    | 1               | Lorem ipsum |

  Scenario: Create extended conversation
    Given user "participant1" creates room "room1" (v4)
      | roomType | 3 |
      | roomName | Your Group |
    Given user "participant2" creates room "room2" (v4)
      | roomType | 3 |
      | roomName | Not Your Public |
    Given user "participant2" creates room "room3" (v4)
      | roomType | 2 |
      | roomName | Not Your Group |
    Given user "participant2" creates room "room4" (v4)
      | roomType | 2 |
      | roomName | Not Your Open |
      | listable | 1 |
    Given user "participant1" creates room "room5" (v4)
      | roomType | 1 |
      | invite | participant2 |
    Given user "participant2" creates room "room6" (v4)
      | roomType | 1 |
      | invite | participant3 |
    Then user "participant1" is participant of the following unordered rooms (v4)
      | id      | name                 | type | participantType |
      | room1   | Your Group           | 3    | 1               |
      | room5   | participant2         | 1    | 1               |
    Given user "participant1" creates room "room1-1" with 400 (v4)
      | roomType | 3 |
      | roomName | Own group conversation |
      | objectType | extended_conversation |
      | objectId | ROOM(room1) |
    Given user "participant1" creates room "room2-1" with 400 (v4)
      | roomType | 3 |
      | roomName | Not own Public |
      | objectType | extended_conversation |
      | objectId | ROOM(room2) |
    Given user "participant1" creates room "room3-1" with 400 (v4)
      | roomType | 3 |
      | roomName | Not okay Group |
      | objectType | extended_conversation |
      | objectId | ROOM(room3) |
    Given user "participant1" creates room "room4-1" with 400 (v4)
      | roomType | 3 |
      | roomName | Not okay Open |
      | objectType | extended_conversation |
      | objectId | ROOM(room4) |
    Given user "participant1" creates room "room5-1" (v4)
      | roomType | 3 |
      | roomName | Okay: Own one-to-one |
      | objectType | extended_conversation |
      | objectId | ROOM(room5) |
    Given user "participant1" creates room "room6-1" with 400 (v4)
      | roomType | 3 |
      | roomName | Not okay one-to-one |
      | objectType | extended_conversation |
      | objectId | ROOM(room6) |
    Given user "participant1" creates room "room7-1" with 400 (v4)
      | roomType | 3 |
      | roomName | Not okay Invalid |
      | objectType | extended_conversation |
      | objectId | in/valid |
    Then user "participant1" is participant of the following unordered rooms (v4)
      | id      | name                 | type | participantType |
      | room1   | Your Group           | 3    | 1               |
      | room5   | participant2         | 1    | 1               |
      | room5-1 | Okay: Own one-to-one | 3    | 1               |
