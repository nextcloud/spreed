Feature: conversation/files

  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And group "group1" exists
    And user "participant2" is member of group "group1"

  # When "user XXX gets the room for path YYY with 200" succeeds the room token
  # can later be used by any participant using the "file YYY room" identifier.

  Scenario: get room for file not shared
    When user "participant1" gets the room for path "welcome.txt" with 404



  Scenario: get room for file shared with user
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    Then user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"

  Scenario: get room for folder shared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" shares "test" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "test" with 404
    And user "participant2" gets the room for path "test" with 404

  Scenario: get room for file in folder shared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "test/renamed.txt" with 200
    And user "participant2" gets the room for path "test/renamed.txt" with 200
    Then user "participant1" is participant of room "file test/renamed.txt room"
    And user "participant2" is participant of room "file test/renamed.txt room"

  Scenario: get room for file in folder reshared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" shares "test" with user "participant3" with OCS 100
    When user "participant1" gets the room for path "test/renamed.txt" with 200
    And user "participant2" gets the room for path "test/renamed.txt" with 200
    And user "participant3" gets the room for path "test/renamed.txt" with 200
    Then user "participant1" is participant of room "file test/renamed.txt room"
    And user "participant2" is participant of room "file test/renamed.txt room"
    And user "participant3" is participant of room "file test/renamed.txt room"

  Scenario: get room for file no longer shared
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant1" deletes last share
    When user "participant1" gets the room for path "welcome.txt" with 404



  Scenario: get room for file shared with group
    Given user "participant1" shares "welcome.txt" with group "group1" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    Then user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"

  Scenario: get room for file shared with user and group
    Given user "participant1" shares "welcome.txt" with group "group1" with OCS 100
    And user "participant1" shares "welcome.txt" with user "participant3" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    And user "participant3" gets the room for path "welcome (2).txt" with 200
    Then user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"
    And user "participant3" is participant of room "file welcome (2).txt room"



  Scenario: get room for file shared by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 404

  Scenario: get room for file shared with user and by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200
    And user "participant2" gets the room for path "welcome (2).txt" with 200
    Then user "participant1" is participant of room "file welcome (2).txt room"
    And user "participant2" is participant of room "file welcome (2).txt room"
