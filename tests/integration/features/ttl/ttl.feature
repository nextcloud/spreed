Feature: room/ttp
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
  Scenario: Enable TTL and check after expire
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    And user "participant1" set the ttl to -1 of room "room" with 400 (v4)
    And user "participant2" set the ttl to 3 of room "room" with 403 (v4)
    And user "participant3" set the ttl to 3 of room "room" with 404 (v4)
    And user "participant1" set the ttl to 3 of room "room" with 200 (v4)
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" check if ttl of room "room" is 3 (v4)