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
