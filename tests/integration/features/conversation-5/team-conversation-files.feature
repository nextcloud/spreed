Feature: conversation-5/team-conversation-files
  # Files of team conversations are stored in the folder of the team, when an
  # app provides team folders. None is installed for these tests: teams have
  # no folder, and team conversations keep the files in the files of the user,
  # the same as every other conversation.

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And User "participant1" creates team "Team A"
    And add user "participant2" to team "Team A"

  Scenario: Members of a team without folder get no folder
    Then user "participant1" gets the folder of team "Team A" with 404 (v1)
      | error | team |
    And user "participant2" gets the folder of team "Team A" with 404 (v1)
      | error | team |

  Scenario: Unknown teams and teams of others look the same as teams without folder
    Then user "participant3" gets the folder of team "Team A" with 404 (v1)
      | error | team |
    And user "participant1" gets the folder of unknown team with 404 (v1)
      | error | team |

  Scenario: Only logged in users can ask for the folder of a team
    Then user "guest" gets the folder of team "Team A" with 401 (v1)

  Scenario: Files posted in a team conversation without team folder stay in the files of the user
    Given user "participant1" creates team conversation "room" for team "Team A" with 201 (v4)
    And force run "OCA\Talk\BackgroundJob\ResyncTeamRooms" background jobs
    And repeating run "OCA\Talk\BackgroundJob\ResyncTeamRoomsNow" background jobs
    When user "participant1" uploads file "test.txt" with content "Hello!" to conversation folder for room "room" with name "Team A"
    And user "participant1" posts file "test.txt" from conversation folder of room "room" with name "Team A" with 200 (v1)
    Then user "participant2" sees the following messages in room "room" with 200
      | room | actorType | actorId      | actorDisplayName         | message | messageParameters |
      | room | users     | participant1 | participant1-displayname | {file}  | "IGNORE"          |
    And user "participant2" gets all received shares
    And share is returned with
      | uid_owner   | participant1 |
      | item_type   | folder       |
      | permissions | 1            |
      | path        | REGEXP /^\/Talk\/.+\/participant1-dis-participant1$/ |
