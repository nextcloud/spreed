Feature: federation/ocm
  Background:
    Given user "participant1" exists

  Scenario: Check that the OCM resource is not registered when federation is disabled
    Given the following "spreed" app config is set
      | federation_enabled | no |
    Then OCM provider does not have the following resource types
      | name      | shareTypes | protocols |
      | talk-room | ["user"]   | {"talk-v1":"/ocs/v2.php/apps/spreed/api/"} |

  Scenario: Check that the OCM resource is registered when federation is enabled
    Given the following "spreed" app config is set
      | federation_enabled | yes |
    Given OCM provider has the following resource types
      | name      | shareTypes | protocols |
      | talk-room | ["user"]   | {"talk-v1":"/ocs/v2.php/apps/spreed/api/"} |
