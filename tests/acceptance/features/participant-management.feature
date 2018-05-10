Feature: participant-management

  Scenario: create a one-to-one conversation
    Given I am logged in
    And I have opened the Talk app
    When I create a one-to-one conversation with "admin"
    Then I see that the sidebar is open
    And I see that I can not add new participants
    And I see that the number of participants shown in the list is "2"
    And I see that "user0" is shown in the list of participants as a moderator
    And I see that I can not moderate "user0"
    And I see that "admin" is shown in the list of participants as a moderator
    And I see that I can not moderate "admin"

  Scenario: open a one-to-one conversation as the other moderator
    Given I act as John
    And I am logged in
    And I have opened the Talk app
    And I create a one-to-one conversation with "admin"
    When I act as Jane
    And I am logged in as the admin
    And I have opened the Talk app
    And I open the "user0" conversation
    Then I see that the "user0" conversation is active
    And I see that the sidebar is open
    And I see that I can not add new participants
    And I see that the number of participants shown in the list is "2"
    And I see that "user0" is shown in the list of participants as a moderator
    And I see that I can not moderate "user0"
    And I see that "admin" is shown in the list of participants as a moderator
    And I see that I can not moderate "admin"
