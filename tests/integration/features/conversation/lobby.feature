Feature: conversation/lobby

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given user "participant4" exists

  Scenario: set lobby state in group room
    Given user "participant1" creates room "room"
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    When user "participant1" sets lobby state for room "room" to "moderators only" with 200
    And user "participant1" sets lobby state for room "room" to "all participants" with 200
    And user "participant2" sets lobby state for room "room" to "moderators only" with 200
    And user "participant2" sets lobby state for room "room" to "all participants" with 200
    And user "participant3" sets lobby state for room "room" to "moderators only" with 403
    And user "participant3" sets lobby state for room "room" to "all participants" with 403

  Scenario: set lobby state in public room
    Given user "participant1" creates room "room"
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant4" joins room "room" with 200
    And user "guest" joins room "room" with 200
    And user "participant1" promotes "guest" in room "room" with 200
    And user "guest2" joins room "room" with 200
    When user "participant1" sets lobby state for room "room" to "moderators only" with 200
    And user "participant1" sets lobby state for room "room" to "all participants" with 200
    And user "participant2" sets lobby state for room "room" to "moderators only" with 200
    And user "participant2" sets lobby state for room "room" to "all participants" with 200
    And user "participant3" sets lobby state for room "room" to "moderators only" with 403
    And user "participant3" sets lobby state for room "room" to "all participants" with 403
    And user "participant4" sets lobby state for room "room" to "moderators only" with 403
    And user "participant4" sets lobby state for room "room" to "all participants" with 403
    And user "guest" sets lobby state for room "room" to "moderators only" with 401
    And user "guest" sets lobby state for room "room" to "all participants" with 401
    And user "guest2" sets lobby state for room "room" to "moderators only" with 401
    And user "guest2" sets lobby state for room "room" to "all participants" with 401

  Scenario: set lobby state in one-to-one room
    Given user "participant1" creates room "room"
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" sets lobby state for room "room" to "moderators only" with 400
    And user "participant1" sets lobby state for room "room" to "all participants" with 400
    And user "participant2" sets lobby state for room "room" to "moderators only" with 400
    And user "participant2" sets lobby state for room "room" to "all participants" with 400

  Scenario: set lobby state in file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    When user "participant1" sets lobby state for room "file welcome.txt room" to "moderators only" with 403
    And user "participant1" sets lobby state for room "file welcome.txt room" to "all participants" with 403
    And user "participant2" sets lobby state for room "file welcome (2).txt room" to "moderators only" with 403
    And user "participant2" sets lobby state for room "file welcome (2).txt room" to "all participants" with 403

  Scenario: set lobby state of a room not joined to
    Given user "participant1" creates room "room"
      | roomType | 3 |
      | roomName | room |
    When user "participant2" sets lobby state for room "room" to "moderators only" with 404
    And user "participant2" sets lobby state for room "room" to "all participants" with 404
