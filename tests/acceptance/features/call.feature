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
