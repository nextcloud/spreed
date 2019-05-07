Feature: conversation

  Scenario: join a public conversation
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a public conversation named "Public"
    And I write down the public conversation link
    When I act as Jane
    And I visit the public conversation link I wrote down
    Then I see that the current page is the public conversation link I wrote down
