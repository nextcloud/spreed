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
