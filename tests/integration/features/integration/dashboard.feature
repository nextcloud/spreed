Feature: integration/dashboard
  Background:
    Given user "participant1" exists
    Given user "participant2" exists

  Scenario: User gets the available dashboard widgets
    When user "participant1" sees the following entry when loading the list of dashboard widgets (v1)
      | id     | title         | icon_class          | icon_url         | widget_url                       | item_icons_round | order | buttons | item_api_versions | reload_interval |
      | spreed | Talk mentions | dashboard-talk-icon | img/app-dark.svg | {$BASE_URL}index.php/apps/spreed/ | true             | 10    | [{"type":"more","text":"More conversations","link":"{$BASE_URL}index.php/apps/spreed/"}] | [1,2] | 30 |

  Scenario: User gets the dashboard widget content
    When user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title | subtitle | link | iconUrl |
    Given user "participant2" creates room "one-to-one room" (v4)
      | roomType | 1 |
      | invite   | participant1 |
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
    Then user "participant1" sees the following entries for dashboard widgets "spreed" (v1)
      | title                    | subtitle            | link            | iconUrl                                                               | sinceId | overlayIconUrl |
      | call room                |  Call in progress   | call room       | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | group room               |  You were mentioned | group room      | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
      | participant2-displayname |  Hello              | one-to-one room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
