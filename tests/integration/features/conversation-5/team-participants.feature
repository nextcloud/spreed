Feature: conversation-5/team-participants
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    And User "participant1" creates team "Team A"
    And add user "participant2" to team "Team A"

  Scenario: Owner invites a team
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId      | participantType |
      | users      | participant1 | 1               |
    And user "participant1" adds team "Team A" to room "room" with 200 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType | displayName              |
      | users      | participant1    | 1               | participant1-displayname |
      | circles    | TEAM_ID(Team A) | 3               | Team A                   |
      | users      | participant2    | 3               | participant2-displayname |
    And team "Team A" is renamed to "Team Alpha"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType | displayName              |
      | users      | participant1    | 1               | participant1-displayname |
      | circles    | TEAM_ID(Team A) | 3               | Team Alpha               |
      | users      | participant2    | 3               | participant2-displayname |

  Scenario: A team conversation has the team and its members as participants
    Given user "participant3" exists
    # Participants of the request are ignored: the team is the participant
    When user "participant1" creates room "room" with 201 (v4)
      | roomType               | 2               |
      | roomName               | Team A          |
      | objectType             | team            |
      | objectId               | TEAM_ID(Team A) |
      | participants[users][0] | participant3    |
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 3               |
    When add user "participant3" to team "Team A"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 3               |
      | users      | participant3    | 3               |

  Scenario: Team levels are mirrored in the team conversation
    Given user "participant3" exists
    And add user "participant3" to team "Team A"
    And user "participant4" exists
    When user "participant1" creates room "room" with 201 (v4)
      | roomType   | 2               |
      | roomName   | Team A          |
      | objectType | team            |
      | objectId   | TEAM_ID(Team A) |
    And user "participant1" creates room "other" (v4)
      | roomType | 2     |
      | roomName | other |
    And user "participant1" adds team "Team A" to room "other" with 200 (v4)
    When user "participant2" is promoted to moderator in team "Team A"
    And user "participant3" is promoted to admin in team "Team A"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 2               |
      | users      | participant3    | 1               |
    And user "participant1" sees the following attendees in room "other" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 3               |
      | users      | participant3    | 3               |
    When add user "participant4" to team "Team A"
    And user "participant4" is promoted to owner in team "Team A"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 2               |
      | users      | participant3    | 1               |
      | users      | participant4    | 1               |
    When user "participant3" is demoted to moderator in team "Team A"
    And user "participant2" is demoted to member in team "Team A"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 3               |
      | users      | participant3    | 2               |
      | users      | participant4    | 1               |

  Scenario: A team conversation is only for the members of the team
    Given user "participant3" exists
    # Not a member of the team (Circles only links members): refused like an unknown team
    When user "participant3" creates room "other" with 404 (v4)
      | roomType   | 2               |
      | roomName   | Team A          |
      | objectType | team            |
      | objectId   | TEAM_ID(Team A) |
    And user "participant1" creates room "public" with 400 (v4)
      | roomType   | 3               |
      | roomName   | Team A          |
      | objectType | team            |
      | objectId   | TEAM_ID(Team A) |
    And user "participant1" creates room "room" with 201 (v4)
      | roomType   | 2               |
      | roomName   | Team A          |
      | objectType | team            |
      | objectId   | TEAM_ID(Team A) |
    Then user "participant1" makes room "room" public with 400 (v4)
    And user "participant1" allows listing room "room" for "users" with 400 (v4)
    And user "participant1" adds user "participant3" to room "room" with 400 (v4)
    And user "participant1" promotes "participant2" in room "room" with 400 (v4)
    And user "participant2" leaves room "room" with 400 (v4)
    And user "participant1" removes "participant2" from room "room" with 400 (v4)
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType  | actorId         | participantType |
      | users      | participant1    | 1               |
      | circles    | TEAM_ID(Team A) | 3               |
      | users      | participant2    | 3               |
