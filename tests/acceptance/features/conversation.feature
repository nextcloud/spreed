Feature: conversation

  Scenario: create a group conversation
    Given I am logged in
    And I have opened the Talk app
    When I create a group conversation named "Group"
    Then I see that the "Group" conversation is active
    And I see that the chat is shown in the main view
    And I see that the sidebar is open
    And I see that the number of participants shown in the list is "1"
    And I see that "user0" is shown in the list of participants as a moderator

  Scenario: create a one-to-one conversation
    Given I am logged in
    And I have opened the Talk app
    When I create a one-to-one conversation with "admin"
    Then I see that the "admin" conversation is active
    And I see that the chat is shown in the main view
    And I see that the sidebar is open
    And I see that the number of participants shown in the list is "2"
    And I see that "user0" is shown in the list of participants as a moderator
    And I see that "admin" is shown in the list of participants as a moderator

  Scenario: rename a conversation
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the "Group" conversation is active
    When I rename the conversation to "Test conversation"
    Then I see that the "Test conversation" conversation is active

  Scenario: change between conversations
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the "Group" conversation is active
    And I see that the number of participants shown in the list is "1"
    And I create a one-to-one conversation with "admin"
    And I see that the "Group" conversation is not active
    And I see that the "admin" conversation is active
    And I see that the number of participants shown in the list is "2"
    When I open the "Group" conversation
    Then I see that the "Group" conversation is active
    And I see that the "admin" conversation is not active
    And I see that the number of participants shown in the list is "1"

  Scenario: leave a conversation
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the "Group" conversation is active
    When I leave the "Group" conversation
    Then I see that the "Group" conversation is not shown in the list
    And I see that the "Join a conversation or start a new one Say hi to your friends and colleagues!" empty content message is shown in the main view
    And I see that the sidebar is closed

  Scenario: delete a one-to-one conversation
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    And I see that the chat is shown in the main view
    And I see that the sidebar is open
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    And I see that the "user0" conversation is active
    And I see that the chat is shown in the main view
    And I see that the sidebar is open
    When I act as John
    And I delete the "admin" conversation
    Then I see that the "admin" conversation is not shown in the list
    And I see that the "Join a conversation or start a new one Say hi to your friends and colleagues!" empty content message is shown in the main view
    And I see that the sidebar is closed
    And I act as Jane
    And I see that the "user0" conversation is not shown in the list
    And I see that the "This conversation has ended" empty content message is shown in the main view
    And I see that the sidebar is closed

  Scenario: create a new conversation after leaving the active one
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the "Group" conversation is active
    And I leave the "Group" conversation
    And I see that the "Group" conversation is not shown in the list
    And I see that the "Join a conversation or start a new one Say hi to your friends and colleagues!" empty content message is shown in the main view
    And I see that the sidebar is closed
    When I create a group conversation named "Group"
    Then I see that the "Group" conversation is active
    And I see that the chat is shown in the main view
    And I see that the sidebar is open
    And I see that the number of participants shown in the list is "1"
    And I see that "user0" is shown in the list of participants as a moderator

  Scenario: change to another conversation after leaving the active one
    Given I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the "admin" conversation is active
    And I see that the number of participants shown in the list is "2"
    And I create a group conversation named "Group"
    And I see that the "admin" conversation is not active
    And I see that the "Group" conversation is active
    And I see that the number of participants shown in the list is "1"
    And I leave the "Group" conversation
    And I see that the "Group conversation is not shown in the list
    And I see that the "Join a conversation or start a new one Say hi to your friends and colleagues!" empty content message is shown in the main view
    And I see that the sidebar is closed
    When I open the "admin" conversation
    Then I see that the "admin" conversation is active
    And I see that the chat is shown in the main view
    And I see that the sidebar is open
    And I see that the number of participants shown in the list is "2"
    And I see that "user0" is shown in the list of participants as a moderator
    And I see that "admin" is shown in the list of participants as a moderator
