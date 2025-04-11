Feature: conversation-2/set-publishing-permissions
  Background:
    Given user "owner" exists
    Given user "moderator" exists
    Given user "invited user" exists
    Given user "not invited but joined user" exists

  Scenario: owner can not set permissions in one-to-one room
    Given user "owner" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | moderator |
    Given user "moderator" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | owner |
    And user "owner" loads attendees attendee ids in room "one-to-one room" (v4)
    When user "owner" sets permissions for "owner" in room "one-to-one room" to "S" with 400 (v4)
    And user "owner" sets permissions for "moderator" in room "one-to-one room" to "S" with 400 (v4)
    And user "moderator" sets permissions for "owner" in room "one-to-one room" to "S" with 400 (v4)
    And user "moderator" sets permissions for "moderator" in room "one-to-one room" to "S" with 400 (v4)
    Then user "owner" sees the following attendees in room "one-to-one room" with 200 (v4)
      | actorType  | actorId   | permissions |
      | users      | owner     | SJLAVPM     |
      | users      | moderator | SJLAVPM     |
    And user "moderator" sees the following attendees in room "one-to-one room" with 200 (v4)
      | actorType  | actorId   | permissions |
      | users      | owner     | SJLAVPM     |
      | users      | moderator | SJLAVPM     |

  Scenario: owner can set permissions in group room
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "group room" with 200 (v4)
    And user "owner" promotes "moderator" in room "group room" with 200 (v4)
    And user "owner" adds user "invited user" to room "group room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "group room" (v4)
    When user "owner" sets permissions for "owner" in room "group room" to "S" with 403 (v4)
    And user "owner" sets permissions for "moderator" in room "group room" to "S" with 403 (v4)
    And user "owner" sets permissions for "invited user" in room "group room" to "S" with 200 (v4)
    Then user "owner" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | CS          |
    And user "moderator" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | CS          |
    And user "invited user" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | CS          |

  Scenario: moderator can set permissions in group room
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "group room" with 200 (v4)
    And user "owner" promotes "moderator" in room "group room" with 200 (v4)
    And user "owner" adds user "invited user" to room "group room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "group room" (v4)
    When user "owner" sets permissions for "owner" in room "group room" to "S" with 403 (v4)
    And user "owner" sets permissions for "moderator" in room "group room" to "S" with 403 (v4)
    And user "owner" sets permissions for "invited user" in room "group room" to "S" with 200 (v4)
    Then user "owner" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | CS          |
    And user "moderator" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | CS          |
    And user "invited user" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | CS          |

  Scenario: others can not set permissions in group room
    Given user "not invited user" exists
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "group room" with 200 (v4)
    And user "owner" promotes "moderator" in room "group room" with 200 (v4)
    And user "owner" adds user "invited user" to room "group room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "group room" (v4)
    When user "invited user" sets permissions for "owner" in room "group room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "moderator" in room "group room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "invited user" in room "group room" to "S" with 403 (v4)
    And user "not invited user" sets permissions for "owner" in room "group room" to "S" with 404 (v4)
    And user "not invited user" sets permissions for "moderator" in room "group room" to "S" with 404 (v4)
    And user "not invited user" sets permissions for "invited user" in room "group room" to "S" with 404 (v4)
    # Guest user names in tests must begin with "guest"
    And user "guest not joined" sets permissions for "owner" in room "group room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "moderator" in room "group room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "invited user" in room "group room" to "S" with 404 (v4)
    Then user "owner" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | SJAVPM      |
    And user "moderator" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | SJAVPM      |
    And user "invited user" sees the following attendees in room "group room" with 200 (v4)
      | actorType  | actorId      | permissions |
      | users      | owner        | SJLAVPM     |
      | users      | moderator    | SJLAVPM     |
      | users      | invited user | SJAVPM      |

  Scenario: owner can set permissions in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "public room" (v4)
    When user "owner" sets permissions for "owner" in room "public room" to "S" with 403 (v4)
    And user "owner" sets permissions for "moderator" in room "public room" to "S" with 403 (v4)
    And user "owner" sets permissions for "invited user" in room "public room" to "S" with 200 (v4)
    And user "owner" sets permissions for "not invited but joined user" in room "public room" to "S" with 200 (v4)
    And user "owner" sets permissions for "guest moderator" in room "public room" to "S" with 403 (v4)
    And user "owner" sets permissions for "guest" in room "public room" to "S" with 200 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |

  Scenario: moderator can set permissions in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "public room" (v4)
    When user "moderator" sets permissions for "owner" in room "public room" to "S" with 403 (v4)
    And user "moderator" sets permissions for "moderator" in room "public room" to "S" with 403 (v4)
    And user "moderator" sets permissions for "invited user" in room "public room" to "S" with 200 (v4)
    And user "moderator" sets permissions for "not invited but joined user" in room "public room" to "S" with 200 (v4)
    And user "moderator" sets permissions for "guest moderator" in room "public room" to "S" with 403 (v4)
    And user "moderator" sets permissions for "guest" in room "public room" to "S" with 200 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    # Guests can not fetch the participant list

  Scenario: guest moderator can set permissions in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "public room" (v4)
    # Guest user names in tests must begin with "guest"
    When user "guest moderator" sets permissions for "owner" in room "public room" to "S" with 403 (v4)
    And user "guest moderator" sets permissions for "moderator" in room "public room" to "S" with 403 (v4)
    And user "guest moderator" sets permissions for "invited user" in room "public room" to "S" with 200 (v4)
    And user "guest moderator" sets permissions for "not invited but joined user" in room "public room" to "S" with 200 (v4)
    And user "guest moderator" sets permissions for "guest moderator" in room "public room" to "S" with 403 (v4)
    And user "guest moderator" sets permissions for "guest" in room "public room" to "S" with 200 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | CS          |
      | users      | not invited but joined user | CS          |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | CS          |
    # Guests can not fetch the participant list

  Scenario: others can not set permissions in public room
    Given user "not joined user" exists
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" loads attendees attendee ids in room "public room" (v4)
    When user "invited user" sets permissions for "owner" in room "public room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "moderator" in room "public room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "invited user" in room "public room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "not invited but joined user" in room "public room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "guest moderator" in room "public room" to "S" with 403 (v4)
    And user "invited user" sets permissions for "guest" in room "public room" to "S" with 403 (v4)
    And user "not invited but joined user" sets permissions for "owner" in room "public room" to "S" with 403 (v4)
    And user "not invited but joined user" sets permissions for "moderator" in room "public room" to "S" with 403 (v4)
    And user "not invited but joined user" sets permissions for "invited user" in room "public room" to "S" with 403 (v4)
    And user "not invited but joined user" sets permissions for "not invited but joined user" in room "public room" to "S" with 403 (v4)
    And user "not invited but joined user" sets permissions for "guest moderator" in room "public room" to "S" with 403 (v4)
    And user "not invited but joined user" sets permissions for "guest" in room "public room" to "S" with 403 (v4)
    And user "not joined user" sets permissions for "owner" in room "public room" to "S" with 404 (v4)
    And user "not joined user" sets permissions for "moderator" in room "public room" to "S" with 404 (v4)
    And user "not joined user" sets permissions for "invited user" in room "public room" to "S" with 404 (v4)
    And user "not joined user" sets permissions for "not invited but joined user" in room "public room" to "S" with 404 (v4)
    And user "not joined user" sets permissions for "guest moderator" in room "public room" to "S" with 404 (v4)
    And user "not joined user" sets permissions for "guest" in room "public room" to "S" with 404 (v4)
    # Guest user names in tests must begin with "guest"
    And user "guest" sets permissions for "owner" in room "public room" to "S" with 403 (v4)
    And user "guest" sets permissions for "moderator" in room "public room" to "S" with 403 (v4)
    And user "guest" sets permissions for "invited user" in room "public room" to "S" with 403 (v4)
    And user "guest" sets permissions for "not invited but joined user" in room "public room" to "S" with 403 (v4)
    And user "guest" sets permissions for "guest moderator" in room "public room" to "S" with 403 (v4)
    And user "guest" sets permissions for "guest" in room "public room" to "S" with 403 (v4)
    And user "guest not joined" sets permissions for "owner" in room "public room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "moderator" in room "public room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "invited user" in room "public room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "not invited but joined user" in room "public room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "guest moderator" in room "public room" to "S" with 404 (v4)
    And user "guest not joined" sets permissions for "guest" in room "public room" to "S" with 404 (v4)
    Then user "owner" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | SJAVPM      |
      | users      | not invited but joined user | SJAVPM      |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | SJAVPM      |
    And user "moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | SJAVPM      |
      | users      | not invited but joined user | SJAVPM      |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | SJAVPM      |
    And user "invited user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | SJAVPM      |
      | users      | not invited but joined user | SJAVPM      |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | SJAVPM      |
    And user "not invited but joined user" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | SJAVPM      |
      | users      | not invited but joined user | SJAVPM      |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | SJAVPM      |
    And user "guest moderator" sees the following attendees in room "public room" with 200 (v4)
      | actorType  | actorId                     | permissions |
      | users      | owner                       | SJLAVPM     |
      | users      | moderator                   | SJLAVPM     |
      | users      | invited user                | SJAVPM      |
      | users      | not invited but joined user | SJAVPM      |
      | guests     | "guest moderator"           | SJLAVPM     |
      | guests     | "guest"                     | SJAVPM      |
    # Guests can not fetch the participant list

  Scenario: participants can not set permissions in room for a share
    # These users are only needed in very specific tests, so they are not
    # created in the background step.
    Given user "owner of file" exists
    And user "user with access to file" exists
    And user "owner of file" shares "welcome.txt" with user "user with access to file" with OCS 100
    And user "user with access to file" accepts last share
    And user "owner of file" shares "welcome.txt" by link with OCS 100
    And user "guest" gets the room for last share with 200 (v1)
    And user "owner of file" joins room "file last share room" with 200 (v4)
    And user "user with access to file" joins room "file last share room" with 200 (v4)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "owner of file" loads attendees attendee ids in room "file last share room" (v4)
    When user "owner of file" sets permissions for "owner of file" in room "file last share room" to "S" with 403 (v4)
    And user "owner of file" sets permissions for "user with access to file" in room "file last share room" to "S" with 403 (v4)
    And user "owner of file" sets permissions for "guest" in room "file last share room" to "S" with 403 (v4)
    And user "user with access to file" sets permissions for "owner of file" in room "file last share room" to "S" with 403 (v4)
    And user "user with access to file" sets permissions for "user with access to file" in room "file last share room" to "S" with 403 (v4)
    And user "user with access to file" sets permissions for "guest" in room "file last share room" to "S" with 403 (v4)
    And user "guest" sets permissions for "owner of file" in room "file last share room" to "S" with 403 (v4)
    And user "guest" sets permissions for "user with access to file" in room "file last share room" to "S" with 403 (v4)
    And user "guest" sets permissions for "guest" in room "file last share room" to "S" with 403 (v4)
    Then user "owner of file" sees the following attendees in room "file last share room" with 200 (v4)
      | actorType  | actorId                  | permissions |
      | users      | owner of file            | SJAVPM      |
      | users      | user with access to file | SJAVPM      |
      | guests     | "guest"                  | SJAVPM      |
    And user "user with access to file" sees the following attendees in room "file last share room" with 200 (v4)
      | actorType  | actorId                  | permissions |
      | users      | owner of file            | SJAVPM      |
      | users      | user with access to file | SJAVPM      |
      | guests     | "guest"                  | SJAVPM      |

  # This does not make much sense, but there is no real need to block it either.
  Scenario: owner can set permissions in a password request room
    # The user is only needed in very specific tests, so it is not created in
    # the background step.
    Given user "owner of file" exists
    And user "owner of file" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    And user "owner of file" joins room "password request for last share room" with 200 (v4)
    And user "owner of file" loads attendees attendee ids in room "password request for last share room" (v4)
    When user "owner of file" sets permissions for "owner of file" in room "password request for last share room" to "S" with 403 (v4)
    And user "owner of file" sets permissions for "guest" in room "password request for last share room" to "S" with 200 (v4)
    Then user "owner of file" sees the following attendees in room "password request for last share room" with 200 (v4)
      | actorType  | actorId       | permissions |
      | users      | owner of file | SJLAVPM     |
      | guests     | "guest"       | CS          |
