Feature: conversation/delete-room
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Owner deletes
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 1               |
    And user "participant2" is not participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And reset signaling server requests
    When user "participant1" deletes room "room" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"delete","delete":{"userids":["participant1"]}} |
    Then user "participant1" is not participant of room "room" (v4)

  Scenario: Moderator deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" promotes "participant2" in room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 2               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" deletes room "room" with 200 (v4)
    Then user "participant1" is not participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)

  Scenario: User deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant2" is participant of the following rooms (v4)
      | id   | type | participantType |
      | room | 3    | 3               |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    When user "participant2" deletes room "room" with 403 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)

  Scenario: Stranger deletes
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)
    When user "participant2" deletes room "room" with 404 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is not participant of room "room" (v4)

  Scenario: Automatic retention
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | objectType | event |
      | objectId | 1740204000#1740207600 |
    And user "participant1" is participant of room "room" (v4)
    # Room is new
    When force run "OCA\Talk\BackgroundJob\ExpireObjectRooms" background jobs
    And user "participant1" is participant of room "room" (v4)
    # Room is old but has a new message
    And age room "room" 32 days
    And user "participant1" sends message "Message 1" to room "room" with 201
    When force run "OCA\Talk\BackgroundJob\ExpireObjectRooms" background jobs
    And user "participant1" is participant of room "room" (v4)
    # Room is old and last message is old
    And age room "room" 32 days
    When force run "OCA\Talk\BackgroundJob\ExpireObjectRooms" background jobs
    Then user "participant1" is not participant of room "room" (v4)
