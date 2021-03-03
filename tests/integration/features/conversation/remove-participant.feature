Feature: public
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

#
# Removing an owner
#
  Scenario: Owner removes self participant from empty public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room"
    When user "participant1" removes "participant1" from room "room" with 200
    Then user "participant1" is not participant of room "room"

  Scenario: Owner removes self participant from public room when there are other users in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant1" removes "participant1" from room "room" with 400
    Then user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"

  Scenario: Owner removes self participant from public room when there are other moderators in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" is participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant1" removes "participant1" from room "room" with 200
    Then user "participant1" is not participant of room "room"
    And user "participant2" is participant of room "room"

#
# Removing a moderator
#
  Scenario: Owner removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes self participant from empty public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" removes "participant1" from room "room" with 200
    And user "participant1" is not participant of room "room"
    And user "participant2" is participant of room "room"
    When user "participant2" removes "participant2" from room "room" with 200
    Then user "participant2" is not participant of room "room"

  Scenario: Moderator removes self participant from public room when there are other users in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" removes "participant1" from room "room" with 200
    And user "participant1" is not participant of room "room"
    And user "participant2" adds "participant3" to room "room" with 200
    And user "participant2" is participant of room "room"
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant2" from room "room" with 400
    Then user "participant2" is participant of room "room"
    And user "participant3" is participant of room "room"

  Scenario: Moderator removes self participant from public room when there are other moderators in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" removes "participant1" from room "room" with 200
    And user "participant1" is not participant of room "room"
    And user "participant2" adds "participant3" to room "room" with 200
    And user "participant2" promotes "participant3" in room "room" with 200
    And user "participant2" is participant of room "room"
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant2" from room "room" with 200
    Then user "participant2" is not participant of room "room"
    And user "participant3" is participant of room "room"

  Scenario: User removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: Stranger removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant1" promotes "participant3" in room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is participant of room "room"

#
# Removing a user
#
  Scenario: Owner removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" promotes "participant2" in room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 200
    Then user "participant3" is not participant of room "room"

  Scenario: User removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    Then user "participant3" is participant of room "room"

  Scenario: Stranger removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant3" to room "room" with 200
    And user "participant3" is participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is participant of room "room"

#
# Removing a stranger
#
  Scenario: Owner removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" is not participant of room "room"
    When user "participant1" removes "participant3" from room "room" with 404
    Then user "participant3" is not participant of room "room"

  Scenario: Moderator removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    When user "participant1" promotes "participant2" in room "room" with 200
    And user "participant3" is not participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    Then user "participant3" is not participant of room "room"

  Scenario: User removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds "participant2" to room "room" with 200
    And user "participant3" is not participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 403
    And user "participant3" is not participant of room "room"

  Scenario: Stranger removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" is not participant of room "room"
    When user "participant2" removes "participant3" from room "room" with 404
    And user "participant3" is not participant of room "room"
