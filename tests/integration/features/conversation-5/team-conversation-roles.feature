Feature: conversation-5/team-conversation-roles
  # Team conversations: group conversations with the object type "team" and
  # the team id as object id. The direct users of the team are the
  # participants, their team level is their role (Owner/Admin: owner,
  # Moderator: moderator, Member: user). Changes in the Teams app are synced
  # by the ResyncTeamRoom background job, which is queued by the member events
  # of the Teams app.

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists
    And User "participant1" creates team "Team A"
    And add user "participant2" to team "Team A"
    And add user "participant3" to team "Team A"

  # Creating

  Scenario: A team member creates the team conversation, the team owner is added right away
    When user "participant2" creates team conversation "room" for team "Team A" with 201 (v4)
    Then user "participant2" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
    And user "participant2" gets room "room" with 200 (v4)
      | objectType      | team   |
      | name            | Team A |
      | type            | 2      |
      | participantType | 3      |

  Scenario: The other members of the team are added by the resync
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    # Any change of the members queues the resync of the team conversation
    When add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |
      | users     | participant4 | 3               |

  Scenario: Team admins are owners of the team conversation
    Given set level of user "participant2" in team "Team A" to "Admin"
    And set level of user "participant3" in team "Team A" to "Moderator"
    When user "participant3" creates team conversation "room" for team "Team A" with 201 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 1               |
      | users     | participant3 | 2               |

  Scenario: Users that are not in the team get the same answer as for a team that does not exist
    # Teams are looked up as super user, a different answer would reveal hidden teams
    When user "participant4" creates team conversation "room" for team "Team A" with 400 (v4)
      | error | object-id |
    And user "participant4" creates team conversation "room" for unknown team with 400 (v4)
      | error | object-id |
    # Also when the conversation of the team exists already
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And user "participant4" creates team conversation "room2" for team "Team A" with 400 (v4)
      | error | object-id |

  Scenario: A team has only one conversation, creating it again returns the existing one
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When user "participant1" creates team conversation "room2" for team "Team A" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | name   |
      | room | 2    | Team A |

  Scenario: Personal teams can not have a conversation
    Given User "participant1" creates personal team "Personal"
    When user "participant1" creates team conversation "room" for team "Personal" with 400 (v4)
      | error | object-id |

  Scenario: Only the direct users of the team are participants
    # Groups and nested teams are managed in the Teams app, but their
    # members are not participants of the team conversation
    Given user "participant5" exists
    And group "team-group" exists
    And user "participant4" is member of group "team-group"
    And add group "team-group" to team "Team A"
    And User "participant4" creates team "Team B"
    And add team "Team B" to team "Team A"
    # Neither are members of a team that Team A is a member of
    And User "participant5" creates team "Parent"
    And add team "Team A" to team "Parent"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When set level of user "participant3" in team "Team A" to "Moderator"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 2               |
    And user "participant4" creates team conversation "room2" for team "Team A" with 400 (v4)
      | error | object-id |
    And user "participant5" creates team conversation "room2" for team "Team A" with 400 (v4)
      | error | object-id |

  Scenario: Team conversations can not be made public
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When user "participant1" makes room "room" public with 400 (v4)
    Then user "participant1" gets room "room" with 200 (v4)
      | type | 2 |

  # Team → Talk

  Scenario: Level changes in the team are synced to the conversation
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When set level of user "participant2" in team "Team A" to "Admin"
    And set level of user "participant3" in team "Team A" to "Moderator"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 1               |
      | users     | participant3 | 2               |
    When set level of user "participant2" in team "Team A" to "Member"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 2               |

  Scenario: Transferring the team ownership in the Teams app keeps the former owner a Talk owner
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When set level of user "participant2" in team "Team A" to "Owner"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" has level "Admin" in team "Team A"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 1               |
      | users     | participant3 | 3               |

  Scenario: Roles of users are not changed in Talk, they are the team levels
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |
      | users     | participant4 | 3               |
    When user "participant1" promotes "participant2" in room "room" with 400 (v4)
    And user "participant1" promotes "participant3" to "OWNER" in room "room" with 400 (v4)
    Then user "participant2" has level "Member" in team "Team A"
    And user "participant3" has level "Member" in team "Team A"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |
      | users     | participant4 | 3               |

  Scenario: Participants can not be invited when creating the team conversation
    # The participants are the members of the team, added by the sync
    When user "participant1" creates room "room" with 400 (v4)
      | roomType               | 2                    |
      | roomName               | Team A               |
      | objectType             | team                 |
      | objectId               | TEAM_ID(Team A)      |
      | participants[users][0] | participant4         |
    Then user "participant4" is participant of the following rooms (v4)

  # Adding

  Scenario: Only members of the team are added to the team conversation in Talk
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    # Users that are no member of the team are added to the team in the Teams app
    When user "participant1" adds user "participant4" to room "room" with 400 (v4)
    # Members missing in the conversation (not synced yet) are added right away
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant3 | 3               |

  Scenario: Groups, teams and email addresses are not added to the team conversation in Talk
    Given group "group1" exists
    And user "participant4" is member of group "group1"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When user "participant1" adds group "group1" to room "room" with 400 (v4)
    And user "participant1" adds email "guest@example.tld" to room "room" with 400 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |

  # Removing

  Scenario: Removing a member in Talk removes them from the team
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    When user "participant1" removes "participant2" from room "room" with 200 (v4)
    Then user "participant2" is no direct member of team "Team A"
    # The resync does not add them again
    When add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant3 | 3               |
      | users     | participant4 | 3               |

  Scenario: The team decides who can remove a member
    # Team moderators can not remove team admins (Talk owners are protected anyway)
    Given set level of user "participant2" in team "Team A" to "Moderator"
    And set level of user "participant3" in team "Team A" to "Moderator"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    # Same level: Circles refuses
    When user "participant2" removes "participant3" from room "room" with 400 (v4)
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 2               |
      | users     | participant3 | 2               |

  Scenario: Leaving the team conversation leaves the team
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    When user "participant3" leaves room "room" with 200 (v4)
    Then user "participant3" is no direct member of team "Team A"
    # The resync does not add them again
    When add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant4 | 3               |


  Scenario: Members removed in the Teams app are removed from the conversation right away
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    # No background job: removed while the Teams app applies the change
    When remove user "participant3" from team "Team A"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant4 | 3               |
    And user "participant3" sends message "Still here?" to room "room" with 404
    # The resync afterwards changes nothing
    When run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant4 | 3               |

  Scenario: Banning a member removes them from the team, the resync does not add them again
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    When user "participant1" bans user "participant3" from room "room" with 200 (v1)
      | internalNote | Banned |
    Then user "participant3" is no direct member of team "Team A"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant4 | 3               |
    # Added to the team again in the Teams app: still banned from the conversation
    When add user "participant3" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant4 | 3               |

  # Deleted teams

  Scenario: The conversation of a deleted team is archived, read-only and without moderators
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    When team "Team A" is deleted
    # Still a team conversation, so administrators can convert or delete it
    Then user "participant1" gets room "room" with 200 (v4)
      | objectType      | team |
      | readOnly        | 1    |
      | isArchived      | 1    |
      | participantType | 3    |
    And user "participant1" sends message "Still there?" to room "room" with 403
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 3               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |
      | users     | participant4 | 3               |
