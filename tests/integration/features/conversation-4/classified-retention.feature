Feature: conversation-4/classified-retention
  Background:
    Given user "participant1" exists

  Scenario: A classified conversation is auto-deleted after a call happened
    Given user "participant1" creates room "classified" (v4)
      | roomType | 2 |
      | roomName | classified |
      | preset   | classified |
    And user "participant1" is participant of room "classified" (v4)
    # Without a call the conversation is not queued for deletion and survives the sweep
    And age room "classified" 2 hours
    When force run "OCA\Talk\BackgroundJob\ExpireObjectRooms" background jobs
    Then user "participant1" is participant of room "classified" (v4)
    # A call happens and ends, which queues the conversation for deletion
    And user "participant1" joins room "classified" with 200 (v4)
    And user "participant1" joins call "classified" with 200 (v4)
    And user "participant1" leaves call "classified" with 200 (v4)
    And user "participant1" leaves room "classified" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id         | objectType |
      | classified | classified |
    And age room "classified" 2 hours
    When force run "OCA\Talk\BackgroundJob\ExpireObjectRooms" background jobs
    Then user "participant1" is not participant of room "classified" (v4)

  Scenario: A moderator keeps a classified conversation permanently
    Given user "participant1" creates room "classified" (v4)
      | roomType | 2 |
      | roomName | classified |
      | preset   | classified |
    And user "participant1" joins room "classified" with 200 (v4)
    And user "participant1" joins call "classified" with 200 (v4)
    And user "participant1" leaves call "classified" with 200 (v4)
    And user "participant1" leaves room "classified" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id         | objectType |
      | classified | classified |
    # Moderator keeps it: the object type becomes "classified_persist"
    And user "participant1" unbinds room "classified" from its object with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id         | objectType         |
      | classified | classified_persist |
    And age room "classified" 2 hours
    When force run "OCA\Talk\BackgroundJob\ExpireObjectRooms" background jobs
    Then user "participant1" is participant of room "classified" (v4)
    # A later call must not re-queue it for deletion
    And user "participant1" joins room "classified" with 200 (v4)
    And user "participant1" joins call "classified" with 200 (v4)
    And user "participant1" leaves call "classified" with 200 (v4)
    And user "participant1" leaves room "classified" with 200 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id         | objectType         |
      | classified | classified_persist |
