Feature: chat/notifications

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Normal call notification
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |
    Given user "participant2" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Missed call notification
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" joins call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |
    Given user "participant1" leaves call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | You missed a group call in room |

  Scenario: Silent call does not trigger notifications
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" leaves call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Silent call with dedicated ping does trigger notifications
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant1" joins room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant1" loads attendees attendee ids in room "room" (v4)
    Given user "participant1" joins call "room" with 200 (v4)
      | silent | true |
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
    Given user "participant1" pings user "participant2" to join call "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                          |
      | spreed | call        | room      | A group call has started in room |
