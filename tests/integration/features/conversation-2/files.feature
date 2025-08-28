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
    When user "participant1" gets the room for path "welcome.txt" with 404 (v1)



  Scenario: get room for file shared with user
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)

  Scenario: get room for folder shared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "test" with 404 (v1)
    And user "participant2" gets the room for path "test" with 404 (v1)

  Scenario: get room for file in folder shared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "test/renamed.txt" with 200 (v1)
    And user "participant2" gets the room for path "test/renamed.txt" with 200 (v1)
    Then user "participant1" is not participant of room "file test/renamed.txt room" (v4)
    And user "participant2" is not participant of room "file test/renamed.txt room" (v4)

  Scenario: get room for file in folder reshared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "test" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    When user "participant1" gets the room for path "test/renamed.txt" with 200 (v1)
    And user "participant2" gets the room for path "test/renamed.txt" with 200 (v1)
    And user "participant3" gets the room for path "test/renamed.txt" with 200 (v1)
    Then user "participant1" is not participant of room "file test/renamed.txt room" (v4)
    And user "participant2" is not participant of room "file test/renamed.txt room" (v4)
    And user "participant3" is not participant of room "file test/renamed.txt room" (v4)

  Scenario: get room for file no longer shared
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" deletes last share
    When user "participant1" gets the room for path "welcome.txt" with 404 (v1)



  Scenario: get room for file shared with group
    Given user "participant1" shares "welcome.txt" with group "group1" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)

  Scenario: get room for file shared with user and group
    Given user "participant1" shares "welcome.txt" with group "group1" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant3" gets the room for path "welcome (2).txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant3" is not participant of room "file welcome (2).txt room" (v4)


  Scenario: get room for file shared with team
    Given team "team1" exists
    And add user "participant1" to team "team1"
    And add user "participant2" to team "team1"
    And user "participant1" shares "welcome.txt" with team "team1" with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)


  Scenario: get room for link share
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant1" gets the room for last share with 200 (v1)
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant3" gets the room for last share with 200 (v1)
    And user "guest" gets the room for last share with 200 (v1)
    Then user "participant1" is not participant of room "file last share room" (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    And user "participant3" is not participant of room "file last share room" (v4)
    And user "guest" is not participant of room "file last share room" (v4)

  Scenario: get room for link share protected by password
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
    When user "participant1" gets the room for last share with 404 (v1)
    And user "participant2" gets the room for last share with 404 (v1)
    And user "participant3" gets the room for last share with 404 (v1)
    And user "guest" gets the room for last share with 404 (v1)

  Scenario: get room for link share of a folder
    Given user "participant1" creates folder "/test"
    And user "participant1" shares "test" by link with OCS 100
    When user "participant1" gets the room for last share with 404 (v1)
    And user "participant2" gets the room for last share with 404 (v1)
    And user "guest" gets the room for last share with 404 (v1)

  Scenario: get room for link no longer shared
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" deletes last share
    When user "participant1" gets the room for last share with 404 (v1)
    And user "participant2" gets the room for last share with 404 (v1)
    And user "participant3" gets the room for last share with 404 (v1)
    And user "guest" gets the room for last share with 404 (v1)

  Scenario: get room for file shared by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome.txt room" (v4)

  Scenario: get room for file shared by link and protected by password
    Given user "participant1" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome.txt room" (v4)

  Scenario: get room for folder shared by link
    Given user "participant1" creates folder "/test"
    And user "participant1" shares "test" by link with OCS 100
    When user "participant1" gets the room for path "test" with 404 (v1)

  Scenario: get room for file in folder shared by link
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" by link with OCS 100
    When user "participant1" gets the room for path "test/renamed.txt" with 404 (v1)

  Scenario: get room for file in folder shared by link and reshared with user
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" by link with OCS 100
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "test/renamed.txt" with 200 (v1)
    And user "participant2" gets the room for path "test/renamed.txt" with 200 (v1)
    Then user "participant1" is not participant of room "file test/renamed.txt room" (v4)
    And user "participant2" is not participant of room "file test/renamed.txt room" (v4)

  Scenario: get room for file shared with user and by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    When user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    Then user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)

  Scenario: get room for last link share also shared with user
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    When user "participant1" gets the room for last share with 200 (v1)
    And user "participant2" gets the room for last share with 200 (v1)
    Then user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)



  Scenario: owner of a shared file can join its room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    When user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    Then user "participant1" is participant of room "file welcome (2).txt room" (v4)

  Scenario: user with access to a file can join its room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    When user "participant2" joins room "file welcome.txt room" with 200 (v4)
    Then user "participant2" is participant of room "file welcome.txt room" (v4)

  Scenario: owner of a file in a shared folder can join its room
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" gets the room for path "test/renamed.txt" with 200 (v1)
    When user "participant1" joins room "file test/renamed.txt room" with 200 (v4)
    Then user "participant1" is participant of room "file test/renamed.txt room" (v4)

  Scenario: user with access to a file in a shared folder can join its room
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "test/renamed.txt" with 200 (v1)
    When user "participant2" joins room "file test/renamed.txt room" with 200 (v4)
    Then user "participant2" is participant of room "file test/renamed.txt room" (v4)

  Scenario: user with access to a file in a reshared folder can join its room
    Given user "participant1" creates folder "/test"
    And user "participant1" moves file "/welcome.txt" to "/test/renamed.txt" with 201
    And user "participant1" shares "test" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" shares "test" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant3" gets the room for path "test/renamed.txt" with 200 (v1)
    When user "participant3" joins room "file test/renamed.txt room" with 200 (v4)
    Then user "participant3" is participant of room "file test/renamed.txt room" (v4)

  Scenario: owner of a no longer shared file can join its room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" deletes last share
    When user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    Then user "participant1" is participant of room "file welcome (2).txt room" (v4)

  Scenario: user no longer with access to a file can not join its room
    Given user "participant1" shares "welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant1" deletes last share
    When user "participant2" joins room "file welcome.txt room" with 404 (v4)
    Then user "participant2" is not participant of room "file welcome.txt room" (v4)

  Scenario: user without access to a file can not join its room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    When user "participant3" joins room "file welcome.txt room" with 404 (v4)
    Then user "participant3" is not participant of room "file welcome.txt room" (v4)

  Scenario: guest can not join a file room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    When user "guest" joins room "file welcome.txt room" with 404 (v4)



  Scenario: join room for file shared with group
    Given user "participant1" shares "welcome.txt" with group "group1" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    When user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant2" joins room "file welcome.txt room" with 200 (v4)
    Then user "participant1" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)

  Scenario: join room for file shared with user and group
    Given user "participant1" shares "welcome.txt" with group "group1" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" with user "participant3" with OCS 100
    And user "participant3" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant3" gets the room for path "welcome (2).txt" with 200 (v1)
    When user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant2" joins room "file welcome.txt room" with 200 (v4)
    And user "participant3" joins room "file welcome.txt room" with 200 (v4)
    Then user "participant1" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)
    And user "participant3" is participant of room "file welcome (2).txt room" (v4)



  Scenario: owner of a file shared by link can join its room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    When user "participant1" joins room "file last share room" with 200 (v4)
    Then user "participant1" is participant of room "file last share room" (v4)

  Scenario: user with access to a file shared by link can join its room
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant2" gets the room for last share with 200 (v1)
    When user "participant2" joins room "file last share room" with 200 (v4)
    Then user "participant2" is participant of room "file last share room" (v4)

  Scenario: user without access to a file shared by link can join its room
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant2" logs in
    And user "participant2" gets the room for last share with 200 (v1)
    When user "participant2" joins room "file last share room" with 200 (v4)
    Then user "participant2" is participant of room "file last share room" (v4)

  Scenario: guest can join the room of a file shared by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "guest" gets the room for last share with 200 (v1)
    When user "guest" joins room "file last share room" with 200 (v4)
    And user "guest" is participant of room "file last share room" (v4)



  Scenario: owner of a shared file is not removed from its room after leaving it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    # Note that the room token is got by a different user than the one that
    # joins the room
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome (2).txt room" (v4)
    When user "participant1" leaves room "file welcome (2).txt room" with 200 (v4)
    Then user "participant1" is participant of room "file welcome (2).txt room" (v4)

  Scenario: user with access to a file is not removed from its room after leaving it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    # Note that the room token is got by a different user than the one that
    # joins the room
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" joins room "file welcome.txt room" with 200 (v4)
    And user "participant2" is participant of room "file welcome.txt room" (v4)
    When user "participant2" leaves room "file welcome.txt room" with 200 (v4)
    Then user "participant2" is participant of room "file welcome.txt room" (v4)



  Scenario: owner of a file shared by link is not removed from its room after leaving it
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    When user "participant1" leaves room "file last share room" with 200 (v4)
    Then user "participant1" is participant of room "file last share room" (v4)

  Scenario: user with access to a file shared by link is not removed from its room after leaving it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    When user "participant2" leaves room "file last share room" with 200 (v4)
    Then user "participant2" is participant of room "file last share room" (v4)

  Scenario: user without access to a file shared by link is removed from its room after leaving it
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant2" logs in
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    When user "participant2" leaves room "file last share room" with 200 (v4)
    Then user "participant2" is not participant of room "file last share room" (v4)

  Scenario: guest is removed from the room of a file shared by link after leaving it
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "guest" gets the room for last share with 200 (v1)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "guest" is participant of room "file last share room" (v4)
    When user "guest" leaves room "file last share room" with 200 (v4)
    And user "guest" is not participant of room "file last share room" (v4)



  Scenario: owner of a shared file can join its room again after removing self from it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    # Note that the room token is got by a different user than the one that
    # joins the room
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome (2).txt room" (v4)
    When user "participant1" removes themselves from room "file welcome (2).txt room" with 200 (v4)
    And user "participant1" is not participant of room "file welcome (2).txt room" (v4)
    And user "participant1" joins room "file welcome (2).txt room" with 200 (v4)
    Then user "participant1" is participant of room "file welcome (2).txt room" (v4)

  Scenario: user with access to a file can join its room again after removing self from it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    # Note that the room token is got by a different user than the one that
    # joins the room
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant2" joins room "file welcome.txt room" with 200 (v4)
    And user "participant2" is participant of room "file welcome.txt room" (v4)
    When user "participant2" removes themselves from room "file welcome.txt room" with 200 (v4)
    And user "participant2" is not participant of room "file welcome.txt room" (v4)
    And user "participant2" joins room "file welcome.txt room" with 200 (v4)
    Then user "participant2" is participant of room "file welcome.txt room" (v4)



  Scenario: owner of a file shared by link can join its room again after removing self from it
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    When user "participant1" removes themselves from room "file last share room" with 200 (v4)
    And user "participant1" is not participant of room "file last share room" (v4)
    And user "participant1" joins room "file last share room" with 200 (v4)
    Then user "participant1" is participant of room "file last share room" (v4)

  Scenario: user with access to a file shared by link can join its room again after removing self from it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    When user "participant2" removes themselves from room "file last share room" with 200 (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    Then user "participant2" is participant of room "file last share room" (v4)

  Scenario: user without access to a file shared by link can join its room again after removing self from it
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant2" logs in
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    When user "participant2" removes themselves from room "file last share room" with 200 (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    And user "participant2" joins room "file last share room" with 200 (v4)
    Then user "participant2" is participant of room "file last share room" (v4)

  # Guests can not remove themselves from a room.



  # Participants are removed from the room for a no longer shared file once they
  # try to join the room again, but not when the file is unshared.

  Scenario: owner is participant of room for file no longer shared
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" gets the room for path "welcome.txt" with 200 (v1)
    And user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant1" leaves room "file welcome.txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome.txt room" (v4)
    When user "participant1" deletes last share
    Then user "participant1" is participant of room "file welcome.txt room" (v4)
    And user "participant1" joins room "file welcome.txt room" with 200 (v4)
    And user "participant1" is participant of room "file welcome.txt room" (v4)

  Scenario: user is not participant of room for file no longer with access to it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant2" gets the room for path "welcome (2).txt" with 200 (v1)
    And user "participant2" joins room "file welcome (2).txt room" with 200 (v4)
    And user "participant2" leaves room "file welcome (2).txt room" with 200 (v4)
    And user "participant2" is participant of room "file welcome (2).txt room" (v4)
    When user "participant1" deletes last share
    Then user "participant2" is participant of room "file welcome (2).txt room" (v4)
    And user "participant2" joins room "file welcome (2).txt room" with 404 (v4)
    And user "participant2" is not participant of room "file welcome (2).txt room" (v4)



  Scenario: owner is participant of room for file no longer shared by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant1" gets the room for last share with 200 (v1)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant1" leaves room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)
    When user "participant1" deletes last share
    Then user "participant1" is participant of room "file last share room" (v4)
    And user "participant1" joins room "file last share room" with 200 (v4)
    And user "participant1" is participant of room "file last share room" (v4)

  Scenario: user is participant of room for file no longer shared by link but with access to it
    Given user "participant1" shares "welcome.txt" with user "participant2" with OCS 100
    And user "participant2" accepts last share
    And user "participant1" shares "welcome.txt" by link with OCS 100
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" leaves room "file last share room" with 200 (v4)
    And user "participant2" is participant of room "file last share room" (v4)
    When user "participant1" deletes last share
    Then user "participant2" is participant of room "file last share room" (v4)
    # Although the room was created for the shared link it will still be
    # available to other types of shares after the shared link is deleted.
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" is participant of room "file last share room" (v4)

  Scenario: user is not participant of room for file no longer shared by link and without access to it
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    # Users without access to a file shared by link need to log in (so further
    # requests keep the same session) and get the room (so the share token is
    # stored in the session) to be able to join it.
    And user "participant2" logs in
    And user "participant2" gets the room for last share with 200 (v1)
    And user "participant2" joins room "file last share room" with 200 (v4)
    And user "participant2" leaves room "file last share room" with 200 (v4)
    And user "participant2" is not participant of room "file last share room" (v4)
    When user "participant1" deletes last share
    Then user "participant2" is not participant of room "file last share room" (v4)
    And user "participant2" joins room "file last share room" with 404 (v4)
    And user "participant2" is not participant of room "file last share room" (v4)

  Scenario: guest is not participant of room for file no longer shared by link
    Given user "participant1" shares "welcome.txt" by link with OCS 100
    And user "guest" gets the room for last share with 200 (v1)
    And user "guest" joins room "file last share room" with 200 (v4)
    And user "guest" leaves room "file last share room" with 200 (v4)
    When user "participant1" deletes last share
    Then user "guest" is not participant of room "file last share room" (v4)
    And user "guest" joins room "file last share room" with 404 (v4)
    And user "guest" is not participant of room "file last share room" (v4)
