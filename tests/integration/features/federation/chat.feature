Feature: federation/chat
  Background:
    Given using server "REMOTE"
    And user "participant2" exists
    And user "participant3" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |
    And using server "LOCAL"
    Given user "participant1" exists
    Given user "participant2" exists
    Given user "participant3" exists
    And the following "spreed" app config is set
      | federation_enabled | yes |

  Scenario: Get mention suggestions (translating local users to federated users)
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    And using server "LOCAL"
    And user "participant1" gets the following candidate mentions in room "room" for "" with 200
      | source          | id                               | label                    | mentionId                                 |
      | calls           | all                              | room                     | all                                       |
      | federated_users | participant2@{$REMOTE_URL}       | participant2-displayname | federated_user/participant2@{$REMOTE_URL} |
      | users           | participant1                     | participant1-displayname | participant1                              |
      | users           | participant3                     | participant3-displayname | participant3                              |
    Given using server "REMOTE"
    And user "participant2" gets the following candidate mentions in room "LOCAL::room" for "" with 200
      | source          | id                        | label                    | mentionId    |
      | calls           | all                       | room                     | all          |
      | federated_users | participant1@{$LOCAL_URL} | participant1-displayname | participant1 |
      | federated_users | participant3@{$LOCAL_URL} | participant3-displayname | participant3 |
      | users           | participant2              | participant2-displayname | federated_user/participant2@{$REMOTE_URL} |

  Scenario: Get mention suggestions (translating federated users of the same server to local users)
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    Given using server "LOCAL"
    And user "participant1" gets the following candidate mentions in room "room" for "" with 200
      | source          | id                         | label                    | mentionId                                 |
      | calls           | all                        | room                     | all                                       |
      | users           | participant1               | participant1-displayname | participant1                              |
      | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | federated_user/participant2@{$REMOTE_URL} |
      | federated_users | participant3@{$REMOTE_URL} | participant3-displayname | federated_user/participant3@{$REMOTE_URL} |
    Given using server "REMOTE"
    And user "participant2" gets the following candidate mentions in room "LOCAL::room" for "" with 200
      | source          | id                        | label                    | mentionId                                 |
      | calls           | all                       | room                     | all                                       |
      | federated_users | participant1@{$LOCAL_URL} | participant1-displayname | participant1                              |
      | users           | participant3              | participant3-displayname | federated_user/participant3@{$REMOTE_URL} |
      | users           | participant2              | participant2-displayname | federated_user/participant2@{$REMOTE_URL} |

  Scenario: Basic chatting including posting, getting, editing and deleting
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastMessage |
      | LOCAL::room | 2    | {federated_user} accepted the invitation |
    Given using server "LOCAL"
    And user "participant1" sends message "Message 1" to room "room" with 201
    Given using server "REMOTE"
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastMessage |
      | LOCAL::room | 2    | Message 1 |
    When user "participant2" sends reply "Message 1-1" on message "Message 1" to room "LOCAL::room" with 201
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastMessage |
      | LOCAL::room | 2    | Message 1-1 |
    Given using server "LOCAL"
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType       | actorId                    | actorDisplayName         | message     | messageParameters | parentMessage |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message 1-1 | []                | Message 1     |
      | room | users           | participant1               | participant1-displayname | Message 1   | []                |               |
    Given using server "REMOTE"
    When next message request has the following parameters set
      | timeout                  | 0         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room        | actorType       | actorId                   | actorDisplayName         | message     | messageParameters | parentMessage |
      | LOCAL::room | users           | participant2              | participant2-displayname | Message 1-1 | []                | Message 1     |
      | LOCAL::room | federated_users | participant1@{$LOCAL_URL} | participant1-displayname | Message 1   | []                |               |
    Given using server "LOCAL"
    And user "participant1" edits message "Message 1" in room "room" to "Message 1 - Edit 1" with 200
    Given using server "REMOTE"
    And user "participant2" edits message "Message 1-1" in room "LOCAL::room" to "Message 1-1 - Edit 1" with 200
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastMessage |
      | LOCAL::room | 2    | Message 1-1 - Edit 1 |
    Given using server "LOCAL"
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType       | actorId                    | actorDisplayName         | message              | messageParameters | parentMessage      | lastEditActorType | lastEditActorId            | lastEditActorDisplayName |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message 1-1 - Edit 1 | []                | Message 1 - Edit 1 | federated_users   | participant2@{$REMOTE_URL} | participant2-displayname |
      | room | users           | participant1               | participant1-displayname | Message 1 - Edit 1   | []                |                    | users             | participant1               | participant1-displayname |
    Given using server "REMOTE"
    When next message request has the following parameters set
      | timeout                  | 0         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room        | actorType       | actorId                   | actorDisplayName         | message              | messageParameters | parentMessage      | lastEditActorType | lastEditActorId           | lastEditActorDisplayName |
      | LOCAL::room | users           | participant2              | participant2-displayname | Message 1-1 - Edit 1 | []                | Message 1 - Edit 1 | users             | participant2              | participant2-displayname |
      | LOCAL::room | federated_users | participant1@{$LOCAL_URL} | participant1-displayname | Message 1 - Edit 1   | []                |                    | federated_users   | participant1@{$LOCAL_URL} | participant1-displayname |
    Given using server "LOCAL"
    And user "participant1" deletes message "Message 1 - Edit 1" from room "room" with 200
    Given using server "REMOTE"
    And user "participant2" deletes message "Message 1-1 - Edit 1" from room "LOCAL::room" with 200
    Given using server "LOCAL"
    Then user "participant1" sees the following messages in room "room" with 200
      | room | actorType       | actorId                    | actorDisplayName         | message                   | messageParameters | parentMessage          |
      | room | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","server":"{$REMOTE_URL}","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}} | Message deleted by you |
      | room | users           | participant1               | participant1-displayname | Message deleted by you    | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1"}}                          |                        |
    When next message request has the following parameters set
      | timeout                  | 0         |
    Given using server "REMOTE"
    And user "participant2" sees the following messages in room "LOCAL::room" with 200
      | room        | actorType       | actorId                   | actorDisplayName         | message                   | messageParameters | parentMessage             |
      | LOCAL::room | users           | participant2              | participant2-displayname | Message deleted by you    | {"actor":{"type":"user","id":"participant2","name":"participant2-displayname","mention-id":"federated_user\/participant2@{$REMOTE_URL}"}}                        | Message deleted by author |
      | LOCAL::room | federated_users | participant1@{$LOCAL_URL} | participant1-displayname | Message deleted by author | {"actor":{"type":"user","id":"participant1","name":"participant1-displayname","mention-id":"participant1","server":"{$LOCAL_URL}"}} |                           |
    # Disabled due to https://github.com/nextcloud/spreed/issues/12957
    # Then user "participant2" is participant of the following rooms (v4)
    # | id          | type | lastMessage |
    # | LOCAL::room | 2    | Message deleted by author |

  Scenario: Last message actor when the same user ID is present
    Given user "participant3" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant3" adds federated_user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant3@LOCAL | participant3-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant3" is participant of the following unordered rooms (v4)
      | id          | name | type | remoteServer | remoteToken | lastMessage |
      | LOCAL::room | room | 2    | LOCAL        | room        | {federated_user} accepted the invitation |
    Given using server "LOCAL"
    Then user "participant3" is participant of the following unordered rooms (v4)
      | id          | name | type | remoteServer | remoteToken | lastMessage |
      | room        | room | 2    |              |             | {federated_user} accepted the invitation |
    And user "participant3" sends message "Message 1" to room "room" with 201
    Then user "participant3" is participant of the following unordered rooms (v4)
      | id          | name | type | remoteServer | remoteToken | lastMessage | lastMessageActorType | lastMessageActorId |
      | room        | room | 2    |              |             | Message 1   | users                | participant3       |
    Given using server "REMOTE"
    Then user "participant3" is participant of the following unordered rooms (v4)
      | id          | name | type | remoteServer | remoteToken | lastMessage | lastMessageActorType | lastMessageActorId |
      | LOCAL::room | room | 2    | LOCAL        | room        | Message 1   | federated_users      | participant3@{$LOCAL_URL} |
    When user "participant3" sends reply "Message 1-1" on message "Message 1" to room "LOCAL::room" with 201
    Then user "participant3" is participant of the following unordered rooms (v4)
      | id          | name | type | remoteServer | remoteToken | lastMessage | lastMessageActorType | lastMessageActorId |
      | LOCAL::room | room | 2    | LOCAL        | room        | Message 1-1 | users                | participant3       |
    Given using server "LOCAL"
    Then user "participant3" is participant of the following unordered rooms (v4)
      | id          | name | type | remoteServer | remoteToken | lastMessage | lastMessageActorType | lastMessageActorId |
      | room        | room | 2    |              |             | Message 1-1 | federated_users      | participant3@{$REMOTE_URL} |

  Scenario: Read marker checking
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | LOCAL::room | 2    | UNKNOWN_MESSAGE      | 0              | 0             | 0                   |
    Given using server "LOCAL"
    And user "participant1" sends message "Message 1" to room "room" with 201
    And user "participant1" sends message "Message 2" to room "room" with 201
    Given using server "REMOTE"
    When user "participant2" marks room "LOCAL::room" as unread with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | LOCAL::room | 2    | Message 1            | 1              | 0             | 0                   |
    When user "participant2" reads message "NULL" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | LOCAL::room | 2    | Message 2            | 0              | 0             | 0                   |
    When user "participant2" reads message "Message 1" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | LOCAL::room | 2    | Message 1            | 1              | 0             | 0                   |
    When user "participant2" reads message "Message 2" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type | lastReadMessage      | unreadMessages | unreadMention | unreadMentionDirect |
      | LOCAL::room | 2    | Message 2            | 0              | 0             | 0                   |

  Scenario: Error handling of chatting (posting a too long message)
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    And user "participant2" sends message "413 Payload Too Large" to room "LOCAL::room" with 413

  Scenario: Mentioning a federated user triggers a notification for them
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2@REMOTE" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "LOCAL::room" with 201
    Then user "participant2" sees the following entries for dashboard widgets "spreed" (v1)
      | title | subtitle           | link        | iconUrl                                                               | sinceId | overlayIconUrl |
    Then user "participant2" sees the following entries for dashboard widgets "spreed" (v2)
      | title | subtitle           | link        | iconUrl                                                               | sinceId | overlayIconUrl |
      | room  | Message 1          | LOCAL::room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    When using server "LOCAL"
    When user "participant1" sends reply "Message 1-1" on message "Message 1" to room "room" with 201
    Then using server "REMOTE"
    Then user "participant2" sees the following entries for dashboard widgets "spreed" (v1)
      | title | subtitle           | link        | iconUrl                                                               | sinceId | overlayIconUrl |
      | room  | You were mentioned | LOCAL::room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    Then user "participant2" sees the following entries for dashboard widgets "spreed" (v2)
      | title | subtitle           | link        | iconUrl                                                               | sinceId | overlayIconUrl |
      | room  | You were mentioned | LOCAL::room | {$BASE_URL}ocs/v2.php/apps/spreed/api/v1/room/{token}/avatar{version} |         |                |
    And using server "LOCAL"
    And user "participant1" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "room" with 201
    And user "participant1" sends message 'Hi @all bye' to room "room" with 201
    Then using server "REMOTE"
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | LOCAL::room/Hi @all bye         | participant1-displayname mentioned everyone in conversation room       | Hi room bye |
      | spreed | chat        | LOCAL::room/Hi @"federated_user/participant2@{$REMOTE_URL}" bye | participant1-displayname mentioned you in conversation room | Hi @participant2-displayname bye |
      | spreed | chat        | LOCAL::room/Message 1-1         | participant1-displayname replied to your message in conversation room  | Message 1-1 |
    When next message request has the following parameters set
      | timeout                  | 0         |
      | lookIntoFuture           | 1         |
      | lastKnownMessageId       | Hi @all bye         |
    And user "participant2" sees the following messages in room "LOCAL::room" with 304
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |

  Scenario: Mentioning a federated user as a guest also triggers a notification for them
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "guest" joins room "room" with 200 (v4)
    When user "guest" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "room" with 201
    When user "guest" sends message "Message 2" to room "room" with 201
    Then using server "REMOTE"
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | LOCAL::room/Hi @"federated_user/participant2@{$REMOTE_URL}" bye | A guest mentioned you in conversation room | Hi @participant2-displayname bye |
    Then user "participant2" reads message "Message 2" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |

  Scenario: Mentioning a federated user with an active session does not trigger a notification but inactive does
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" sets session state to 1 in room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    And user "guest" joins room "room" with 200 (v4)
    When user "guest" sends message 'Sent to @"federated_user/participant2@{$REMOTE_URL}" while active' to room "room" with 201
    Given using server "REMOTE"
    Given user "participant2" sets session state to 0 in room "LOCAL::room" with 200 (v4)
    When using server "LOCAL"
    When user "guest" sends message 'User @"federated_user/participant2@{$REMOTE_URL}" is inactive' to room "room" with 201
    When user "guest" sends message "Message 3" to room "room" with 201
    Then using server "REMOTE"
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | LOCAL::room/User @"federated_user/participant2@{$REMOTE_URL}" is inactive | A guest mentioned you in conversation room | User @participant2-displayname is inactive |
    Then user "participant2" reads message "Message 3" in room "LOCAL::room" with 200 (v1)
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |

  Scenario: Mentioning a federated user as a federated user that is a local user to the mentioned one also triggers a notification for them
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    And using server "LOCAL"
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant3" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    When user "participant3" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "LOCAL::room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |
      | spreed | chat        | LOCAL::room/Hi @"federated_user/participant2@{$REMOTE_URL}" bye | participant3-displayname mentioned you in conversation room | Hi @participant2-displayname bye |

  Scenario: Mentioning and replying to self does not do notifications
    Given user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    And using server "LOCAL"
    And user "participant1" adds federated_user "participant3" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant3" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant3" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    Then user "participant3" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    # Join and leave to clear the invite notification
    Given using server "REMOTE"
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    When user "participant2" sends message 'Hi @"federated_user/participant2@{$REMOTE_URL}" bye' to room "LOCAL::room" with 201
    And user "participant2" sends message "Message 1" to room "LOCAL::room" with 201
    When user "participant2" sends reply "Message 1-1" on message "Message 1" to room "LOCAL::room" with 201
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |

  Scenario: System messages don't trigger notifications
    And user "participant1" creates room "room" (v4)
      | roomType | 3 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    Given using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 3    | LOCAL        | room        |
    And user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 3    |
    Given using server "LOCAL"
    And user "participant1" sends message "Message 1" to room "room" with 201
    Given using server "REMOTE"
    When user "participant2" sets notifications to all for room "LOCAL::room" (v4)
    Given using server "LOCAL"
    And user "participant1" sets description for room "room" to "the description" with 200 (v4)
    And user "participant1" react with "🚀" on message "Message 1" to room "room" with 201
    Given using server "REMOTE"
    Then user "participant2" has the following notifications
      | app    | object_type | object_id                | subject                                                                | message     |

  Scenario: Reaction on federated chat messages
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And user "participant2" sends message "Message 1" to room "LOCAL::room" with 201
    And using server "LOCAL"
    And user "participant1" react with "🚀" on message "Message 1" to room "room" with 201
      | actorType       | actorId                  | actorDisplayName         | reaction |
      | users           | participant1             | participant1-displayname | 🚀       |
    And using server "REMOTE"
    And user "participant2" react with "🚀" on message "Message 1" to room "LOCAL::room" with 201
      | actorType       | actorId                   | actorDisplayName         | reaction |
      | federated_users | participant1@{$LOCAL_URL} | participant1-displayname | 🚀       |
      | users           | participant2              | participant2-displayname | 🚀       |
    And using server "LOCAL"
    And user "participant1" retrieve reactions "all" of message "Message 1" in room "room" with 200
      | actorType       | actorId                    | actorDisplayName         | reaction |
      | users           | participant1               | participant1-displayname | 🚀       |
      | federated_users | participant2@{$REMOTE_URL} | participant2-displayname | 🚀       |


  Scenario: Typing indicator
    Given user "participant1" creates room "room" (v4)
      | roomType | 2 |
      | roomName | room |
    And user "participant1" adds federated_user "participant2" to room "room" with 200 (v4)
    And using server "REMOTE"
    And user "participant2" has the following invitations (v1)
      | remoteServerUrl | remoteToken | state | inviterCloudId     | inviterDisplayName       |
      | LOCAL           | room        | 0     | participant1@LOCAL | participant1-displayname |
    And user "participant2" accepts invite to room "room" of server "LOCAL" with 200 (v1)
      | id          | name | type | remoteServer | remoteToken |
      | LOCAL::room | room | 2    | LOCAL        | room        |
    Then user "participant2" is participant of the following rooms (v4)
      | id          | type |
      | LOCAL::room | 2    |
    # Join and leave to clear the invite notification
    Given user "participant2" joins room "LOCAL::room" with 200 (v4)
    Given user "participant2" leaves room "LOCAL::room" with 200 (v4)
    And using server "LOCAL"
    When user "participant1" sets setting "typing_privacy" to 1 with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>typing-privacy" set to 1
    Then user "participant1" has room capability "config=>chat=>typing-privacy" set to 1 on room "room"
    # Public
    When user "participant1" sets setting "typing_privacy" to 0 with 200 (v1)
    Then user "participant1" has capability "spreed=>config=>chat=>typing-privacy" set to 0
    Then user "participant1" has room capability "config=>chat=>typing-privacy" set to 0 on room "room"
