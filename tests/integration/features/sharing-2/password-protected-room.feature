Feature: sharing-2/password-protected-room
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: Shares of a public room without password can be accessed by everyone
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    When user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    Then user "guest" opens the share page of last share with 200
    And user "guest" downloads last share via public DAV with 200
    And user "participant2" opens the share page of last share with 200

  Scenario: Shares of a password protected room can only be accessed by participants
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public room" with 200 (v4)
    When user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    Then user "guest" opens the share page of last share with 303
    And user "guest" downloads last share via public DAV with 401
    And user "participant2" opens the share page of last share with 303
    When user "guest" joins room "public room" with 200 (v4)
      | password | foobar |
    And user "participant2" joins room "public room" with 200 (v4)
      | password | foobar |
    Then user "guest" opens the share page of last share with 200
    And user "guest" downloads last share via public DAV with 200
    And user "participant2" opens the share page of last share with 200
    And user "guest2" opens the share page of last share with 303
    And user "guest2" downloads last share via public DAV with 401
    And user "participant3" opens the share page of last share with 303

  Scenario: Shares are accessible again after the room password is removed
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "public room" with 200 (v4)
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    And user "guest" opens the share page of last share with 303
    When user "participant1" sets password "" for room "public room" with 200 (v4)
    Then user "guest" opens the share page of last share with 200
    And user "guest" downloads last share via public DAV with 200

  Scenario: Existing shares are protected when the room password is set
    Given user "participant1" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" shares "welcome.txt" with room "public room" with OCS 100
    And user "guest" opens the share page of last share with 200
    When user "participant1" sets password "foobar" for room "public room" with 200 (v4)
    Then user "guest2" opens the share page of last share with 303
    And user "guest2" downloads last share via public DAV with 401

  Scenario: Existing shares are protected when a group room is made public with a password
    Given user "participant1" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" shares "welcome.txt" with room "group room" with OCS 100
    When user "participant1" makes room "group room" public with 200 (v4)
      | password | foobar |
    And user "participant1" refreshes last share
    Then user "guest" opens the share page of last share with 303
    And user "guest" downloads last share via public DAV with 401
    When user "guest" joins room "group room" with 200 (v4)
      | password | foobar |
    Then user "guest" opens the share page of last share with 200
    And user "guest" downloads last share via public DAV with 200
