Feature: chat/notifications

  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: Created a one-to-one room
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                                        |
      | spreed | room        | one-to-one room | participant1-displayname invited you to a private conversation |

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
      | app    | object_type | object_id       | subject                                             |
      | spreed | chat        | one-to-one room | participant1-displayname sent you a private message |

  Scenario: Mention when recipient is online in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                                          |
      | spreed | chat        | one-to-one room | participant1-displayname mentioned you in a private conversation |

  Scenario: Mention when recipient is offline in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                                          |
      | spreed | chat        | one-to-one room | participant1-displayname mentioned you in a private conversation |

  Scenario: At-all when recipient is online in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                                          |
      | spreed | chat        | one-to-one room | participant1-displayname mentioned you in a private conversation |

  Scenario: At-all when recipient is offline in the one-to-one
    When user "participant1" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant2 |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "one-to-one room" with 200 (v4)
    Given user "participant2" leaves room "one-to-one room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "one-to-one room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                                          |
      | spreed | chat        | one-to-one room | participant1-displayname mentioned you in a private conversation |

  Scenario: Created a group room and invite
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id | subject                                                            |
      | spreed | room        | room      | participant1-displayname invited you to a group conversation: room |
    Given user "participant2" joins room "room" with 200 (v4)
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

  Scenario: Mention when recipient is online in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    When user "participant1" sends message "Hi @participant2 bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                          |
      | spreed | chat        | room | participant1-displayname mentioned you in conversation room |

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
      | app    | object_type | object_id       | subject                                          |
      | spreed | chat        | room | participant1-displayname mentioned you in conversation room |

  Scenario: At-all when recipient is online in the group room
    When user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds user "participant2" to room "room" with 200 (v4)
    Given user "participant2" joins room "room" with 200 (v4)
    When user "participant1" sends message "Hi @all bye" to room "room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id       | subject                                          |
      | spreed | chat        | room | participant1-displayname mentioned you in conversation room |

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
      | app    | object_type | object_id       | subject                                          |
      | spreed | chat        | room | participant1-displayname mentioned you in conversation room |

# FIXME
# Disable notifications
# Enable full notification
# Share object
# Share file
