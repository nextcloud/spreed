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
| `read_status_privacy` | `config => chat => read-privacy`  | `0`     | One of the read-status constants from the [constants list](constants.md#Participant-read-status-privacy) |

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
