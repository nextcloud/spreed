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
