Feature: sharing-1/conflicts

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: create share with an owned one-to-one room
    Given user "participant1" creates room "own one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    When user "participant1" creates folder "bar"
    When user "participant1" creates folder "foo"
    When user "participant1" creates folder "foo/bar"
    When user "participant1" shares "bar" with room "own one-to-one room"
    When user "participant1" shares "foo/bar" with room "own one-to-one room"
    When user "participant2" gets the DAV properties for "/"
    Then the list of returned files for "participant2" is
      | / |
      | /Talk/ |
      | /welcome.txt |
    When user "participant2" gets the DAV properties for "/Talk"
    Then the list of returned files for "participant2" is
      | /Talk/ |
      | /Talk/bar%20(2)/ |
      | /Talk/bar/ |
