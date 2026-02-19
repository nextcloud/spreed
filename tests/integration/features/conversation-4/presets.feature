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
      | voiceroom    | Voice room   | {"listable":1,"messageExpiration":3600} |
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
      | voiceroom    | Voice room   | {"listable":1,"messageExpiration":3600} |

  Scenario: Create a voice room with preset values
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | listable | 1 |
      | messageExpiration | 3600 |
      | preset | voiceroom |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | attributes | messageExpiration | listable |
      | room | 3    | 1               | 1          | 3600              | 1        |

  Scenario: Create a voice room with overriding default values of the preset
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | listable | 0 |
      | messageExpiration | 1800 |
      | preset | voiceroom |
    Then user "participant1" is participant of the following rooms (v4)
      | id   | type | participantType | attributes | messageExpiration | listable |
      | room | 3    | 1               | 1          | 1800              | 0        |
