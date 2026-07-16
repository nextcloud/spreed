Feature: conversation-4/classified
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Creating a classified conversation locks it down and forces it sensitive for everyone
    Given user "participant1" creates room "classified" (v4)
      | roomType | 2 |
      | roomName | classified |
      | preset   | classified |
    And user "participant1" adds user "participant2" to room "classified" with 200 (v4)
    Then user "participant1" is participant of the following rooms (v4)
      | id         | type | attributes | listable | sipEnabled | isSensitive |
      | classified | 2    | 4          | 0        | 0          | 1           |
    And user "participant2" is participant of the following rooms (v4)
      | id         | type | attributes | listable | sipEnabled | isSensitive |
      | classified | 2    | 4          | 0        | 0          | 1           |

  Scenario: Classified conversations coerce unsafe request values on creation
    # A misbehaving client requesting a public, listable, SIP-enabled classified room
    Given user "participant1" creates room "classified" (v4)
      | roomType   | 3 |
      | roomName   | classified |
      | listable   | 1 |
      | sipEnabled | 1 |
      | preset     | classified |
    Then user "participant1" is participant of the following rooms (v4)
      | id         | type | attributes | listable | sipEnabled |
      | classified | 2    | 4          | 0        | 0          |

  Scenario: The locked-down settings of a classified conversation can not be changed
    Given user "participant1" creates room "classified" (v4)
      | roomType | 2 |
      | roomName | classified |
      | preset   | classified |
    Then user "participant1" makes room "classified" public with 400 (v4)
    And user "participant1" allows listing room "classified" for "users" with 400 (v4)
    And user "participant1" sets SIP state for room "classified" to "enabled" with 400 (v4)
    And user "participant1" starts "video" recording in room "classified" with 400 (v1)
    And user "participant1" enables live transcription in room "classified" with 400 (v1)
    And user "participant1" sets live translation target language to "en" in room "classified" with 400 (v1)
    And user "participant1" downloads call participants from "classified" as "csv" with 403 (v4)
    And user "participant1" sends message "Message 1" to room "classified" with 201
    And user "participant1" can not request summary for "classified" starting from "Message 1" with 400 (v1)

  Scenario: A classified conversation can not be marked as insensitive again
    Given user "participant1" creates room "classified" (v4)
      | roomType | 2 |
      | roomName | classified |
      | preset   | classified |
    Then user "participant1" marks room "classified" as insensitive with 400 (v4)
    And user "participant1" is participant of the following rooms (v4)
      | id         | type | attributes | isSensitive |
      | classified | 2    | 4          | 1           |

  Scenario: Messages of a classified conversation can not be replied to privately
    Given user "participant1" creates room "classified" (v4)
      | roomType | 2 |
      | roomName | classified |
      | preset   | classified |
    And user "participant1" adds user "participant2" to room "classified" with 200 (v4)
    And user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    And user "participant2" sends message "Secret" to room "classified" with 201
    Then user "participant1" sends private reply "Reply" on message "Secret" from room "classified" to room "one-to-one room" with 403 (v1)
