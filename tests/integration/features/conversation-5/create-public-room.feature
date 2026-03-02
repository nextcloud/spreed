Feature: conversation-5/create-public-room

  Background:
    Given user "participant1" exists

  Scenario: Create public room without password when enforcement is off
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |

  Scenario: Create public room with password when enforcement is off
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | password | ARoomPassword123. |

  Scenario: Create public room without password when enforcement is on
    Given the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "room" with 400 (v4)
      | roomType | 3 |
      | roomName | room |

  Scenario: Create public room with password when enforcement is on
    Given the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | password | ARoomPassword123. |

  Scenario: Create non-public room without password when enforcement is on
    Given the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |

  Scenario: Create one-to-one room when enforcement is on
    Given user "participant2" exists
    And the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |

  Scenario: Create public room with policy-compliant password
    Given password policy app is enabled
    And the following "password_policy" app config is set
      | minLength | 10 |
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | password | ARoomPassword123. |

  Scenario: Create public room with policy-violating password (too short)
    Given password policy app is enabled
    And the following "password_policy" app config is set
      | minLength | 10 |
    When user "participant1" creates room "room" with 400 (v4)
      | roomType | 3 |
      | roomName | room |
      | password | abc |

  Scenario: Create public room without password when policy app is enabled but enforcement is off
    Given password policy app is enabled
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |

  Scenario: Create public room without password when policy app is enabled and enforcement is on
    Given password policy app is enabled
    And the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "room" with 400 (v4)
      | roomType | 3 |
      | roomName | room |

  Scenario: Create public room with policy-violating password when enforcement is on
    Given password policy app is enabled
    And the following "password_policy" app config is set
      | minLength | 10 |
    And the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "room" with 400 (v4)
      | roomType | 3 |
      | roomName | room |
      | password | abc |

  Scenario: Create public room with policy-compliant password when enforcement is on
    Given password policy app is enabled
    And the following "password_policy" app config is set
      | minLength | 10 |
    And the following "spreed" app config is set
      | force_passwords | yes |
    When user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
      | password | ARoomPassword123. |
