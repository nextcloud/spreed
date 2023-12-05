Feature: conversation/limit-creation
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    Given group "group1" exists

  Scenario: User can not create group conversations
    Given the following "spreed" app config is set
      | start_conversations | ["group1"] |
    Then user "participant1" creates room "room" with 403 (v4)
      | roomType | 2 |
      | roomName | room |
    Given user "participant1" is member of group "group1"
    Then user "participant1" creates room "room" with 201 (v4)
      | roomType | 2 |
      | roomName | room |

  Scenario: User can not create public conversations
    Given the following "spreed" app config is set
      | start_conversations | ["group1"] |
    Then user "participant1" creates room "room" with 403 (v4)
      | roomType | 3 |
      | roomName | room |
    Given user "participant1" is member of group "group1"
    Then user "participant1" creates room "room" with 201 (v4)
      | roomType | 3 |
      | roomName | room |

  Scenario: User can still do one-to-one conversations
    Given the following "spreed" app config is set
      | start_conversations | ["group1"] |
    Then user "participant1" creates room "room" with 201 (v4)
      | roomType | 1 |
      | invite   | participant2 |
