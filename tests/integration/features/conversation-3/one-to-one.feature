Feature: conversation-2/one-to-one
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User has no rooms
    Then user "participant1" is participant of the following rooms (v4)
    Then user "participant2" is participant of the following rooms (v4)
    Then user "participant3" is participant of the following rooms (v4)

  Scenario: User1 invites themself to a one2one room
    When user "participant1" tries to create room with 403 (v4)
      | roomType | 1 |
      | invite   | participant1 |

  Scenario: User1 invites user2 to a one2one room and user3 is not part of it
    When user "participant1" creates room "room1" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of the following rooms (v4)
      | id    | type | participantType |
      | room1 | 1    | 1               |
    And user "participant2" is participant of the following rooms (v4)
    And user "participant3" is participant of the following rooms (v4)
    And user "participant1" is participant of room "room1" (v4)
    And user "participant2" is not participant of room "room1" (v4)
    And user "participant3" is not participant of room "room1" (v4)
    Given user "participant2" creates room "room1" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" is participant of room "room1" (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id    | type | participantType |
      | room1 | 1    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id    | type | participantType |
      | room1 | 1    | 1               |

  Scenario: User1 invites user2 to a one2one room and leaves it
    Given user "participant1" creates room "room2" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room2" (v4)
    And user "participant2" is not participant of room "room2" (v4)
    When user "participant1" removes themselves from room "room2" with 200 (v4)
    Then user "participant1" is not participant of room "room2" (v4)
    And user "participant1" is participant of the following rooms (v4)
    And user "participant2" is not participant of room "room2" (v4)
    And user "participant2" is participant of the following rooms (v4)

  Scenario: User1 invites user2 to a one2one room and tries to delete it
    Given user "participant1" creates room "room3" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant1" is participant of room "room3" (v4)
    And user "participant2" is not participant of room "room3" (v4)
    When user "participant1" deletes room "room3" with 400 (v4)
    Then user "participant1" is participant of room "room3" (v4)
    And user "participant2" is not participant of room "room3" (v4)

  Scenario: User1 invites user2 to a one2one room and tries to remove user2
    Given user "participant1" creates room "room4" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room4" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    Then user "participant1" is participant of room "room4" (v4)
    And user "participant2" is participant of room "room4" (v4)
    And user "participant1" loads attendees attendee ids in room "room4" (v4)
    When user "participant1" removes "participant2" from room "room4" with 400 (v4)
    Then user "participant1" is participant of room "room4" (v4)
    And user "participant2" is participant of room "room4" (v4)

  Scenario: User1 invites user2 to a one2one room and tries to rename it
    Given user "participant1" creates room "room5" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room5" (v4)
    When user "participant1" renames room "room5" to "new name" with 400 (v4)

  Scenario: User1 invites user2 to a one2one room and tries to make it public
    Given user "participant1" creates room "room6" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room6" (v4)
    When user "participant1" makes room "room6" public with 400 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id    | type | participantType |
      | room6 | 1    | 1               |

  Scenario: User1 invites user2 to a one2one room and tries to invite user3
    Given user "participant1" creates room "room7" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room7" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" is participant of room "room7" (v4)
    And user "participant2" is participant of room "room7" (v4)
    And user "participant3" is not participant of room "room7" (v4)
    When user "participant1" adds user "participant3" to room "room7" with 400 (v4)
    And user "participant3" is not participant of room "room7" (v4)
    And user "participant1" sees the following attendees in room "room7" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 1               |

  Scenario: User1 invites user2 to a one2one room and promote user2 to moderator
    Given user "participant1" creates room "room8" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room8" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" is participant of room "room8" (v4)
    And user "participant2" is participant of room "room8" (v4)
    And user "participant1" loads attendees attendee ids in room "room8" (v4)
    When user "participant1" promotes "participant2" in room "room8" with 400 (v4)

  Scenario: User1 invites user2 to a one2one room and demote user2 to moderator
    Given user "participant1" creates room "room9" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room9" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" is participant of room "room9" (v4)
    And user "participant2" is participant of room "room9" (v4)
    And user "participant1" loads attendees attendee ids in room "room9" (v4)
    When user "participant1" demotes "participant2" in room "room9" with 400 (v4)

  Scenario: User1 invites user2 to a one2one room and promote non-invited user
    Given user "participant1" creates room "room10" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room10" (v4)
    And user "participant3" is not participant of room "room10" (v4)
    And user "participant1" loads attendees attendee ids in room "room10" (v4)
    When user "participant1" promotes "stranger" in room "room10" with 404 (v4)

  Scenario: User1 invites user2 to a one2one room and demote non-invited user
    Given user "participant1" creates room "room11" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room11" (v4)
    And user "participant3" is not participant of room "room11" (v4)
    And user "participant1" loads attendees attendee ids in room "room11" (v4)
    When user "participant1" demotes "stranger" in room "room11" with 404 (v4)

  Scenario: User1 invites user2 to a one2one room twice, it's the same room
    Given user "participant1" creates room "room12" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room12" (v4)
    And user "participant2" is not participant of room "room12" (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room12 | 1    | 1               |
    And user "participant2" is participant of the following rooms (v4)
    When user "participant1" creates room "room13" with 200 (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room12" (v4)
    And user "participant2" is not participant of room "room12" (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room12 | 1    | 1               |
    And user "participant2" is participant of the following rooms (v4)

  Scenario: User1 invites user2 to a one2one room, leaves and does it again, it's the same room
    Given user "participant1" creates room "room14" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room14" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" is participant of room "room14" (v4)
    And user "participant2" is participant of room "room14" (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room14 | 1    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room14 | 1    | 1               |
    When user "participant1" removes themselves from room "room14" with 200 (v4)
    Then user "participant1" is not participant of room "room14" (v4)
    And user "participant1" is participant of the following rooms (v4)
    And user "participant2" is participant of room "room14" (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room14 | 1    | 1               |
    And user "participant2" sees the following attendees in room "room14" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant2 | 1               |
    When user "participant1" creates room "room15" with 200 (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" is participant of room "room14" (v4)
    And user "participant2" is participant of room "room14" (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room14 | 1    | 1               |
    And user "participant2" is participant of the following rooms (v4)
      | id     | type | participantType |
      | room14 | 1    | 1               |
    And user "participant2" sees the following attendees in room "room14" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
      | users      | participant2 | 1               |

  Scenario: Check share restrictions on one to one conversation
    Given the following "core" app config is set
      | shareapi_restrict_user_enumeration_full_match | no |
      | shareapi_allow_share_dialog_user_enumeration | yes |
      | shareapi_restrict_user_enumeration_to_group | yes |
      | shareapi_restrict_user_enumeration_to_phone | yes |
    And user "participant1" creates room "room15" with 403 (v4)
      | roomType | 1 |
      | invite   | participant2 |

  Scenario: Remove self from one-to-one conversations when deletable config is set deletes it
    Given user "participant1" creates room "room" with 201 (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    Then user "participant1" removes themselves from room "room" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 1    | 1               |
    When user "participant1" creates room "room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And the following "spreed" app config is set
      | delete_one_to_one_conversations | 1 |
    Then user "participant1" removes themselves from room "room" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
    And user "participant2" is participant of the following rooms (v4)

  Scenario: Deleting one-to-one conversations is possible when deletable config is set
    Given user "participant1" creates room "room" with 201 (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant1" sends message "Message" to room "room" with 201
    Then user "participant1" deletes room "room" with 400 (v4)
    When the following "spreed" app config is set
      | delete_one_to_one_conversations | 1 |
    Then user "participant1" deletes room "room" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
    And user "participant2" is participant of the following rooms (v4)
