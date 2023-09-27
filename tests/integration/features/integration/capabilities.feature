Feature: integration/dashboard
  Background:
    Given user "participant1" exists

  Scenario: Check that users can read the capabilities as XML
    Given as user "participant1"
    When sending "GET" to "/cloud/capabilities" for xml with
    Then last response body contains "<predefined-backgrounds>\n       <element>1_office.jpg</element>" with newlines
