Feature: conversation-2/remove-participant
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
    And user "participant1" is participant of room "room" (v4)
    When user "participant1" removes "participant1" from room "room" with 200 (v4)
    Then user "participant1" is not participant of room "room" (v4)

  Scenario: Owner removes self participant from public room when there are other users in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant1" removes "participant1" from room "room" with 400 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)

  Scenario: Owner removes self participant from public room when there are other moderators in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant1" removes "participant1" from room "room" with 200 (v4)
    Then user "participant1" is not participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)

#
# Removing a moderator
#
  Scenario: Owner removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" promotes "participant3" in room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant1" removes "participant3" from room "room" with 200 (v4)
    Then user "participant3" is not participant of room "room" (v4)

  Scenario: Moderator removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" promotes "participant3" in room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant2" removes "participant3" from room "room" with 200 (v4)
    Then user "participant3" is not participant of room "room" (v4)

  Scenario: Moderator removes self participant from empty public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" removes "participant1" from room "room" with 200 (v4)
    And user "participant1" is not participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" removes "participant2" from room "room" with 200 (v4)
    Then user "participant2" is not participant of room "room" (v4)

  Scenario: Moderator removes self participant from public room when there are other users in the room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" removes "participant1" from room "room" with 200 (v4)
    And user "participant1" is not participant of room "room" (v4)
    And user "participant2" adds user "participant3" to room "room" with 200 (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant2" removes "participant2" from room "room" with 400 (v4)
    Then user "participant2" is participant of room "room" (v4)
    And user "participant3" is participant of room "room" (v4)

  Scenario: Moderator removes self participant from public room when there are other moderators in the room
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And reset signaling server requests
    And user "participant1" removes "participant1" from room "room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | room  | {"type":"disinvite","disinvite":{"userids":["participant1"],"alluserids":["participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"participant-list":"refresh"}}} |
    And user "participant1" is not participant of room "room" (v4)
    And user "participant2" adds user "participant3" to room "room" with 200 (v4)
    And user "participant2" promotes "participant3" in room "room" with 200 (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant2" removes "participant2" from room "room" with 200 (v4)
    Then user "participant2" is not participant of room "room" (v4)
    And user "participant3" is participant of room "room" (v4)

  Scenario: User removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" promotes "participant3" in room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant2" removes "participant3" from room "room" with 403 (v4)
    Then user "participant3" is participant of room "room" (v4)

  Scenario: Stranger removes moderator
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant1" promotes "participant3" in room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant2" removes "participant3" from room "room" with 404 (v4)
    Then user "participant3" is participant of room "room" (v4)

#
# Removing a user
#
  Scenario: Owner removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant1" removes "participant3" from room "room" with 200 (v4)
    Then user "participant3" is not participant of room "room" (v4)

  Scenario: Moderator removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    When user "participant2" removes "participant3" from room "room" with 200 (v4)
    Then user "participant3" is not participant of room "room" (v4)

  Scenario: User removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" removes "participant3" from room "room" with 403 (v4)
    Then user "participant3" is participant of room "room" (v4)

  Scenario: Stranger removes user
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    And user "participant3" is participant of room "room" (v4)
    And user "participant1" loads attendees attendee ids in room "room" (v4)
    When user "participant2" removes "participant3" from room "room" with 404 (v4)
    Then user "participant3" is participant of room "room" (v4)

#
# Removing a stranger
#
  Scenario: Owner removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" is not participant of room "room" (v4)
    When user "participant1" removes "stranger" from room "room" with 404 (v4)
    Then user "participant3" is not participant of room "room" (v4)

  Scenario: Moderator removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant3" is not participant of room "room" (v4)
    When user "participant2" removes "stranger" from room "room" with 404 (v4)
    Then user "participant3" is not participant of room "room" (v4)

  Scenario: User removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant3" is not participant of room "room" (v4)
    When user "participant2" removes "stranger" from room "room" with 403 (v4)
    And user "participant3" is not participant of room "room" (v4)

  Scenario: Stranger removes stranger
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant3" is not participant of room "room" (v4)
    When user "participant2" removes "stranger" from room "room" with 404 (v4)
    And user "participant3" is not participant of room "room" (v4)
