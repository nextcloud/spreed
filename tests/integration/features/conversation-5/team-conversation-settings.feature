Feature: conversation-5/team-conversation-settings
  # Name, description and who can join are the same in the team and its
  # conversation, changes on either side are applied to the other one.

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists
    And User "participant1" creates team "Team A"
    And add user "participant2" to team "Team A"
    And add user "participant3" to team "Team A"

  # Team → conversation

  Scenario: Name and description of the team are applied to the conversation
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When set description of team "Team A" to "Team description"
    Then user "participant1" gets room "room" with 200 (v4)
      | name        | Team A           |
      | description | Team description |
    When team "Team A" is renamed to "Team Alpha"
    And set description of team "Team A" to "New description"
    Then user "participant1" gets room "room" with 200 (v4)
      | name        | Team Alpha      |
      | description | New description |

  # Skipped until Circles fires EditingCircleEvent for config changes (CircleConfig),
  # without it the conversation only follows the team with the next resync
  @skip
  Scenario: The conversation is open to users when everyone can join the team, also with confirmation
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And user "participant1" gets room "room" with 200 (v4)
      | listable | 0 |
    When set config "VISIBLE" of team "Team A"
    Then user "participant1" gets room "room" with 200 (v4)
      | listable | 0 |
    When set config "OPEN" of team "Team A"
    Then user "participant1" gets room "room" with 200 (v4)
      | listable | 1 |
    # Joining sends a request to join then
    When set config "JOINREQUEST" of team "Team A"
    Then user "participant1" gets room "room" with 200 (v4)
      | listable | 1 |
    When set config "_JOINREQUEST" of team "Team A"
    Then user "participant1" gets room "room" with 200 (v4)
      | listable | 1 |
    When set config "_OPEN" of team "Team A"
    Then user "participant1" gets room "room" with 200 (v4)
      | listable | 0 |

  # Conversation → team

  Scenario: Renaming the conversation and changing its description changes the team
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When user "participant1" renames room "room" to "Team Beta" with 200 (v4)
    And user "participant1" sets description for room "room" to "From Talk" with 200 (v4)
    Then team "Team A" has the following details
      | displayName | Team Beta |
      | description | From Talk |
    And user "participant1" gets room "room" with 200 (v4)
      | name        | Team Beta |
      | description | From Talk |

  Scenario: Only team admins change the settings of the team in Talk
    Given set level of user "participant2" in team "Team A" to "Moderator"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    # Talk moderator = team moderator: no team admin
    When user "participant2" renames room "room" to "Team Beta" with 403 (v4)
    And user "participant2" sets description for room "room" to "From Talk" with 403 (v4)
    And user "participant2" allows listing room "room" for "users" with 403 (v4)
    And user "participant2" deletes room "room" with 403 (v4)
    Then team "Team A" has the following details
      | displayName | Team A |
    And user "participant1" gets room "room" with 200 (v4)
      | name     | Team A |
      | listable | 0      |

  Scenario: Opening the conversation opens the team, closing it closes the team
    # "Request to join" is a team setting Talk does not change
    Given set config "VISIBLE JOINREQUEST" of team "Team A"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And user "participant1" gets room "room" with 200 (v4)
      | listable | 0 |
    When user "participant1" allows listing room "room" for "users" with 200 (v4)
    Then team "Team A" has the following details
      | VISIBLE     | true |
      | OPEN        | true |
      | JOINREQUEST | true |
    When user "participant1" allows listing room "room" for "none" with 200 (v4)
    Then team "Team A" has the following details
      | VISIBLE     | true  |
      | OPEN        | false |
      | JOINREQUEST | true  |
    # Guests can not be members of a team
    And user "participant1" allows listing room "room" for "all" with 400 (v4)

  # Joining

  Scenario: Joining the open conversation joins the team
    Given set config "VISIBLE OPEN" of team "Team A"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When user "participant4" joins room "room" with 200 (v4)
    Then user "participant4" has level "Member" in team "Team A"
    # Not removed again by the resync
    When set level of user "participant3" in team "Team A" to "Moderator"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 2               |
      | users     | participant4 | 3               |

  Scenario: Invited users only become participants after accepting the invitation in the Teams app
    Given set config "INVITE" of team "Team A"
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And add user "participant4" to team "Team A"
    And set level of user "participant3" in team "Team A" to "Moderator"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    And user "participant4" is no direct member of team "Team A"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 2               |
    When user "participant4" joins team "Team A" in the Teams app
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant4" has level "Member" in team "Team A"
    And user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 2               |
      | users     | participant4 | 3               |

  # Email members of the team are the email guests of the conversation

  Scenario: Email members added in the Teams app are invited in the background
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    When add email "guest@example.tld" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId                   | participantType |
      | users     | participant1              | 1               |
      | users     | participant2              | 3               |
      | users     | participant3              | 3               |
      | emails    | SHA256(guest@example.tld) | 4               |
    # Removed right away, the resync afterwards changes nothing
    When remove email "guest@example.tld" from team "Team A"
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |
    When run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |

  Scenario: Removing an email guest in Talk removes them from the team
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And add email "guest@example.tld" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    When user "participant1" removes email "guest@example.tld" from room "room" with 200 (v4)
    # Removed from the team as well: the resync does not invite them again
    And add user "participant4" to team "Team A"
    And run "OCA\Talk\BackgroundJob\ResyncTeamRoom" background jobs
    Then user "participant1" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
      | users     | participant3 | 3               |
      | users     | participant4 | 3               |

  # Guests: the Teams app has none (guest accounts are users and can be members)

  Scenario: Team conversations have no anonymous phone guests
    Given group "sip-group" exists
    And user "participant1" is member of group "sip-group"
    And the following "spreed" app config is set
      | sip_bridge_dialin_info   | +49-1234-567890  |
      | sip_bridge_shared_secret | 1234567890abcdef |
      | sip_bridge_groups        | ["sip-group"]    |
    And user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    Then user "participant1" sets SIP state for room "room" to "no pin" with 400 (v4)
    And user "participant1" sets SIP state for room "room" to "enabled" with 200 (v4)
