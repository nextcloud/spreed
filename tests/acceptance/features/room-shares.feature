Feature: room-shares

  Scenario: share a file in a one-to-one room
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    When I act as John
    And I start the share operation
    And I select "welcome.txt" in the file picker
    And I choose the last selected file in the file picker
    Then I see that the message 1 was sent by "user0" with the text "welcome.txt"
    And I see that the message 1 contains a formatted file preview
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "welcome (2).txt"
    And I see that the message 1 contains a formatted file preview
    And I open the Files app
    And I see that the file list contains a file named "welcome (2).txt"
    And I open the details view for "welcome (2).txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me in the conversation "user0" by "user0"

  Scenario: share a file in a group room
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group conversation"
    And I add "admin" to the participants
    And I add "user1" to the participants
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "Group conversation" conversation
    And I act as Jim
    And I am logged in as "user1"
    And I have opened the Talk app
    And I open the "Group conversation" conversation
    When I act as John
    And I start the share operation
    And I select "welcome.txt" in the file picker
    And I choose the last selected file in the file picker
    Then I see that the message 1 was sent by "user0" with the text "welcome.txt"
    And I see that the message 1 contains a formatted file preview
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "welcome (2).txt"
    And I see that the message 1 contains a formatted file preview
    And I open the Files app
    And I see that the file list contains a file named "welcome (2).txt"
    And I open the details view for "welcome (2).txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me in the conversation "Group conversation" by "user0"
    And I act as Jim
    And I see that the message 1 was sent by "user0" with the text "welcome (2).txt"
    And I see that the message 1 contains a formatted file preview
    And I open the Files app
    And I see that the file list contains a file named "welcome (2).txt"
    And I open the details view for "welcome (2).txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me in the conversation "Group conversation" by "user0"

  Scenario: share a file to a group room from the Sharing tab in the Files app
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group conversation"
    And I add "admin" to the participants
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "Group conversation" conversation
    When I act as John
    And I open the Files app
    And I share "welcome.txt" with "Group conversation"
    And I see that the file is shared with "Group conversation (conversation)"
    Then I have opened the Talk app
    And I open the "Group conversation" conversation
    And I see that the message 1 was sent by "user0" with the text "welcome.txt"
    And I see that the message 1 contains a formatted file preview
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "welcome (2).txt"
    And I see that the message 1 contains a formatted file preview
    And I open the Files app
    And I see that the file list contains a file named "welcome (2).txt"
    And I open the details view for "welcome (2).txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me in the conversation "Group conversation" by "user0"
