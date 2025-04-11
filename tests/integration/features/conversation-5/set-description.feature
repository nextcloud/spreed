Feature: conversation-2/set-description
  Background:
    Given user "owner" exists
    Given user "moderator" exists
    Given user "invited user" exists
    Given user "not invited user" exists
    Given user "not invited but joined user" exists
    Given user "not joined user" exists

  Scenario: a description of 2000 characters can be set
    Given signaling server is started
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And reset signaling server requests
    When user "owner" sets description for room "group room" to "012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C" with 200 (v4)
    Then signaling server received the following requests
      | token | data |
      | group room | {"type":"message","message":{"data":{"type":"chat","chat":{"refresh":true}}}} |
      | group room | {"type":"update","update":{"userids":["owner"],"properties":{"name":"Private conversation","type":2,"lobby-state":0,"lobby-timer":null,"read-only":0,"listable":0,"active-since":null,"sip-enabled":0,"description":"012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C"}}} |
    Then user "owner" is participant of room "group room" (v4)
      | description                                                                                                                                                                                              |
      | 012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C |

  Scenario: a description longer than 2000 characters can not be set
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" sets description for room "group room" to "the description" with 200 (v4)
    When user "owner" sets description for room "group room" to "012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678C1" with 400 (v4)
    Then user "owner" is participant of room "group room" (v4)
      | description     |
      | the description |

  Scenario: a description of 2000 multibyte characters can be set
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    When user "owner" sets description for room "group room" to "०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च" with 200 (v4)
    Then user "owner" is participant of room "group room" (v4)
      | description                                                                                                                                                                                              |
      | ०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च |

  Scenario: a description longer than 2000 multibyte characters can not be set
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" sets description for room "group room" to "the description" with 200 (v4)
    When user "owner" sets description for room "group room" to "०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८९०१२३४५६७८च1" with 400 (v4)
    Then user "owner" is participant of room "group room" (v4)
      | description     |
      | the description |



  Scenario: owner can not set description in one-to-one room
    Given user "owner" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | moderator |
    Given user "moderator" creates room "one-to-one room" with 200 (v4)
      | roomType | 1 |
      | invite   | owner |
    When user "owner" sets description for room "one-to-one room" to "the description" with 400 (v4)
    And user "moderator" sets description for room "one-to-one room" to "the description" with 400 (v4)
    Then user "owner" is participant of room "one-to-one room" (v4)
      | description |
      |             |
    And user "moderator" is participant of room "one-to-one room" (v4)
      | description |
      |             |



  Scenario: owner can set description in group room
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "group room" with 200 (v4)
    And user "owner" promotes "moderator" in room "group room" with 200 (v4)
    And user "owner" adds user "invited user" to room "group room" with 200 (v4)
    When user "owner" sets description for room "group room" to "the description" with 200 (v4)
    Then user "owner" is participant of room "group room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "group room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "group room" (v4)
      | description     |
      | the description |

  Scenario: moderator can set description in group room
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "group room" with 200 (v4)
    And user "owner" promotes "moderator" in room "group room" with 200 (v4)
    And user "owner" adds user "invited user" to room "group room" with 200 (v4)
    When user "moderator" sets description for room "group room" to "the description" with 200 (v4)
    Then user "owner" is participant of room "group room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "group room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "group room" (v4)
      | description     |
      | the description |

  Scenario: others can not set description in group room
    Given user "owner" creates room "group room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "group room" with 200 (v4)
    And user "owner" promotes "moderator" in room "group room" with 200 (v4)
    And user "owner" adds user "invited user" to room "group room" with 200 (v4)
    And user "owner" sets description for room "group room" to "the description" with 200 (v4)
    When user "invited user" sets description for room "group room" to "invited user description" with 403 (v4)
    And user "not invited user" sets description for room "group room" to "not invited user description" with 404 (v4)
    # Guest user names in tests must being with "guest"
    And user "guest not joined" sets description for room "group room" to "guest not joined description" with 404 (v4)
    Then user "owner" is participant of room "group room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "group room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "group room" (v4)
      | description     |
      | the description |



  Scenario: owner can set description in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    When user "owner" sets description for room "public room" to "the description" with 200 (v4)
    Then user "owner" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "not invited but joined user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest" is participant of room "public room" (v4)
      | description     |
      | the description |

  Scenario: moderator can set description in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    When user "moderator" sets description for room "public room" to "the description" with 200 (v4)
    Then user "owner" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "not invited but joined user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest" is participant of room "public room" (v4)
      | description     |
      | the description |

  Scenario: guest moderator can set description in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    When user "guest moderator" sets description for room "public room" to "the description" with 200 (v4)
    Then user "owner" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "not invited but joined user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest" is participant of room "public room" (v4)
      | description     |
      | the description |

  Scenario: others can not set description in public room
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    And user "guest moderator" joins room "public room" with 200 (v4)
    And user "owner" promotes "guest moderator" in room "public room" with 200 (v4)
    And user "guest" joins room "public room" with 200 (v4)
    And user "owner" sets description for room "public room" to "the description" with 200 (v4)
    When user "invited user" sets description for room "public room" to "invited user description" with 403 (v4)
    And user "not invited but joined user" sets description for room "public room" to "not invited but joined description" with 403 (v4)
    And user "not joined user" sets description for room "public room" to "not joined user description" with 404 (v4)
    And user "guest" sets description for room "public room" to "guest description" with 403 (v4)
    # Guest user names in tests must being with "guest"
    And user "guest not joined" sets description for room "public room" to "guest not joined description" with 404 (v4)
    Then user "owner" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "invited user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "not invited but joined user" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest moderator" is participant of room "public room" (v4)
      | description     |
      | the description |
    And user "guest" is participant of room "public room" (v4)
      | description     |
      | the description |



  Scenario: participants can not set description in room for a share
    # These users are only needed in very specific tests, so they are not
    # created in the background step.
    Given user "owner of file" exists
    And user "user with access to file" exists
    And user "owner of file" shares "welcome.txt" with user "user with access to file" with OCS 100
    And user "user with access to file" accepts last share
    And user "owner of file" shares "welcome.txt" by link with OCS 100
    And user "guest" gets the room for last share with 200 (v1)
    And user "owner of file" joins room "file last share room" with 200 (v4)
    And user "user with access to file" joins room "file last share room" with 200 (v4)
    And user "guest" joins room "file last share room" with 200 (v4)
    When user "owner of file" sets description for room "file last share room" to "owner of file description" with 403 (v4)
    And user "user with access to file" sets description for room "file last share room" to "user with access to file description" with 403 (v4)
    And user "guest" sets description for room "file last share room" to "guest description" with 403 (v4)
    Then user "owner of file" is participant of room "file last share room" (v4)
      | description |
      |             |
    And user "user with access to file" is participant of room "file last share room" (v4)
      | description |
      |             |
    And user "guest" is participant of room "file last share room" (v4)
      | description |
      |             |



  Scenario: owner can set description in a password request room
    # The user is only needed in very specific tests, so it is not created in
    # the background step.
    Given user "owner of file" exists
    And user "owner of file" shares "welcome.txt" by link with OCS 100
      | password | 123456 |
      | sendPasswordByTalk | true |
    And user "guest" creates the password request room for last share with 201 (v1)
    And user "guest" joins room "password request for last share room" with 200 (v4)
    And user "owner of file" joins room "password request for last share room" with 200 (v4)
    When user "owner of file" sets description for room "password request for last share room" to "the description" with 200 (v4)
    Then user "owner of file" is participant of room "password request for last share room" (v4)
      | description     |
      | the description |
    And user "guest" is participant of room "password request for last share room" (v4)
      | description     |
      | the description |



  Scenario: room list returns the description if the description is set
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    When user "owner" sets description for room "public room" to "the description" with 200 (v4)
    Then user "owner" is participant of the following rooms (v4)
      | name | description     |
      | room | the description |
    And user "moderator" is participant of the following rooms (v4)
      | name | description     |
      | room | the description |
    And user "invited user" is participant of the following rooms (v4)
      | name | description     |
      | room | the description |
    And user "not invited but joined user" is participant of the following rooms (v4)
      | name | description     |
      | room | the description |

  Scenario: room list returns an empty value if the description is not set
    Given user "owner" creates room "public room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "owner" adds user "moderator" to room "public room" with 200 (v4)
    And user "owner" promotes "moderator" in room "public room" with 200 (v4)
    And user "owner" adds user "invited user" to room "public room" with 200 (v4)
    And user "not invited but joined user" joins room "public room" with 200 (v4)
    When user "owner" sets description for room "public room" to "" with 200 (v4)
    Then user "owner" is participant of the following rooms (v4)
      | name | description |
      | room |             |
    And user "moderator" is participant of the following rooms (v4)
      | name | description |
      | room |             |
    And user "invited user" is participant of the following rooms (v4)
      | name | description |
      | room |             |
    And user "not invited but joined user" is participant of the following rooms (v4)
      | name | description |
      | room |             |
