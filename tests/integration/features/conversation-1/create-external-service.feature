Feature: conversation-1/create-external-service
  Background:
    Given user "participant1" exists

  Scenario: External call service creates a room on behalf of a user
    Given the following "spreed" app config is set
      | external_call_service               | https://external-service.example.org/ |
      | external_call_service_shared_secret | aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa |
    When external call service creates room "room" with secret "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" with 201 (v4)
      | roomType | 3 |
      | roomName | room |
      | owner | participant1 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | name | type | participantType |
      | room | room | 3    | 1               |

  Scenario: Unauthenticated user without external service header is rejected
    Given the following "spreed" app config is set
      | external_call_service               | https://external-service.example.org/ |
      | external_call_service_shared_secret | aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa |
    When external call service creates room "room" with secret "bbbbbbbbbb" with 401 (v4)
      | roomType | 3 |
      | roomName | room |
      | owner | participant1 |
    When external call service creates room "room" with secret "" with 401 (v4)
      | roomType | 3 |
      | roomName | room |
      | owner | participant1 |
    Then user "participant1" is participant of the following rooms (v4)
