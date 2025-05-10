Feature: integration/dashboard-server
  Background:
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists

  Scenario: User gets the available dashboard widgets
    When user "participant1" sees the following entry when loading the list of dashboard widgets (v1)
      | id     | title         | icon_class          | icon_url         | widget_url                       | item_icons_round | order | buttons | item_api_versions | reload_interval |
      | spreed | Talk mentions | dashboard-talk-icon | img/app-dark.svg | {$BASE_URL}index.php/apps/spreed/ | true             | 10    | [{"type":"more","text":"More conversations","link":"{$BASE_URL}index.php/apps/spreed/"}] | [1,2] | 30 |

  Scenario: User gets the dashboard widget content
    When user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title | subtitle | link | iconUrl |
    When user "participant1" sees the following entries for dashboard widgets "spreed" (v2)
      | title | subtitle | link | iconUrl |
    Given user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
    Given user "participant1" creates room "former one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant3 |
    And user "participant3" is deleted
    And user "participant2" sends message "Hello" to room "one-to-one room" with 201
    And wait for 1 second
    Given user "participant2" creates room "group room" (v4)
      | roomType | 2          |
      | roomName | group room |
    And user "participant2" adds user "participant1" to room "group room" with 200 (v4)
    And user "participant2" sends message "Hello @all" to room "group room" with 201
    And wait for 1 second
    Given user "participant2" creates room "call room" (v4)
      | roomType | 3         |
      | roomName | call room |
    And user "participant2" adds user "participant1" to room "call room" with 200 (v4)
    And user "participant2" joins room "call room" with 200 (v4)
    And user "participant2" joins call "call room" with 200 (v4)
    Given user "participant2" creates room "breakout room parent" (v4)
      | roomType | 2         |
      | roomName | call room |
    And user "participant2" adds user "participant1" to room "breakout room parent" with 200 (v4)
    When user "participant2" creates 2 automatic breakout rooms for "breakout room parent" with 200 (v1)
    And user "participant2" starts breakout rooms in room "breakout room parent" with 200 (v1)
    And user "participant2" broadcasts message "@participant1 hello" to room "breakout room parent" with 201 (v1)
    And wait for 1 second
    Given user "participant2" creates room "lobby room" (v4)
      | roomType | 2          |
      | roomName | lobby room |
    When user "participant2" sets lobby state for room "lobby room" to "non moderators" with 200 (v4)
    And user "participant2" adds user "participant1" to room "lobby room" with 200 (v4)
    And user "participant2" sends message "Lobby @all" to room "lobby room" with 201
    Given user "participant2" creates room "lobby room with bypass" (v4)
      | roomType | 2                      |
      | roomName | lobby room with bypass |
    When user "participant2" sets lobby state for room "lobby room with bypass" to "non moderators" with 200 (v4)
    And user "participant2" adds user "participant1" to room "lobby room with bypass" with 200 (v4)
    And user "participant2" sets permissions for "participant1" in room "lobby room with bypass" to "L" with 200 (v4)
    And user "participant2" adds user "participant1" to room "lobby room with bypass" with 200 (v4)
    And user "participant2" sends message "Lobby @all but with bypass" to room "lobby room with bypass" with 201
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                | Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname | Hello              | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v2)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                | Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname | Hello              | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    And user "participant1" archives room "one-to-one room" with 200 (v4)
    And user "participant1" archives room "group room" with 200 (v4)
    And user "participant1" archives room "call room" with 200 (v4)
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname | Hello              | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v2)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname | Hello              | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    And user "participant1" unarchives room "one-to-one room" with 200 (v4)
    And user "participant1" unarchives room "group room" with 200 (v4)
    And user "participant1" unarchives room "call room" with 200 (v4)
    And user "participant1" marks room "one-to-one room" as sensitive with 200 (v4)
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                | Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname |                    | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v2)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                | Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname |                    | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    And user "participant1" marks room "one-to-one room" as insensitive with 200 (v4)
    And user "participant2" set the message expiration to 3 of room "one-to-one room" with 200 (v4)
    And user "participant2" sends message "Message 3" to room "one-to-one room" with 201
    And user "participant2" set the message expiration to 3 of room "group room" with 200 (v4)
    And user "participant2" sends message "Message 2" to room "group room" with 201
    And user "participant2" set the message expiration to 3 of room "call room" with 200 (v4)
    And user "participant2" sends message "Message 3" to room "call room" with 201
    And wait for 3 seconds
    And force run "OCA\Talk\BackgroundJob\ExpireChatMessages" background jobs
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                | Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname |                    | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v2)
      | title                    | subtitle           | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                | Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               | You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname |                    | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | lobby room with bypass   | You were mentioned | lobby room with bypass | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
