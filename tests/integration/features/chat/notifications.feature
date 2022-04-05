Feature: chat/notifications

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Normal message when recipient is online in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Normal message when recipient is offline in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                 | subject                                             |
      | spreed | chat        | one-to-one room/Message 1 | participant1-displayname sent you a private message |

  Scenario: Normal message when recipient disabled notifications in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    And user "participant2" sets notifications to disabled for room "one-to-one room" (v4)
    When user "participant1" sends message "Message 1" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Mention when recipient is online in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                            | subject                                                          |
      | spreed | chat        | one-to-one room/Hi @participant2 bye | participant1-displayname mentioned you in a private conversation |

  Scenario: Mention when recipient is offline in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                            | subject                                                          |
      | spreed | chat        | one-to-one room/Hi @participant2 bye | participant1-displayname mentioned you in a private conversation |

  Scenario: Mention when recipient disabled notifications in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    And user "participant2" sets notifications to disabled for room "one-to-one room" (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: At-all when recipient is online in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                   | subject                                                          |
      | spreed | chat        | one-to-one room/Hi @all bye | participant1-displayname mentioned you in a private conversation |

  Scenario: At-all when recipient is offline in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                   | subject                                                          |
      | spreed | chat        | one-to-one room/Hi @all bye | participant1-displayname mentioned you in a private conversation |

  Scenario: At-all when recipient disabled notifications in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    And user "participant2" sets notifications to disabled for room "one-to-one room" (v4)
    When user "participant1" sends message "Hi @all bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Normal message when recipient is online in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Normal message when recipient is offline in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant2" leaves room "room" with 200 (v4)
    When user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: Normal message when recipient with all notifications in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant2" leaves room "room" with 200 (v4)
    And user "participant2" sets notifications to all for room "room" (v4)
    When user "participant1" sends message "Message 1" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id      | subject                                                      |
      | spreed | chat        | room/Message 1 | participant1-displayname sent a message in conversation room |

  Scenario: Mention when recipient is online in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                 | subject                                                     |
      | spreed | chat        | room/Hi @participant2 bye | participant1-displayname mentioned you in conversation room |

  Scenario: Mention when recipient is offline in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant2" leaves room "room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                 | subject                                                     |
      | spreed | chat        | room/Hi @participant2 bye | participant1-displayname mentioned you in conversation room |

  Scenario: Mention when recipient with disabled notifications in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant2" leaves room "room" with 200 (v4)
    And user "participant2" sets notifications to disabled for room "room" (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |

  Scenario: At-all when recipient is online in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id        | subject                                                     |
      | spreed | chat        | room/Hi @all bye | participant1-displayname mentioned you in conversation room |

  Scenario: At-all when recipient is offline in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant2" leaves room "room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id        | subject                                                     |
      | spreed | chat        | room/Hi @all bye | participant1-displayname mentioned you in conversation room |

  Scenario: At-all when recipient with disabled notifications in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "room" with 200 (v4)
    Given user "participant2" leaves room "room" with 200 (v4)
    And user "participant2" sets notifications to disabled for room "room" (v4)
    When user "participant1" sends message "Hi @all bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app | object_type | object_id | subject |
