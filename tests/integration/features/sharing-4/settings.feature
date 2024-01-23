Feature: sharing-4/settings

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Do not allow setting a shared folder as attachment_folder
    Given user "participant1" creates folder "/test"
    When user "participant1" sets setting "attachment_folder" to "/test" with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>attachments=>folder" set to "/test"
    Given user "participant2" creates folder "/test-participant2"
    Given user "participant2" shares "/test-participant2" with user "participant1" with OCS 100
    When user "participant1" sets setting "attachment_folder" to "/test-participant2" with 400 (v1)
    Then user "participant1" has capability "spreed=>config=>attachments=>folder" set to "/test"
