# Settings API

* Base endpoint: `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 19

## Set user´s settings

* Method: `POST`
* Endpoint: `/settings/user`
* Data:

| field   | type       | Description            |
|---------|------------|------------------------|
| `key`   | string     | The user config to set |
| `value` | string/int | The value to set       |

* Response:
    - Status code:
        + `200 OK` When the value was updated
        + `400 Bad Request` When the key or value was invalid
        + `401 Unauthorized` When the user is not logged in

## User settings

**Note:** Settings from `calls_start_without_media` onwards can not be set via above API.
Instead, the server API `POST /ocs/v2.php/apps/provisioning_api/api/v1/config/users/{appId}/{configKey}` needs to be used.

| Key                         | Capability                                  | Default                                            | Valid values                                                                                             |
|-----------------------------|---------------------------------------------|----------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| `attachment_folder`         | `config => attachments => folder`           | Value of app config `default_attachment_folder`    | Path owned by the user to store uploads and received shares. It is created if it does not exist.         |
| `read_status_privacy`       | `config => chat => read-privacy`            | `0`                                                | One of the read-status constants from the [constants list](constants.md#participant-read-status-privacy) |
| `typing_privacy`            | `config => chat => typing-privacy`          | `0`                                                | One of the typing privacy constants from the [constants list](constants.md#participant-typing-privacy)   |
| `play_sounds`               |                                             | `'yes'`                                            | `'yes'` and `'no'`                                                                                       |
| `calls_start_without_media` | `config => call => start-without-media`     | `''` falling back to app config with the same name | `'yes'` and `'no'`                                                                                       |
| `blur_virtual_background`   | `config => call => blur-virtual-background` | `'no'`                                             | `'yes'` and `'no'`                                                                                       |
| `conversations_list_style`  | `config => conversations => list-style`     | `''` falling back to app config with the same name | One of the constants from the [constants list](constants.md#conversation-list-style)                     |
| `chat_style`                | `config => chat => chat-style`              | `''` falling back to app config with the same name | One of the constants from the [constants list](constants.md#chat-style)                                  |
| `conversations_unarchive`   | `config => conversations => unarchive`      | `'never'`                                          | One of the constants from the [constants list](constants.md#conversations-unarchive-mode)                |
| `conversations_tags_show_unread` | `config => conversations => tags-show-unread` | `'always'`                                         | One of the constants from the [constants list](constants.md#conversation-tags-show-unread-mode)             |

## Set SIP settings

* Required capability: `sip-support`
* Method: `POST`
* Endpoint: `/settings/sip`
* Data:

    All values must be sent in the same request

| field          | type   | Description                                                       |
|----------------|--------|-------------------------------------------------------------------|
| `sipGroups`    | array  | List of group ids that are allow to enable SIP for a conversation |
| `dialInInfo`   | string | The dial-in information shown in the sidebar and sent in emails   |
| `sharedSecret` | string | The shared secret of the SIP component                            |

* Response:
    - Status code:
        + `403 Forbidden` When the user is not an admin

## App configuration

**Note:** All app configs are stored as `string` in the `oc_appconfig` database table. Arrays and objects are therefor JSON encoded, integers are casted to string and booleans are replaced with `0/1` or `no/yes` strings.

When available the dedicated UI or OCC command option should be used to configure the setting rather than directly manipulating the database.

Legend:

* `Hash` - Whether the changing the config changes the Talk version hash triggering clients to refresh capabilities
* 🖌️ - UI option in the admin settings available
* 💻 - Dedicated OCC command available

| Key | Type | Default | Hash | Option | Description |
|---|---|---|---|---|---|
| `allowed_groups` | `list<string>` | `[]` | Yes | 🖌️ | List of group ids that are allowed to use Talk (everyone when empty) |
| `backgrounds_branded_for_guests` | bool | `false` | No |  | Whether guests are allowed to use the virtual backgrounds provided via `themes/talk-backgrounds/` |
| `backgrounds_default_for_users` | bool | `true` | No |  | Whether users are allowed to use the default virtual backgrounds provided by the releases |
| `backgrounds_upload_users` | bool | `true` | No |  | Whether users are allowed to upload custom virtual backgrounds and choose from their Nextcloud Files |
| `breakout_rooms` | bool | `true` | Yes |  | Whether or not breakout rooms are allowed (Will only prevent creating new breakout rooms. Existing conversations are not modified.) |
| `bridge_bot_password` | string | `''` | No |  | Automatically generated password of the matterbridge bot user profile |
| `call_end_to_end_encryption` | bool | `false` | No | 🖌️ | Whether clients should end-to-end encrypt streams in calls (Only supported with High-performance backend) |
| `call_recording` | bool | `true` | Yes |  | Enable call recording |
| `call_recording_summary` | bool | `true` | Yes | 🖌️ | Whether call recordings should automatically be summarized when a transcription and a summary provider is enabled. |
| `call_recording_summary_prompt` | string | See `lib/ConfigLexicon.php` | No |  | Instructions used by LLM to generate Talk call recording summaries |
| `call_recording_transcription` | bool | `false` | Yes | 🖌️ | Whether call recordings should automatically be transcribed when a transcription provider is enabled. |
| `calls_start_without_media` | bool | `false` | No |  | Whether participants start with enabled or disabled audio and video by default |
| `certificate_expiration_days` | int | `10` | No |  | Minimum days a certificate needs to be valid for, before an expiration notification will be shown. (default 10, minimum 0 and maximum 365) |
| `changelog` | bool | `true` | No |  | Whether the changelog conversation is updated with new features on major releases |
| `chat_style` | string<br>`split` or `unified` | `split` | No |  | Default chat style when not overwritten by the user |
| `conversation_subfolders` | bool | `true` | No |  | Whether files shared in a conversation are stored in a dedicated subfolder of the conversation inside the attachment folder |
| `conversations_files` | bool | `true` | No | 🖌️ | Whether the files app integration is enabled allowing to start conversations in the right sidebar |
| `conversations_files_public_shares` | bool | `true` | No | 🖌️ | Whether the public share integration is enabled allowing to start conversations in the right sidebar on the public share page (Requires `conversations_files` also to be enabled) |
| `conversations_list_style` | string<br>`two-lines` or `compact` | `two-lines` | No |  | Default conversation list style when not overwritten by the user |
| `create_samples` | bool | `true` | No |  | Create sample conversations (the content can be overwritten by providing files in a provided `samples_directory` app config) |
| `default_attachment_folder` | string | `/Talk` | No |  | Specify default attachment folder location |
| `default_group_notification` | int | `1` | No | 🖌️ | Default notification level for group conversations, see https://github.com/nextcloud/spreed/blob/main/docs/constants.md#participant-notification-levels<br>(Default changed from 2 (mentions) to 1 (always) in Nextcloud 33 for new installations) |
| `default_permissions` | int | `246` | Yes |  | Default permissions for non-moderators<br>(see https://github.com/nextcloud/spreed/blob/main/docs/constants.md#attendee-permissions for bit flags) |
| `delete_one_to_one_conversations` | bool | `false` | No |  | Whether one-to-one conversations can be left by either participant or should be deleted when one participant leaves |
| `enable_matterbridge` | bool | `false` | Yes | 🖌️ | Whether the Matterbridge integration is enabled and can be configured |
| `experiments_guests` | int | `0` | Yes |  | Bit flag of experiments that should be enabled for guests on this server<br>See https://github.com/nextcloud/spreed/blob/main/docs/settings.md#experiments |
| `experiments_users` | int | `0` | Yes |  | Bit flag of experiments that should be enabled for logged-in users on this server<br>See https://github.com/nextcloud/spreed/blob/main/docs/settings.md#experiments |
| `external_call_service` | string | `''` | No |  | URL of the external service endpoint. `{meetingId}` is replaced with the conversation's `objectId` when Talk makes the request |
| `external_call_service_auth_password` | string | `''` | No |  | HTTP Basic Auth password used when Talk calls the external service |
| `external_call_service_auth_user` | string | `''` | No |  | HTTP Basic Auth username used when Talk calls the external service |
| `external_call_service_frame_origins` | `list<string>` | `[]` | No |  | JSON array of scheme+host(+port) origins that may be loaded in the iframe.<br>Added to `Content-Security-Policy: frame-src` and the `Permissions-Policy` for camera/microphone |
| `external_call_service_iframe_field` | string | `''` | No |  | JSON field name in the external service response that contains the iframe URL |
| `external_call_service_shared_secret` | string | `''` | No |  | Shared secret used for two purposes:<br>as the HTTP Basic Auth password when Talk calls the external service, and as the bearer token when the external service calls Talk.<br>Minimum 64 characters, `a-zA-Z0-9` recommended |
| `feature_hints_hidden` | int | `0` | Yes |  | Feature hints that should be hidden. Set to `999999` to hide all. |
| `federation_allowed_groups` | `list<string>` | `[]` | Yes | 🖌️ | List of local group ids that are allowed to use federated features (everyone when empty) |
| `federation_enabled` | bool | `false` | Yes | 🖌️ | Whether or not federation with this instance is allowed |
| `federation_incoming_enabled` | bool | `true` | Yes | 🖌️ | Whether users of this instance can be invited to federated conversations |
| `federation_only_trusted_servers` | bool | `false` | Yes | 🖌️ | Whether federation should be limited to the list of "Trusted servers" |
| `federation_outgoing_enabled` | bool | `true` | Yes | 🖌️ | Whether users of this instance can invite federated users into conversations |
| `force_passwords` | bool | `false` | No |  | Whether public chats are forced to use a password |
| `grid_videos_limit` | int | `19` | No |  | Maximum number of videos to show (additional to the own video) |
| `grid_videos_limit_enforced` | bool | `false` | No |  | Whether the number of grid videos should be enforced |
| `guests_play_sounds` | bool | `true` | No |  | Whether guests hear the join and leave sounds by default |
| `has_feature_changed_users` | bool | `false` | No |  | Set automatically when the High-performance backend supports the `changed-users` feature, so only the affected users are sent in room updates |
| `hide_signaling_warning` | bool | `false` | No | 🖌️ | Flag that allows to suppress the warning that an HPB should be configured |
| `hosted_signaling_server_account` | `array<string, mixed>` | `[]` | No | 🖌️ | Account information of the hosted signaling server |
| `hosted_signaling_server_account_id` | string | `''` | No |  | Account identifier of the hosted signaling server |
| `hosted_signaling_server_nonce` | string | `''` | No |  | Temporary nonce while configuring the hosted signaling server |
| `inactivity_enable_lobby` | bool | `false` | No |  | Additionally enable the lobby for inactive rooms so they can only be read by moderators. |
| `inactivity_lock_after_days` | int | `0` | No |  | A duration (in days) after which rooms are locked. Calculated from the last activity in the room. |
| `matrix_allowed_groups` | `list<string>` | `[]` | No | 🖌️ | List of group ids that are allowed to link a Matrix account (everyone when empty) |
| `matrix_enabled` | bool | `false` | No | 🖌️ | Whether users can link a Matrix account and use their Matrix rooms as conversations |
| `matterbridge_binary` | string | `''` | No |  | Path to the matterbridge binary file |
| `max_call_duration` | int | `0` | No |  | Maximum duration of a call in seconds, 0 for unlimited. Federated calls will be terminated based on the setting of the host server.<br>Calls are ended via a background job, so system cron should be used and calls will last a bit longer<br>(until the next execution of cron). |
| `max_gif_size` | int | `3145728` | No |  | Maximum file size for clients to render GIF previews with animation |
| `recording_consent` | int | `0` | Yes | 🖌️ | Whether users have to agree on being recorded before they can join the call (see https://github.com/nextcloud/spreed/blob/main/docs/constants.md#recording-consent-required) |
| `recording_servers` | `array{servers: list<array{server: string, verify: bool}>, secret: string}` | `[]` | Yes | 🖌️ | List of recording servers, should be configured via the web interface |
| `retention_classified_rooms` | int | `604800` | No |  | Retention period of classified conversations in seconds after a call happened (`0` means no-retention) |
| `retention_event_rooms` | int | `28` | No |  | Retention period of event conversations in days (`0` means no-retention) |
| `retention_instant_meetings` | int | `1` | No |  | Retention period of instant meetings in days (`0` means no-retention) |
| `retention_phone_rooms` | int | `7` | No |  | Retention period of phone dial-in and dial-out conversations in days (`0` means no-retention) |
| `samples_directory` | string | `''` | No |  | Specify a readable directory that contains other sample conversation data |
| `session_ping_limit` | int | `200` | No |  | Number of sessions the HPB can ping in a single request |
| `signaling_servers` | `array{servers: list<array{server: string, verify: bool}>, secret: string}` | `{"servers":[],"secret":""}` | Yes | 🖌️💻 | List of signaling servers, should be configured via the web interface or the OCC commands |
| `signaling_servers_secret` | string | `''` | No | 💻 | Shared secret between Nextcloud and the signaling servers, should be configured via the web interface or the OCC commands |
| `signaling_ticket_secret` | string | `''` | Yes |  | Secret used to secure the signaling tickets for guests (255 character random string) |
| `signaling_token_alg` | string<br>`ES256`, `ES384`, `RS256`, `RS384`, `RS512` or `EdDSA` | `ES256` | Yes |  | Algorithm for the signaling tickets |
| `signaling_token_privkey` | string | `''` | No |  | Private key for the signaling ticket creation by the server |
| `signaling_token_pubkey` | string | `''` | Yes | 💻 | Public key for the signaling ticket creation by the server |
| `sip_bridge_dialin_info` | string | `''` | Yes | 🖌️ | Additional information added in the SIP dial-in invitation mail and sidebar |
| `sip_bridge_dialout` | bool | `false` | No | 🖌️ | SIP dial-out is allowed when a SIP bridge is configured |
| `sip_bridge_dialout_anonymous` | bool | `false` | No | 🖌️ | When set to true, all out-going SIP calls will be anonymous calls (CLIR) |
| `sip_bridge_dialout_number` | string | `''` | No | 🖌️ | Fallback phone number that is used as caller for out-going calls when the user has no phone-number configured (has to be E164 `+49123456789` formatted) |
| `sip_bridge_dialout_prefix` | string | `+` | No | 🖌️ | Deprecated: Only used for direct dial-in from older SIP bridges, which send phone numbers without this prefix |
| `sip_bridge_groups` | `list<string>` | `[]` | Yes | 🖌️ | List of group ids that are allowed to enable SIP dial-in in a conversation (everyone when empty) |
| `sip_bridge_shared_secret` | string | `''` | Yes | 🖌️ | Shared secret allowing the SIP bridge to authenticate on the Nextcloud server |
| `sip_dialin_default` | int | `0` | No |  | Default value of SIP dial-in when creating new conversations |
| `start_calls` | int | `0` | Yes | 🖌️ | Who can start a call, see https://github.com/nextcloud/spreed/blob/main/docs/constants.md#start-call |
| `start_calls_groups` | `list<string>` | `[]` | Yes | 🖌️ | List of group ids that are allowed to start calls (everyone when empty). This also applies to moderators; guests and federated users can then never start calls.<br>For federated conversations the host server decides. Others can still chat and join ongoing calls |
| `start_conversations` | `list<string>` | `[]` | Yes | 🖌️ | List of group ids that are allowed to create conversations (everyone when empty) |
| `stun_servers` | `list<string>` | `["stun.nextcloud.com:443"]` | Yes | 🖌️💻 | List of STUN servers for WebRTC connections |
| `summary_threshold` | int | `100` | No |  | Amount of unread messages a user needs before they see the option to summarize with AI |
| `token_entropy` | int | `8` | No |  | Length of conversation tokens, can be increased to make tokens harder to guess but reduces readability and dial-in comfort |
| `turn_servers` | `list<array{schemes: string, server: string, secret: string, protocols: string}>` | `[]` | Yes | 🖌️💻 | List of TURN servers for WebRTC connections |

## Experiments

Features that can be toggled on-off with the `experiments_users` and `experiments_guests` bit flags:

| Bit | Status    | Introduced                       | Ended                       | Description                                                                                                                 |
|-----|-----------|----------------------------------|-----------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| 1   | Completed | Web 21.1.0<br>Desktop 1.2.2-beta | Web 23.0.0<br>Desktop 2.x.x | Instead of refreshing the participant list repeatingly during calls, the data is generated from received signaling messages |
| 2   | Completed | Web 21.1.0<br>Desktop 1.2.2      | Web 23.0.0<br>Desktop 2.x.x | Make automatic attempts to recover suspended / expired signaling session to allow join the call without page reload         |
| 4   | Completed | Web 22.0.3<br>Desktop 2.0.4      | Web 23.0.0<br>Desktop 2.x.x | Send chat messages via the High performance-backend / websocket                                                             |
