Feature: public share auth

  Scenario: try to access a link share with a password protected by Talk
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I set the password of the shared link as protected by Talk
    And I see that the password of the link share is protected by Talk
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    Then I see that the current page is the Authenticate page for the shared link I wrote down
    And I see that the request password button is shown

  Scenario: try to access a link share with a password no longer protected by Talk
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I set the password of the shared link as protected by Talk
    And I see that the password of the link share is protected by Talk
    And I set the password of the shared link as not protected by Talk
    And I see that the password of the link share is not protected by Talk
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    Then I see that the current page is the Authenticate page for the shared link I wrote down
    And I see that the request password button is not shown



  Scenario: chat in the authentication page of a link share with a password protected by Talk
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I set the password of the shared link as protected by Talk
    And I see that the password of the link share is protected by Talk
    And I write down the shared link
    And I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the Authenticate page for the shared link I wrote down
    When I request the password
    And I send a new chat message with the text "Hello @user0"
    And I see that the message 1 was sent by "Guest" with the text "Hello user0"
    And I act as John
    And I have opened the Talk app
    And I open the "Password request: welcome.txt" conversation
    And I type a new chat message with the text "Hi @"
    And I choose the candidate mention for "Guest"
    And I send the current chat message
    Then I see that the message 1 was sent by "Guest" with the text "Hello user0"
    And I see that the message 1 contains a formatted mention of "user0" as current user
    # The generated avatar is plain text, so it appears in the message itself
    And I see that the message 2 was sent by "user0" with the text "Hi ?Guest"
    And I see that the message 2 contains a formatted mention of "Guest"
    And I act as Jane
    And I see that the message 1 was sent by "Guest" with the text "Hello user0"
    And I see that the message 1 contains a formatted mention of "user0"
    And I see that the message 2 was sent by "user0" with the text "Hi ?Guest"
    And I see that the message 2 contains a formatted mention of "Guest" as current user

  Scenario: access a link share with a password protected by Talk after a chat
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I set the password of the shared link as protected by Talk
    And I see that the password of the link share is protected by Talk
    And I write down the shared link
    And I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the Authenticate page for the shared link I wrote down
    And I request the password
    And I send a new chat message with the text "Hello"
    And I see that the message 1 was sent by "Guest" with the text "Hello"
    And I act as John
    And I have opened the Talk app
    And I open the "Password request: welcome.txt" conversation
    And I send a new chat message with the text "Hi!"
    When I act as Jane
    And I see that the message 2 was sent by "user0" with the text "Hi!"
    And I authenticate with password "abcdef"
    Then I see that the current page is the shared link I wrote down
    And I see that the shared file preview shows the text "Welcome to your Nextcloud account!"
    And I act as John
    And I see that the "Password request: welcome.txt" conversation is not shown in the list
    # This fails when run without any timeout multiplier, as currently the empty
    # content message is shown after receiving several 404 errors instead of on
    # the first one.
    And I see that the "This conversation has ended" empty content message is shown in the main view
    And I see that the sidebar is closed
