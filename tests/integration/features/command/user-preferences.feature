Feature: command/user-preferences

  Background:
    Given user "participant1" exists

  Scenario: User sets preferences via API
    When user "participant1" sets setting "play_sounds" to "false" with 200 (v1)
    When user "participant1" sets setting "play_sounds" to "no" with 200 (v1)
    When user "participant1" sets setting "play_sounds" to 1 with 200 (v1)
    When user "participant1" sets setting "play_sounds" to "on" with 400 (v1)
    When user "participant1" sets setting "chat_style" to "unified" with 200 (v1)
    When user "participant1" sets setting "chat_style" to "random" with 400 (v1)
    When user "participant1" sets setting "note_to_self" to "42" with 400 (v1)
    When user "participant1" sets setting "does_not_exist" to "yes" with 400 (v1)
