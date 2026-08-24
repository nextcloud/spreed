Feature: integration/navigation
  Background:
    Given user "participant1" exists
    Given group "group1" exists

  Scenario: Talk is listed in the navigation when the user is allowed to use Talk
    Then user "participant1" has the "spreed" navigation entry
      | name | Talk                              |
      | href | {$BASE_URL}index.php/apps/spreed/ |
      | icon | spreed/img/app.svg                |
      | type | link                              |

  Scenario: Talk is not listed in the navigation when the user is not allowed to use Talk
    Given the following "spreed" app config is set
      | allowed_groups | ["group1"] |
    Then user "participant1" does not have the "spreed" navigation entry
    Given user "participant1" is member of group "group1"
    Then user "participant1" has the "spreed" navigation entry
      | name | Talk                              |
      | href | {$BASE_URL}index.php/apps/spreed/ |
      | icon | spreed/img/app.svg                |
      | type | link                              |
