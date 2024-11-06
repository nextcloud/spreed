Feature: chat-2/typing-privacy
  Background:
    Given user "participant1" exists
  Scenario: User toggles the typing privacy
    # Private
    When user "participant1" sets setting "typing_privacy" to 1 with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>typing-privacy" set to 1

    # Public
    When user "participant1" sets setting "typing_privacy" to 0 with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>typing-privacy" set to 0
