Feature: conversation-4/hidden-participants

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: The participants list of a presentation is only available to moderators
    Given user "participant1" creates room "presentation" (v4)
      | roomType | 2            |
      | roomName | presentation |
      | preset   | presentation |
    And user "participant1" adds user "participant2" to room "presentation" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id           | type | participantType | attributes |
      | presentation | 2    | 1               | 32         |
    And user "participant1" sees the following attendees in room "presentation" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |
    But user "participant2" sees the following attendees in room "presentation" with 403 (v4)

  Scenario: Webinars hide the participants list
    Given user "participant1" creates room "webinar" (v4)
      | roomType | 3       |
      | roomName | webinar |
      | preset   | webinar |
    Then user "participant1" is participant of the following rooms (v4)
      | id      | type | participantType | attributes |
      | webinar | 3    | 1               | 32         |

  Scenario: Default conversations do not hide the participants list
    Given user "participant1" creates room "room" (v4)
      | roomType | 2    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant2" sees the following attendees in room "room" with 200 (v4)
      | actorType | actorId      | participantType |
      | users     | participant1 | 1               |
      | users     | participant2 | 3               |

  Scenario: Participant and call join messages are only shown to moderators
    Given user "participant1" creates room "presentation" (v4)
      | roomType | 2            |
      | roomName | presentation |
      | preset   | presentation |
    And user "participant1" adds user "participant2" to room "presentation" with 200 (v4)
    And user "participant1" promotes "participant2" in room "presentation" with 200 (v4)
    And user "participant1" demotes "participant2" in room "presentation" with 200 (v4)
    And user "participant1" joins room "presentation" with 200 (v4)
    And user "participant1" joins call "presentation" with 200 (v4)
    And user "participant2" joins room "presentation" with 200 (v4)
    And user "participant2" joins call "presentation" with 200 (v4)
    And user "participant2" leaves call "presentation" with 200 (v4)
    And user "participant1" ends call "presentation" with 200 (v4)
    Then user "participant1" sees the following system messages in room "presentation" with 200
      | room         | actorType | actorId      | systemMessage        |
      | presentation | users     | participant1 | call_ended_everyone  |
      | presentation | users     | participant2 | call_left            |
      | presentation | users     | participant2 | call_joined          |
      | presentation | users     | participant1 | call_started         |
      | presentation | users     | participant1 | moderator_demoted    |
      | presentation | users     | participant1 | moderator_promoted   |
      | presentation | users     | participant1 | user_added           |
      | presentation | users     | participant1 | conversation_created |
    And user "participant2" sees the following system messages in room "presentation" with 200
      | room         | actorType | actorId      | systemMessage               | messageParameters |
      | presentation | users     | participant1 | call_ended_everyone         | "IGNORE"          |
      | presentation | guests    | system       | hidden_participant_activity | []                |
      | presentation | guests    | system       | hidden_participant_activity | []                |
      | presentation | users     | participant1 | call_started                | "IGNORE"          |
      | presentation | guests    | system       | hidden_participant_activity | []                |
      | presentation | guests    | system       | hidden_participant_activity | []                |
      | presentation | guests    | system       | hidden_participant_activity | []                |
      | presentation | users     | participant1 | conversation_created        | "IGNORE"          |
