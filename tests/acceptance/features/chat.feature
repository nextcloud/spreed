Feature: chat

  Scenario: send a message
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the chat is shown in the main view
    When I send a new chat message with the text "Hello"
    Then I see that the message 1 was sent by "user0" with the text "Hello"

  Scenario: send several messages
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the chat is shown in the main view
    When I send a new chat message with the text "Hello"
    And I send a new chat message with the text "World"
    And I send a new chat message with the text "How is it going?"
    Then I see that the message 1 was sent by "user0" with the text "Hello"
    And I see that the message 2 was sent with the text "World" and grouped with the previous one
    And I see that the message 3 was sent with the text "How is it going?" and grouped with the previous one

  Scenario: receive a message from another user when the conversation was not opened yet
    Given I act as John
    And I am logged in as the admin
    And I have opened the Talk app
    And I create a one-to-one conversation with "user0"
    And I see that the chat is shown in the main view
    And I send a new chat message with the text "Hello"
    When I act as Jane
    And I am logged in
    And I have opened the Talk app
    And I open the "admin" conversation
    And I see that the chat is shown in the main view
    Then I see that the message 1 was sent by "admin" with the text "Hello"

  Scenario: receive several messages from another user when the conversation was not opened yet
    Given I act as John
    And I am logged in as the admin
    And I have opened the Talk app
    And I create a one-to-one conversation with "user0"
    And I see that the chat is shown in the main view
    And I send a new chat message with the text "Hello"
    And I send a new chat message with the text "World"
    And I send a new chat message with the text "How is it going?"
    When I act as Jane
    And I am logged in
    And I have opened the Talk app
    And I open the "admin" conversation
    And I see that the chat is shown in the main view
    Then I see that the message 1 was sent by "admin" with the text "Hello"
    And I see that the message 2 was sent with the text "World" and grouped with the previous one
    And I see that the message 3 was sent with the text "How is it going?" and grouped with the previous one

  Scenario: receive a message from another user when the conversation is already open
    Given I act as John
    And I am logged in as the admin
    And I have opened the Talk app
    And I create a one-to-one conversation with "user0"
    And I see that the chat is shown in the main view
    And I act as Jane
    And I am logged in
    And I have opened the Talk app
    And I open the "admin" conversation
    And I see that the chat is shown in the main view
    When I act as John
    And I send a new chat message with the text "Hello"
    Then I act as Jane
    And I see that the message 1 was sent by "admin" with the text "Hello"

  Scenario: receive several messages from another user when the conversation is already open
    Given I act as John
    And I am logged in as the admin
    And I have opened the Talk app
    And I create a one-to-one conversation with "user0"
    And I see that the chat is shown in the main view
    And I act as Jane
    And I am logged in
    And I have opened the Talk app
    And I open the "admin" conversation
    And I see that the chat is shown in the main view
    When I act as John
    And I send a new chat message with the text "Hello"
    And I send a new chat message with the text "World"
    And I send a new chat message with the text "How is it going?"
    Then I act as Jane
    And I see that the message 1 was sent by "admin" with the text "Hello"
    And I see that the message 2 was sent with the text "World" and grouped with the previous one
    And I see that the message 3 was sent with the text "How is it going?" and grouped with the previous one

  Scenario: two users sending chat messages
    Given I act as John
    And I am logged in as the admin
    And I have opened the Talk app
    And I create a one-to-one conversation with "user0"
    And I see that the chat is shown in the main view
    And I act as Jane
    And I am logged in
    And I have opened the Talk app
    And I open the "admin" conversation
    And I see that the chat is shown in the main view
    When I act as John
    And I send a new chat message with the text "Hello"
    And I act as Jane
    And I see that the message 1 was sent by "admin" with the text "Hello"
    And I send a new chat message with the text "Hi!"
    And I act as John
    And I see that the message 2 was sent by "user0" with the text "Hi!"
    And I send a new chat message with the text "How are you?"
    And I act as Jane
    And I see that the message 3 was sent by "admin" with the text "How are you?"
    And I send a new chat message with the text "Fine thanks"
    And I send a new chat message with the text "And you?"
    And I act as John
    And I see that the message 5 was sent with the text "And you?" and grouped with the previous one
    And I send a new chat message with the text "Fine too!"
    Then I see that the message 1 was sent by "admin" with the text "Hello"
    And I see that the message 2 was sent by "user0" with the text "Hi!"
    And I see that the message 3 was sent by "admin" with the text "How are you?"
    And I see that the message 4 was sent by "user0" with the text "Fine thanks"
    And I see that the message 5 was sent with the text "And you?" and grouped with the previous one
    And I see that the message 6 was sent by "admin" with the text "Fine too!"
    And I act as Jane
    And I see that the message 1 was sent by "admin" with the text "Hello"
    And I see that the message 2 was sent by "user0" with the text "Hi!"
    And I see that the message 3 was sent by "admin" with the text "How are you?"
    And I see that the message 4 was sent by "user0" with the text "Fine thanks"
    And I see that the message 5 was sent with the text "And you?" and grouped with the previous one
    And I see that the message 6 was sent by "admin" with the text "Fine too!"

  Scenario: mention another user
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the chat is shown in the main view
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    And I see that the chat is shown in the main view
    When I act as John
    And I send a new chat message with the text "Hello @admin"
    Then I see that the message 1 was sent by "user0" with the text "Hello admin"
    And I see that the message 1 contains a formatted mention of "admin"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello admin"
    And I see that the message 1 contains a formatted mention of "admin" as current user

  Scenario: mention another user from the candidate mentions
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    And I see that the chat is shown in the main view
    And I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    And I see that the chat is shown in the main view
    When I act as John
    And I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "admin"
    And I send the current chat message
    Then I see that the message 1 was sent by "user0" with the text "Hello admin"
    And I see that the message 1 contains a formatted mention of "admin"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello admin"
    And I see that the message 1 contains a formatted mention of "admin" as current user

  Scenario: mention a guest from the candidate mentions
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I see that the chat is shown in the main view
    And I write down the public conversation link
    And I act as Jane
    And I visit the public conversation link I wrote down
    And I see that the current page is the public conversation link I wrote down
    And I see that the chat is shown in the main view
    And I set my guest name to "Rob"
    When I act as John
    And I see that "Rob" is shown in the list of participants as a normal participant
    And I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "Rob"
    And I send the current chat message
    # The generated avatar is plain text, so it appears in the message itself
    Then I see that the message 1 was sent by "user0" with the text "Hello RRob"
    And I see that the message 1 contains a formatted mention of "Rob"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello RRob"
    And I see that the message 1 contains a formatted mention of "Rob" as current user

  Scenario: mention all users in a public room from the candidate mentions
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I see that the chat is shown in the main view
    And I write down the public conversation link
    And I act as Jane
    And I visit the public conversation link I wrote down
    And I see that the current page is the public conversation link I wrote down
    And I see that the chat is shown in the main view
    When I act as John
    And I type a new chat message with the text "Hello @"
    And I choose the candidate mention for "Public"
    And I send the current chat message
    Then I see that the message 1 was sent by "user0" with the text "Hello Public"
    And I see that the message 1 contains a formatted mention of all participants of "Public"
    And I act as Jane
    And I see that the message 1 was sent by "user0" with the text "Hello Public"
    And I see that the message 1 contains a formatted mention of all participants of "Public"

  Scenario: mention another user and a URL
    Given I am logged in
    And I have opened the Talk app
    And I create a group conversation named "Group"
    And I see that the chat is shown in the main view
    When I send a new chat message with the text "Hello @admin, check http://www.nextcloud.com"
    # As the message contains child HTML elements (due to the contacts menu for
    # the mention) a whitespace appears after the mention when the message is
    # converted to plain text; it does not appear when the message is rendered
    # by browsers, it is just an artefact from the conversion algorithm of the
    # underlying libraries used by the tests.
    Then I see that the message 1 was sent by "user0" with the text "Hello admin , check http://www.nextcloud.com"
    And I see that the message 1 contains a formatted mention of "admin"
    And I see that the message 1 contains a formatted link to "http://www.nextcloud.com"
