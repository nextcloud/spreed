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
    Then last response body contains "0.6.0"
    When client "Mozilla/5.0 (Linux) Nextcloud-Talk v0.6.0" requests room list with 200 (v4)
    When client "Mozilla/5.0 (Mac) Nextcloud-Talk v0.3.2" requests room list with 426 (v4)
    Then last response body contains "0.6.0"
    When client "Mozilla/5.0 (Mac) Nextcloud-Talk v0.6.0" requests room list with 200 (v4)
    When client "Mozilla/5.0 (Windows) Nextcloud-Talk v0.3.2" requests room list with 426 (v4)
    Then last response body contains "0.6.0"
    When client "Mozilla/5.0 (Windows) Nextcloud-Talk v0.6.0" requests room list with 200 (v4)
