Feature: federation/reminder
  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    Given user "participant1" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: Get mention suggestions (translating local users to federated users)
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And user "participant2" sends message "Message 2" to room "LOCAL::room" with 201
    When using server "LOCAL"
    And user "participant1" sets reminder for message "Message 2" in room "room" for time 2133349024 with 201 (v1)
    And using server "REMOTE"
    And user "participant2" sets reminder for message "Message 1" in room "LOCAL::room" for time 1234567 with 201 (v1)
    And using server "LOCAL"
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And using server "REMOTE"
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    And using server "LOCAL"
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And using server "REMOTE"
    And user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                                 |
      | spreed | reminder    | LOCAL::room/Message 1 | Reminder: participant1-displayname in conversation room |
    # Participant1 sets timestamp to past so it should trigger now
    When using server "LOCAL"
    And user "participant1" sets reminder for message "Message 2" in room "room" for time 1234567 with 201 (v1)
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                                                 |
      | spreed | reminder    | room/Message 2 | Reminder: participant2-displayname in conversation room |
    When using server "REMOTE"
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    And user "participant2" deletes reminder for message "Message 1" in room "LOCAL::room" with 200 (v1)
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Deleting reminder before the job is executed never triggers a notification
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    And user "participant2" joins room "LOCAL::room" with 200 (v4)
    And user "participant2" leaves room "LOCAL::room" with 200 (v4)
    When user "participant2" sets reminder for message "Message 1" in room "LOCAL::room" for time 1234567 with 201 (v1)
    And user "participant2" deletes reminder for message "Message 1" in room "LOCAL::room" with 200 (v1)
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
