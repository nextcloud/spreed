Feature: integration/dashboard
  Background:
    Given user "participant1" exists

  Scenario: User gets the available dashboard widgets
    Given as user "participant1"
    When sending "GET" to "/cloud/capabilities" for xml with
    Then last response body contains "<toLabel>Lorem ipsum</toLabel>"
