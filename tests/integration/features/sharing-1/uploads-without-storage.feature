Feature: sharing-1/uploads-without-storage

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Guest uploads into the storage of the moderator who allowed it
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    When user "participant1" allows uploads without storage in room "public room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id          | type | uploadsWithoutStorage |
      | public room | 3    | yes                   |
    When user "guest" joins room "public room" with 200 (v4)
    And user "guest" uploads "guest.txt" without storage to room "public room" with 200 (v1)
    Then user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId | actorDisplayName | message | messageParameters |
      | public room | guests    | guest   |                  | {file}  | "IGNORE"          |
    And user "participant1" gets file "Guests/guest.txt" in conversation folder of room "public room" with 200

  Scenario: Deleting the message moves the file out of the shared folder
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    And user "participant1" allows uploads without storage in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "guest" uploads "guest.txt" without storage to room "public room" with 200 (v1)
    And user "participant1" sees the following messages in room "public room" with 200
      | room        | actorType | actorId | actorDisplayName | message | messageParameters |
      | public room | guests    | guest   |                  | {file}  | "IGNORE"          |
    When user "participant1" deletes message "{file}" from room "public room" with 200
    Then user "participant1" gets file "Guests/guest.txt" in conversation folder of room "public room" with 404
    And user "participant1" gets file "Deleted/guest.txt" in conversation folder of room "public room" with 200

  Scenario: Guests cannot upload while it is not allowed
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    And user "guest" joins room "public room" with 200 (v4)
    Then user "guest" uploads "guest.txt" without storage to room "public room" with 403 (v1)
    When user "participant1" allows uploads without storage in room "public room" with 200 (v4)
    And user "participant1" stops uploads without storage in room "public room" with 200 (v4)
    Then user "guest" uploads "guest.txt" without storage to room "public room" with 403 (v1)

  Scenario: Stopping uploads revokes open upload links
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    And user "participant1" allows uploads without storage in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "guest" gets an upload link in room "public room" with 200 (v1)
    Then the last upload link accepts a file with 201
    When user "participant1" stops uploads without storage in room "public room" with 200 (v4)
    Then the last upload link accepts a file with 401

  Scenario: Clients without upload link support still get 507
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2          |
      | roomName | Group room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And invoking occ with "user:setting participant2 files quota 0"
    And the command was successful
    And user "participant1" allows uploads without storage in room "group room" with 200 (v4)
    Then user "participant2" probes room "group room" without upload link support with 507 (v1)

  Scenario: User without quota uploads into the storage of the moderator
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2          |
      | roomName | Group room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    And invoking occ with "user:setting participant2 files quota 0"
    And the command was successful
    Then user "participant2" uploads "user.txt" without storage to room "group room" with 507 (v1)
    When user "participant1" allows uploads without storage in room "group room" with 200 (v4)
    And user "participant2" uploads "user.txt" without storage to room "group room" with 200 (v1)
    Then user "participant1" gets file "Guests/user.txt" in conversation folder of room "group room" with 200

  Scenario: Only moderators with storage can allow uploads
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2          |
      | roomName | Group room |
    And user "participant1" adds user "participant2" to room "group room" with 200 (v4)
    Then user "participant2" allows uploads without storage in room "group room" with 403 (v4)
    When user "participant1" promotes "participant2" in room "group room" with 200 (v4)
    And invoking occ with "user:setting participant2 files quota 0"
    And the command was successful
    Then user "participant2" allows uploads without storage in room "group room" with 400 (v4)

  Scenario: One-to-one conversations cannot allow uploads without storage
    Given user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1            |
      | invite   | participant2 |
    Then user "participant1" allows uploads without storage in room "one-to-one room" with 400 (v4)

  Scenario: Uploads stop when the moderator who allowed them is demoted or leaves
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    And user "participant1" adds user "participant2" to room "public room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "public room" with 200 (v4)
    And user "participant2" allows uploads without storage in room "public room" with 200 (v4)
    When user "participant1" demotes "participant2" in room "public room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id          | type | uploadsWithoutStorage |
      | public room | 3    | no                    |
    When user "participant1" promotes "participant2" in room "public room" with 200 (v4)
    And user "participant2" allows uploads without storage in room "public room" with 200 (v4)
    And user "participant2" removes themselves from room "public room" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id          | type | uploadsWithoutStorage |
      | public room | 3    | no                    |
    And user "guest" joins room "public room" with 200 (v4)
    And user "guest" uploads "guest.txt" without storage to room "public room" with 403 (v1)

  Scenario: An upload link is deleted after posting and only posts its own files
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    And user "participant1" allows uploads without storage in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "guest" gets an upload link in room "public room" with 200 (v1)
    And the last upload link accepts a file with 201
    When user "guest2" joins room "public room" with 200 (v4)
    Then user "guest2" posts "late.txt" uploaded by someone else to room "public room" with 404 (v1)
    When user "guest" uploads "guest.txt" without storage to room "public room" with 200 (v1)
    Then the last upload link accepts a file with 401

  Scenario: Removing a guest revokes their upload links
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3           |
      | roomName | Public room |
    And user "participant1" allows uploads without storage in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "guest" gets an upload link in room "public room" with 200 (v1)
    When user "participant1" removes "guest" from room "public room" with 200 (v4)
    Then the last upload link accepts a file with 401
