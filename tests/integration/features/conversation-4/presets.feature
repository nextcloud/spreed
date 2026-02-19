Feature: conversation-4/presets

  Background:
    Given user "participant1" exists

  Scenario: Get list of presets
    Given user "participant1" gets available presets with 200 (v1)
      | identifier   | name         | parameters |
      | default      | default      | {"roomType":2,"readOnly":0,"listable":0,"messageExpiration":0,"lobbyState":0,"sipEnabled":0,"permissions":0,"recordingConsent":0,"mentionPermissions":0} |
      | forced       | forced       | []         |
      | webinar      | Webinar      | {"lobbyState":1,"mentionPermissions":1,"permissions":389,"recordingConsent":1,"roomType":3} |
      | presentation | Presentation | {"mentionPermissions":1,"permissions":389,"recordingConsent":1} |
      | hallway      | Hallway      | {"listable":1,"messageExpiration":3600} |
    And the following "spreed" app config is set
      | force_listable            | 0 |
      | force_message_expiration  | 7200 |
      | force_sip_enabled         | 1 |
      | default_recording_consent | 2 |
    Given user "participant1" gets available presets with 200 (v1)
      | identifier   | name         | parameters |
      | default      | default      | {"roomType":2,"readOnly":0,"listable":0,"messageExpiration":0,"lobbyState":0,"sipEnabled":0,"permissions":0,"recordingConsent":2,"mentionPermissions":0} |
      | forced       | forced       | {"listable":0,"messageExpiration":7200,"sipEnabled":1} |
      | webinar      | Webinar      | {"lobbyState":1,"mentionPermissions":1,"permissions":389,"recordingConsent":1,"roomType":3} |
      | presentation | Presentation | {"mentionPermissions":1,"permissions":389,"recordingConsent":1} |
      | hallway      | Hallway      | {"listable":1,"messageExpiration":3600} |
