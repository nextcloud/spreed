Feature: conversation-1/create-external-service
  Background:
    Given user "participant1" exists

  Scenario: External call service creates a room on behalf of a user
    Given the following "spreed" app config is set
      | external_call_service_shared_secret | aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa |
      | external_call_service_auth_user | nextcloud |
      | external_call_service_iframe_field | targetUrl |
    Given external call server is started
    When external call service creates room "room" with secret "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" with 201 (v4)
      | roomType | 3 |
      | roomName | room |
      | owner | participant1 |
      | objectType | external_call |
      | objectId | d4e5f6a7-b8c9-0123-defa-123456789012 |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | name | type | participantType | objectType    | objectId                             |
      | room | room | 3    | 1               | external_call | d4e5f6a7-b8c9-0123-defa-123456789012 |
    And user "participant1" gets external call url for room "room" with 200 (v4)
      | url | https://example.tld/webapp3/m/210987654321-afed-3210-9c8b-7a6f5e4d |

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
