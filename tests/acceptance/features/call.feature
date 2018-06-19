Feature: call

  Scenario: start a call
    Given I act as "Kermit with microphone and camera"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    When I join the call
    Then I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    And I see that the local audio is enabled
    And I see that the local video is enabled
    And I see that the local video is shown
    And I see that the local avatar is not shown

  Scenario: start a call without camera
    Given I act as "Karen with microphone"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    When I join the call
    Then I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    And I see that the local audio is enabled
    And I see that the local avatar is shown
    And I see that the local video is not available
    And I see that the local video is not shown

  Scenario: start a call without camera nor microphone
    Given I act as "James"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    When I join the call
    Then I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    And I see that the local avatar is shown
    And I see that the local audio is not available
    And I see that the local video is not available
    And I see that the local video is not shown

  Scenario: leave a call
    Given I act as "Kermit with microphone and camera"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    And I join the call
    And I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    When I leave the call
    Then I see that the chat is shown in the main view

  Scenario: join a call
    Given I act as "Kermit with microphone and camera"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    And I join the call
    And I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    And I act as "April with microphone and camera"
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    And I see that the "user0" conversation is active
    When I join the call
    Then I see that the chat is shown in the sidebar
    And I see that the local audio is enabled
    And I see that the local video is enabled
    And I see that the local video is shown
    And I see that the local avatar is not shown
    And I see that the promoted audio is enabled
    And I see that the promoted video is enabled
    And I see that the promoted video is shown
    And I see that the promoted avatar is not shown
    And I see that the promoted user is "user0"
    And I act as "Kermit with microphone and camera"
    And I see that the promoted audio is enabled
    And I see that the promoted video is enabled
    And I see that the promoted video is shown
    And I see that the promoted avatar is not shown
    And I see that the promoted user is "admin"

  Scenario: join a call without camera
    Given I act as "Kermit with microphone and camera"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    And I join the call
    And I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    And I act as "Karen with microphone"
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    And I see that the "user0" conversation is active
    When I join the call
    Then I see that the chat is shown in the sidebar
    And I see that the local audio is enabled
    And I see that the local avatar is shown
    And I see that the local video is not available
    And I see that the local video is not shown
    And I see that the promoted audio is enabled
    And I see that the promoted video is enabled
    And I see that the promoted video is shown
    And I see that the promoted avatar is not shown
    And I see that the promoted user is "user0"
    And I act as "Kermit with microphone and camera"
    And I see that the promoted audio is enabled
    And I see that the promoted avatar is shown
    And I see that the promoted video is not shown
    And I see that the promoted user is "admin"

  Scenario: leave a call with another participant
    Given I act as "Kermit with microphone and camera"
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    And I join the call
    And I see that the chat is shown in the sidebar
    And I see that the "Waiting for admin to join the call …" empty content message is shown in the main view
    And I act as "April with microphone and camera"
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    And I see that the "user0" conversation is active
    And I join the call
    And I see that the chat is shown in the sidebar
    And I see that the promoted video is shown
    And I act as "Kermit with microphone and camera"
    And I see that the promoted video is shown
    When I leave the call
    Then I see that the chat is shown in the main view
    And I act as "April with microphone and camera"
    And I see that the chat is shown in the sidebar
    And I see that the "Waiting for user0 to join the call …" empty content message is shown in the main view
