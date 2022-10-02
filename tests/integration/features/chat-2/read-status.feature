Feature: chat-2/read-status
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User toggles the read privacy
    Given user "participant1" creates room "chatting" (v4)
      | roomType | 3 |
      | roomName | room |

    # Private
    When user "participant1" sets setting "read_status_privacy" to "1" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "1"
    When user "participant1" sends message "Message 1" to room "chatting" with 201
    Then last response has no last common read message header

    # Public
    When user "participant1" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "0"
    When user "participant1" reads message "Message 1" in room "chatting" with 200
    Then last response has last common read message header set to "Message 1"

    # Private again
    When user "participant1" sets setting "read_status_privacy" to "1" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "1"
    When user "participant1" reads message "Message 1" in room "chatting" with 200
    Then last response has no last common read message header


  Scenario: Read status is the minimum of all public users
    Given user "participant1" creates room "chatting" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "chatting" with 200 (v4)

    When user "participant1" sets setting "read_status_privacy" to "1" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "1"
    When user "participant2" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant2" has capability "spreed=>config=>chat=>read-privacy" set to "0"

    When user "participant1" sends message "Message 1" to room "chatting" with 201
    Then last response has no last common read message header
    When user "participant2" sends message "Message 2" to room "chatting" with 201
    Then last response has last common read message header set to "Message 2"

    When user "participant1" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "0"

    When user "participant2" sends message "Message 3" to room "chatting" with 201
    Then last response has last common read message header set to "Message 1"
    When user "participant1" reads message "Message 3" in room "chatting" with 200
    Then last response has last common read message header set to "Message 3"
    When user "participant2" reads message "Message 3" in room "chatting" with 200
    Then last response has last common read message header set to "Message 3"


  Scenario: User switching to private is not considered anymore
    Given user "participant1" creates room "chatting" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "chatting" with 200 (v4)

    When user "participant1" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "0"
    When user "participant2" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant2" has capability "spreed=>config=>chat=>read-privacy" set to "0"

    When user "participant1" sends message "Message 1" to room "chatting" with 201
    Then last response has last common read message header less than "Message 1"
    When user "participant2" reads message "Message 1" in room "chatting" with 200
    Then last response has last common read message header set to "Message 1"

    When user "participant2" sends message "Message 2" to room "chatting" with 201
    Then last response has last common read message header set to "Message 1"
    When user "participant2" reads message "Message 2" in room "chatting" with 200
    Then last response has last common read message header set to "Message 1"

    When user "participant1" sets setting "read_status_privacy" to "1" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "1"

    When user "participant2" reads message "Message 2" in room "chatting" with 200
    Then last response has last common read message header set to "Message 2"


  Scenario: New users added start with the last message of when they are added
    Given user "participant1" creates room "chatting" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "chatting" with 200 (v4)

    When user "participant1" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>read-privacy" set to "0"
    When user "participant2" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant2" has capability "spreed=>config=>chat=>read-privacy" set to "0"
    When user "participant3" sets setting "read_status_privacy" to "0" with 200 (v1)
    Then user "participant3" has capability "spreed=>config=>chat=>read-privacy" set to "0"

    When user "participant1" sends message "Message 1" to room "chatting" with 201
    Then last response has last common read message header less than "Message 1"
    When user "participant2" sends message "Message 2" to room "chatting" with 201
    Then last response has last common read message header set to "Message 1"
    When user "participant1" reads message "Message 2" in room "chatting" with 200
    Then last response has last common read message header set to "Message 2"

    And user "participant1" adds user "participant3" to room "chatting" with 200 (v4)

    When user "participant1" reads message "Message 2" in room "chatting" with 200
    Then last response has last common read message header set to "Message 2"
    When user "participant2" reads message "Message 2" in room "chatting" with 200
    Then last response has last common read message header set to "Message 2"
    When user "participant3" reads message "Message 2" in room "chatting" with 200
    Then last response has last common read message header set to "Message 2"

