Feature: lobby

  Scenario: join public lobby as a user
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I enable the conversation lobby
    And I see that the conversation lobby is enabled
    And I write down the public conversation link
    And I add "admin" to the participants
    When I act as Jane
    And I am logged in as the admin
    And I visit the public conversation link I wrote down
    Then I see that the "Public" conversation is active
    And I see that the "Public You are currently waiting in the lobby" empty content message is shown in the main view
    And I see that the sidebar is closed

  Scenario: join public lobby as a self-joined user
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I enable the conversation lobby
    And I see that the conversation lobby is enabled
    And I write down the public conversation link
    When I act as Jane
    And I am logged in as the admin
    And I visit the public conversation link I wrote down
    Then I see that the "Public" conversation is active
    And I see that the "Public You are currently waiting in the lobby" empty content message is shown in the main view
    And I see that the sidebar is closed



  Scenario: join public lobby protected by password as a user
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I enable the conversation lobby
    And I see that the conversation lobby is enabled
    And I protect the conversation with the password "abcdef"
    And I see that the conversation is password protected
    And I write down the public conversation link
    And I add "admin" to the participants
    When I act as Jane
    And I am logged in as the admin
    And I visit the public conversation link I wrote down
    Then I see that the "Public" conversation is active
    And I see that the "Public You are currently waiting in the lobby" empty content message is shown in the main view
    And I see that the sidebar is closed

  Scenario: join public lobby protected by password as a self-joined user
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I enable the conversation lobby
    And I see that the conversation lobby is enabled
    And I protect the conversation with the password "abcdef"
    And I see that the conversation is password protected
    And I write down the public conversation link
    When I act as Jane
    And I am logged in as the admin
    And I visit the public conversation link I wrote down
    And I see that the current page is the Authenticate page for the public conversation link I wrote down
    And I authenticate with password "abcdef" in public conversation
    Then I see that the "Public" conversation is active
    And I see that the "Public You are currently waiting in the lobby" empty content message is shown in the main view
    And I see that the sidebar is closed
