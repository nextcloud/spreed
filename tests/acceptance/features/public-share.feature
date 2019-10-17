Feature: public share

  Scenario: open the public shared link of a file
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    Then I see that the Talk sidebar is shown in the public share page

  Scenario: open the public shared link of a folder
    Given I act as John
    And I am logged in
    And I create a new folder named "Shared folder"
    # To share the link the "Share" inline action has to be clicked but, as the
    # details view is opened automatically when the folder is created, clicking
    # on the inline action could fail if it is covered by the details view due
    # to its opening animation. Instead of ensuring that the animations of the
    # contents and the details view have both finished it is easier to close the
    # details view and wait until it is closed before continuing.
    And I close the details view
    And I see that the details view is closed
    And I share the link for "Shared folder"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    Then I see that the Talk sidebar is not shown in the public share page



  Scenario: open Talk after opening the public shared link of a file as a user with direct access to the file
    Given I act as John
    And I am logged in as the admin
    And I share "welcome.txt" with "user0"
    And I see that the file is shared with "user0"
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I act as Jane
    And I am logged in
    And I have opened the Talk app
    # Wait until the "Talk updates" conversation is shown to ensure that the
    # list is loaded before checking that there is no "welcome.txt" conversation
    And I see that the "Talk updates ✅" conversation is shown in the list
    And I see that the "welcome.txt" conversation is not shown in the list
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    And I see that the current participant is the user "user0"
    # Visit the Home page so the header shows again the list of apps
    When I visit the Home page
    And I have opened the Talk app
    Then I see that the "welcome.txt" conversation is shown in the list

  Scenario: open Talk after opening the public shared link of a file as a user without direct access to the file
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    # Wait until the "Talk updates" conversation is shown to ensure that the
    # list is loaded before checking that there is no "welcome.txt" conversation
    And I see that the "Talk updates ✅" conversation is shown in the list
    And I see that the "welcome.txt" conversation is not shown in the list
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    And I see that the current participant is the user "admin"
    # Log in with the same user from a different window to check Talk while the
    # original window is in the public share page
    And I act as Jim
    And I am logged in as the admin
    And I have opened the Talk app
    And I see that the "welcome.txt" conversation is shown in the list
    # Leave the public share page from the original window by going to Talk and
    # checking that the conversation is no longer shown
    When I act as Jane
    # Visit the Home page so the header shows again the list of apps
    And I visit the Home page
    And I have opened the Talk app
    # Wait until the "Talk updates" conversation is shown to ensure that the
    # list is loaded before checking that there is no "welcome.txt" conversation
    And I see that the "Talk updates ✅" conversation is shown in the list
    Then I see that the "welcome.txt" conversation is not shown in the list



  Scenario: mention a user that has direct access to a file shared by link
    Given I act as John
    And I am logged in as the admin
    And I share "welcome.txt" with "user0"
    And I see that the file is shared with "user0"
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    When I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "user0"
    And I send the current chat message
    Then I see that the message 1 was sent by "admin" with the text "Hello user0"
    And I see that the message 1 contains a formatted mention of "user0"
    And I act as Jane
    And I am logged in
    And I have opened the Talk app
    And I see that the "welcome.txt" conversation is shown in the list

  Scenario: mention a user that has no direct access to a file shared by link
    Given I act as John
    And I am logged in as the admin
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    When I send a new chat message with the text "Hello @user0"
    Then I see that the message 1 was sent by "admin" with the text "Hello user0"
    And I see that the message 1 contains a formatted mention of "user0"
    And I act as Jane
    And I am logged in
    And I have opened the Talk app
    # Wait until the "Talk updates" conversation is shown to ensure that the
    # list is loaded before checking that there is no "welcome.txt" conversation
    And I see that the "Talk updates ✅" conversation is shown in the list
    And I see that the "welcome.txt" conversation is not shown in the list

  Scenario: mention a user that has no direct access to a file shared by link while the user is in the public share page
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    And I act as Jim
    And I am logged in as the admin
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    When I act as Jane
    And I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "admin"
    And I send the current chat message
    Then I see that the message 1 was sent by "Guest" with the text "Hello admin"
    And I see that the message 1 contains a formatted mention of "admin"
    And I act as Jim
    And I see that the message 1 was sent by "Guest" with the text "Hello admin"
    And I see that the message 1 contains a formatted mention of "admin" as current user
    # Leave the public share page from the original window by going to Talk and
    # checking that the conversation is no longer shown
    # Visit the Home page so the header shows again the list of apps
    And I visit the Home page
    And I have opened the Talk app
    # Wait until the "Talk updates" conversation is shown to ensure that the
    # list is loaded before checking that there is no "welcome.txt" conversation
    And I see that the "Talk updates ✅" conversation is shown in the list
    And I see that the "welcome.txt" conversation is not shown in the list

  Scenario: mention another guest in the public share page
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    And I set my guest name to "Cat"
    And I act as Jim
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    When I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "Cat"
    And I send the current chat message
    Then I see that the message 1 was sent by "Guest" with the text "Hello CCat"
    And I see that the message 1 contains a formatted mention of "Cat"
    And I act as Jane
    And I see that the message 1 was sent by "Guest" with the text "Hello CCat"
    And I see that the message 1 contains a formatted mention of "Cat" as current user

  Scenario: mention all users when a user with direct access has not joined yet the file room
    Given I act as John
    And I am logged in as the admin
    And I share "welcome.txt" with "user0"
    And I see that the file is shared with "user0"
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the Talk sidebar is shown in the public share page
    And I join the conversation in the Talk sidebar in the public share page
    When I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "welcome.txt"
    And I send the current chat message
    Then I see that the message 1 was sent by "Guest" with the text "Hello welcome.txt"
    And I see that the message 1 contains a formatted mention of all participants of "welcome.txt"
    And I act as Jim
    And I am logged in
    And I have opened the Talk app
    # Wait until the "Talk updates" conversation is shown to ensure that the
    # list is loaded before checking that there is no "welcome.txt" conversation
    And I see that the "Talk updates ✅" conversation is shown in the list
    And I see that the "welcome.txt" conversation is not shown in the list



  Scenario: chat in the public share page of a link share
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I write down the shared link
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I act as Jane
    And I am logged in as the admin
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I act as Jim
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I set my guest name to "Rob"
    When I act as John
    And I send a new chat message with the text "Hello"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello"
    And I send a new chat message with the text "Hi!"
    And I act as Jim
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I send a new chat message with the text "Hey!"
    Then I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I see that the message 3 was sent by "Rob" with the text "Hey!"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I see that the message 3 was sent by "Rob" with the text "Hey!"
    And I act as John
    And I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I see that the message 3 was sent by "Rob" with the text "Hey!"



  Scenario: chat in the public share page of a link share with a password
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I write down the shared link
    And I visit the shared link I wrote down
    # Even the owner needs to authenticate when accessing the shared link
    And I see that the current page is the Authenticate page for the shared link I wrote down
    And I authenticate with password "abcdef"
    And I see that the current page is the shared link I wrote down
    And I act as Jane
    And I am logged in as the admin
    And I visit the shared link I wrote down
    And I see that the current page is the Authenticate page for the shared link I wrote down
    And I authenticate with password "abcdef"
    And I see that the current page is the shared link I wrote down
    And I act as Jim
    And I visit the shared link I wrote down
    And I see that the current page is the Authenticate page for the shared link I wrote down
    And I authenticate with password "abcdef"
    And I see that the current page is the shared link I wrote down
    When I act as John
    And I send a new chat message with the text "Hello"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello"
    And I send a new chat message with the text "Hi!"
    And I act as Jim
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I send a new chat message with the text "Hey!"
    Then I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I see that the message 3 was sent by "Guest" with the text "Hey!"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I see that the message 3 was sent by "Guest" with the text "Hey!"
    And I act as John
    And I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent by "admin" with the text "Hi!"
    And I see that the message 3 was sent by "Guest" with the text "Hey!"
