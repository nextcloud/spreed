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

| Key                   | Capability                        | Default | Valid values                                                                                             |
|-----------------------|-----------------------------------|---------|----------------------------------------------------------------------------------------------------------|
| `attachment_folder`   | `config => attachments => folder` | `/Talk` | Path owned by the user to store uploads and received shares. It is created if it does not exist.         |
| `read_status_privacy` | `config => chat => read-privacy`  | `0`     | One of the read-status constants from the [constants list](constants.md#participant-read-status-privacy) |

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

Option legend:

* 🖌️ - UI option in the admin settings available
* 💻 - Dedicated OCC command available

| Key                                  | Internal type                                                    | Default    | Option | Valid values                                                                                                                                                                      |
|--------------------------------------|------------------------------------------------------------------|------------|--------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `allowed_groups`                     | string[]                                                         | `[]`       | 🖌️    | List of group ids that are allowed to use Talk                                                                                                                                    |
| `sip_bridge_groups`                  | string[]                                                         | `[]`       | 🖌️    | List of group ids that are allowed to enable SIP dial-in in a conversation                                                                                                        |
| `start_conversations`                | string[]                                                         | `[]`       | 🖌️    | List of group ids that are allowed to create conversations                                                                                                                        |
| `hosted-signaling-server-account`    | array                                                            | `{}`       | 🖌️    | Account information of the hosted signaling server                                                                                                                                |
| `stun_servers`                       | array[]                                                          | `[]`       | 🖌💻️  | List of STUN servers, should be configured via the web interface or the OCC commands                                                                                              |
| `turn_servers`                       | array[]                                                          | `[]`       | 🖌️💻  | List of TURN servers, should be configured via the web interface or the OCC commands                                                                                              |
| `signaling_servers`                  | array[]                                                          | `[]`       | 🖌️💻  | List of signaling servers, should be configured via the web interface or the OCC commands                                                                                         |
| `signaling_mode`                     | string<br>`internal` or `external` or `conversation_cluster`     | `internal` |        | `internal` when no HPB is configured, `external` when configured, `conversation_cluster` is an experimental flag that is deprecated                                               |
| `sip_bridge_dialin_info`             | string                                                           |            | 🖌️    | Additional information added in the SIP dial-in invitation mail and sidebar                                                                                                       |
| `sip_bridge_shared_secret`           | string                                                           |            | 🖌️    | Shared secret allowing the SIP bridge to authenticate on the Nextcloud server                                                                                                     |
| `signaling_ticket_secret`            | string                                                           |            |        | Secret used to secure the signaling tickets for guests (255 character random string)                                                                                              |
| `signaling_token_alg`                | string<br>`ES256`, `ES384`, `RS256`, `RS384`, `RS512` or `EdDSA` | `ES256`    |        | Algorithm for the signaling tickets                                                                                                                                               |
| `signaling_token_privkey_*`          | string                                                           | *          |        | Private key for the signaling ticket creation by the server                                                                                                                       |
| `signaling_token_pubkey__*`          | string                                                           | *          |        | Public key for the signaling ticket creation by the server                                                                                                                        |
| `hosted-signaling-server-nonce`      | string                                                           |            |        | Temporary nonce while configuring the hosted signaling server                                                                                                                     |
| `hosted-signaling-server-account-id` | string                                                           |            |        | Account identifier of the hosted signaling server                                                                                                                                 |
| `matterbridge_binary`                | string                                                           |            |        | Path to the matterbridge binary file                                                                                                                                              |
| `bridge_bot_password`                | string                                                           |            |        | Automatically generated password of the matterbridge bot user profile                                                                                                             |
| `start_calls`                        | int                                                              | `0`        | 🖌️    | Who can start a call, see [constants list](constants.md#start-call)                                                                                                               |
| `max-gif-size`                       | int                                                              | `3145728`  |        | Maximum file size for clients to render gifs previews with animation                                                                                                              |
| `session-ping-limit`                 | int                                                              | `200`      |        | Number of sessions the HPB can ping in a single request                                                                                                                           |
| `token_entropy`                      | int                                                              | `8`        |        | Length of conversation tokens, can be increased to make tokens harder to guess but reduces readability and dial-in comfort                                                        |
| `default_group_notification`         | int                                                              | `2`        | 🖌️    | Default notification level for group conversations [constants list](constants.md#participant-notification-levels)                                                                 |
| `grid_videos_limit`                  | int                                                              | `19`       |        | Maximum number of videos to show (additional to the own video)                                                                                                                    |
| `grid_videos_limit_enforced`         | string<br>`yes` or `no`                                          | `no`       |        | Whether the number of grid videos should be enforced                                                                                                                              |
| `changelog`                          | string<br>`yes` or `no`                                          | `yes`      |        | Whether the changelog conversation is updated with new features on major releases                                                                                                 |
| `has_reference_id`                   | string<br>`yes` or `no`                                          | `no`       |        | Indicator whether the clients can use the reference value to identify their message, will be automatically set to `yes` when the repair steps are executed                        |
| `hide_signaling_warning`             | string<br>`yes` or `no`                                          | `no`       | 🖌️    | Flag that allows to suppress the warning that an HPB should be configured                                                                                                         |
| `signaling_dev`                      | string<br>`yes` or `no`                                          | `no`       |        | Developer flag that allows to suppress various requirements like a Redis server when using the HPB                                                                                |
| `breakout_rooms`                     | string<br>`yes` or `no`                                          | `yes`      |        | Whether or not breakout rooms are allowed (Will only prevent creating new breakout rooms. Existing conversations are not modified.)                                               |
| `federation_enabled`                 | string<br>`yes` or `no`                                          | `no`       |        | 🏗️ *Work in progress:* Whether or not federation with this instance is allowed                                                                                                   |
| `conversations_files`                | string<br>`1` or `0`                                             | `1`        | 🖌️    | Whether the files app integration is enabled allowing to start conversations in the right sidebar                                                                                 |
| `conversations_files_public_shares`  | string<br>`1` or `0`                                             | `1`        | 🖌️    | Whether the public share integration is enabled allowing to start conversations in the right sidebar on the public share page (Requires `conversations_files` also to be enabled) |
| `enable_matterbridge`                | string<br>`1` or `0`                                             | `0`        | 🖌️    | Whether the matterbridge integration is enabled and can be configured                                                                                                             |
| `call_recording`                     | string<br>`yes` or `no`                                          | `yes`      |        | Enable call recording                                                                                                                                                             |
