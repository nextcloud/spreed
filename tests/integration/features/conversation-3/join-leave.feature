Feature: conversation/join-leave

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: join a one-to-one room
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    When user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 404 (v4)
    And user "guest" joins room "room" with 404 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And user "guest" is not participant of room "room" (v4)

  Scenario: leave a one-to-one room
    Given user "participant1" creates room "room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" creates room "room" with 200 (v4)
      | roomType | 1 |
      | invite   | participant1 |
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    When user "participant1" leaves room "room" with 200 (v4)
    And user "participant2" leaves room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)



  Scenario: join a group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 404 (v4)
    And user "guest" joins room "room" with 404 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And user "guest" is not participant of room "room" (v4)

  Scenario: leave a group room
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    When user "participant1" leaves room "room" with 200 (v4)
    And user "participant2" leaves room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)



  Scenario: join a public room
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant1" joins room "room" with 200 (v4)
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is participant of room "room" (v4)
    And user "guest" is participant of room "room" (v4)

  Scenario: leave a public room
    Given signaling server is started
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And reset signaling server requests
    And user "participant1" joins room "room" with 200 (v4) session name "old"
    And user "participant1" joins room "room" with 200 (v4) session name "new"
    # Rejoining the same room disinvites the previous session
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"disinvite","disinvite":{"sessionids":["SESSION(participant1#old)"],"alluserids":["participant1","participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"participant-list":"refresh"}}} |
    And user "participant2" joins room "room" with 200 (v4)
    And user "participant3" joins room "room" with 200 (v4)
    And user "guest" joins room "room" with 200 (v4)
    And reset signaling server requests
    When user "participant1" leaves room "room" with 200 (v4)
    # No signaling message when a normal user leaves
    Then signaling server received the following requests
    And user "participant2" leaves room "room" with 200 (v4)
    And reset signaling server requests
    And user "participant3" leaves room "room" with 200 (v4)
    # Signaling message when a self-joined user leaves
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"disinvite","disinvite":{"userids":["participant3"],"alluserids":["participant1","participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"participant-list":"refresh"}}} |
    And reset signaling server requests
    And user "guest" leaves room "room" with 200 (v4)
    # Signaling message when a guest leaves
    Then signaling server received the following requests
      | token | data |
      | room  | {"type":"disinvite","disinvite":{"sessionids":["SESSION(guest)"],"alluserids":["participant1","participant2"],"properties":{"name":"Private conversation","type":3,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"participant-list":"refresh"}}} |
    Then user "participant1" is participant of room "room" (v4)
    And user "participant2" is participant of room "room" (v4)
    And user "participant3" is not participant of room "room" (v4)
    And user "guest" is not participant of room "room" (v4)
