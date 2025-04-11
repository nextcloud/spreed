Feature: chat-2/reminder

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Reminder in one-to-one chat (and sender is deleted)
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant2" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                                                        |
      | spreed | reminder    | room/Message 1 | Reminder: You in private conversation participant2-displayname |
    And user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                                    |
      | spreed | reminder    | room/Message 1 | Reminder: participant1-displayname in private conversation |
    When user "participant1" is deleted
    And user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                                                   |
      | spreed | reminder    | room/Message 1 | Reminder: A deleted user in private conversation participant1-displayname |
    And user "participant2" deletes reminder for message "Message 1" in room "room" with 200 (v1)
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Reminder in one-to-one chat recipient is deleted
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant2" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                                                        |
      | spreed | reminder    | room/Message 1 | Reminder: You in private conversation participant2-displayname |
    And user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                                    |
      | spreed | reminder    | room/Message 1 | Reminder: participant1-displayname in private conversation |
    When user "participant2" is deleted
    And user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                                                        |
      | spreed | reminder    | room/Message 1 | Reminder: You in private conversation participant2-displayname |
    And user "participant1" deletes reminder for message "Message 1" in room "room" with 200 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Reminder on user message
    Given user "participant1" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    # Participant1 is in 2037 so not triggering for now
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 2133349024 with 201 (v1)
    And user "participant2" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                            |
    And user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                                 |
      | spreed | reminder    | room/Message 1 | Reminder: participant1-displayname in conversation room |
    # Participant1 sets timestamp to past so it should trigger now
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                            |
      | spreed | reminder    | room/Message 1 | Reminder: You in conversation room |
    When user "participant1" is deleted
    And user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                       |
      | spreed | reminder    | room/Message 1 | Reminder: A deleted user in conversation room |
    And user "participant2" deletes reminder for message "Message 1" in room "room" with 200 (v1)
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Deleting reminder before the job is executed never triggers a notification
    Given user "participant1" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "participant1" sends message "Message 1" to room "room" with 201
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant1" deletes reminder for message "Message 1" in room "room" with 200 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Reminder on anonymous guest message
    Given user "participant1" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "guest" joins room "room" with 200 (v4)
    And user "guest" sends message "Message 1" to room "room" with 201
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant2" sets reminder for message "Message 1" in room "room" for time 1234567 with 404 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                            |
      | spreed | reminder    | room/Message 1 | Reminder: A guest in conversation room |
    And user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    And user "participant1" deletes reminder for message "Message 1" in room "room" with 200 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Reminder on named guest message
    Given user "participant1" creates room "room" (v4)
      | roomType | 3    |
      | roomName | room |
    And user "guest" joins room "room" with 200 (v4)
    And guest "guest" sets name to "FooBar" in room "room" with 200
    And user "guest" sends message "Message 1" to room "room" with 201
    When user "participant1" sets reminder for message "Message 1" in room "room" for time 1234567 with 201 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
    And force run "OCA\Talk\BackgroundJob\Reminder" background jobs
    Then user "participant1" has the following notifications
      | app    | object_type | object_id      | subject                                       |
      | spreed | reminder    | room/Message 1 | Reminder: FooBar (guest) in conversation room |
    And user "participant1" deletes reminder for message "Message 1" in room "room" with 200 (v1)
    And user "participant1" has the following notifications
      | app | object_type | object_id | subject |
