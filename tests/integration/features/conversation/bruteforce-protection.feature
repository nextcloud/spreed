Feature: conversation/bruteforce-protection
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given group "group1" exists

  # Does not log an attempt but shows the password form
  Scenario: User opens the call/{token} URL of a password protected public room
    Given enable brute force protection
    And user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then the following brute force attempts are registered
    When user "participant2" views call-URL of room "room" with 200
      | This conversation is password-protected. |
    Then the following brute force attempts are registered
    And disable brute force protection

  Scenario: User opens the call/{token} URL of a private room
    Given enable brute force protection
    And user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    Then the following brute force attempts are registered
    When user "participant2" views call-URL of room "room" with 200
    Then the following brute force attempts are registered
      | talkRoomToken | 1 |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    When user "participant2" views call-URL of room "room" with 200
    Then the following brute force attempts are registered
      | talkRoomToken | 1 |
    Then user "participant2" joins room "room" with 200 (v4)
    Then the following brute force attempts are registered
    And disable brute force protection

  Scenario: User opens the call/{token} URL with invalid token
    Given enable brute force protection
    Then the following brute force attempts are registered
    When user "participant2" views call-URL of room "invalid" with 200
    Then the following brute force attempts are registered
      | talkRoomToken | 1 |
    And disable brute force protection

  Scenario: User joins a password protected public room
    Given enable brute force protection
    And user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" sets password "foobar" for room "room" with 200 (v4)
    Then the following brute force attempts are registered
    # Joining without password
    When user "participant2" joins room "room" with 403 (v4)
    Then the following brute force attempts are registered
      | talkRoomPassword | 1 |
    # Joining with wrong password
    Then user "participant2" joins room "room" with 403 (v4)
      | password | wrong |
    Then the following brute force attempts are registered
      | talkRoomPassword | 2 |
    Then user "participant2" joins room "room" with 200 (v4)
      | password | foobar |
    Then the following brute force attempts are registered
    And disable brute force protection

  # Note: This test takes quite long …
  Scenario: User gets blocked after some attempts
    Given enable brute force protection
    Then the following brute force attempts are registered
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 200
    When user "participant2" views call-URL of room "invalid" with 429
    Then the following brute force attempts are registered
      | talkRoomToken | 11 |
    And disable brute force protection
