Feature: integration/outdated-client
  Background:
    Given user "participant1" exists

  Scenario: Check if outdated clients correctly receive a 426 error
    Given as user "participant1"
    # Android
    When client "Mozilla/5.0 (Android) Nextcloud-Talk v14.1.1" requests room list with 426 (v4)
    Then last response body contains "15.0.0"
    When client "Mozilla/5.0 (Android) Nextcloud-Talk v17.0.0" requests room list with 200 (v4)
    # iOS
    When client "Mozilla/5.0 (iOS) Nextcloud-Talk v14.1.1" requests room list with 426 (v4)
    Then last response body contains "15.0.0"
    When client "Mozilla/5.0 (iOS) Nextcloud-Talk v17.0.0" requests room list with 200 (v4)
    # Desktop
    When client "Mozilla/5.0 (Linux) Nextcloud-Talk v0.3.2" requests room list with 426 (v4)
    Then last response body contains "1.0.0"
    When client "Mozilla/5.0 (Linux) Nextcloud-Talk v1.0.0" requests room list with 200 (v4)
    When client "Mozilla/5.0 (Mac) Nextcloud-Talk v0.3.2" requests room list with 426 (v4)
    Then last response body contains "1.0.0"
    When client "Mozilla/5.0 (Mac) Nextcloud-Talk 1.0.0" requests room list with 200 (v4)
    When client "Mozilla/5.0 (Windows) Nextcloud-Talk v0.3.2" requests room list with 426 (v4)
    Then last response body contains "1.0.0"
    When client "Mozilla/5.0 (Windows) Nextcloud-Talk v1.0.0" requests room list with 200 (v4)

  Scenario: Check if outdated clients correctly receive a 426 error with recording consent enabled
    Given as user "participant1"
    Given recording server is started
    And the following "spreed" app config is set
      | recording_consent | 2 |
    # Android
    When client "Mozilla/5.0 (Android) Nextcloud-Talk v17.0.0" requests room list with 426 (v4)
    Then last response body contains "18.0.0"
    When client "Mozilla/5.0 (Android) Nextcloud-Talk v18.0.0" requests room list with 200 (v4)
    # iOS
    When client "Mozilla/5.0 (iOS) Nextcloud-Talk v17.0.0" requests room list with 426 (v4)
    Then last response body contains "18.0.0"
    When client "Mozilla/5.0 (iOS) Nextcloud-Talk v18.0.0" requests room list with 200 (v4)
    # Desktop
    When client "Mozilla/5.0 (Linux) Nextcloud-Talk v0.8.0" requests room list with 426 (v4)
    Then last response body contains "1.0.0"
    When client "Mozilla/5.0 (Linux) Nextcloud-Talk v1.0.0" requests room list with 200 (v4)
    When client "Mozilla/5.0 (Mac) Nextcloud-Talk v0.8.0" requests room list with 426 (v4)
    Then last response body contains "1.0.0"
    When client "Mozilla/5.0 (Mac) Nextcloud-Talk v1.0.0" requests room list with 200 (v4)
    When client "Mozilla/5.0 (Windows) Nextcloud-Talk v0.8.0" requests room list with 426 (v4)
    Then last response body contains "1.0.0"
    When client "Mozilla/5.0 (Windows) Nextcloud-Talk v1.0.0" requests room list with 200 (v4)
