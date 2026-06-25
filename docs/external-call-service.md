# External Call Service

Nextcloud Talk can delegate in-browser calls to a third-party video conferencing service (e.g. Pexip) by rendering it inside an iframe. The integration has two directions:

* **Talk → external service** – when a user clicks the call button, Talk calls a configured HTTP endpoint to obtain the iframe URL.
* **External service → Talk** – the service can create Nextcloud Talk conversations and communicate call lifecycle events back to the parent page via `postMessage`.

## Requirements

* Nextcloud Talk built-in calls must be disabled (`start_calls = 3`).
* The conversation must have `objectType = external_call` with an `objectId` that identifies the meeting on the external service side.

## Server configuration

All settings are set with `occ config:app:set spreed`:

| Config key                            | Expected value                                                   | Description                                                                                                                                                                                                             |
|---------------------------------------|------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `start_calls`                         | `3`                                                              | Disables Nextcloud Talk's built-in calls so the external service button is shown instead                                                                                                                                |
| `external_call_service`               | `https://pcs.example.tld/nextcloud/meeting/{meetingId}`          | URL of the external service endpoint. `{meetingId}` is replaced with the conversation's `objectId` when Talk makes the request                                                                                          |
| `external_call_service_frame_origins` | `["https://service.example.tld","https://service2.example.tld"]` | JSON array of scheme+host(+port) origins that may be loaded in the iframe. Added to `Content-Security-Policy: frame-src` and the `Permissions-Policy` for camera/microphone                                             |
| `external_call_service_shared_secret` | *random string*                                                  | Shared secret used for two purposes: as the HTTP Basic Auth password when Talk calls the external service, and as the bearer token when the external service calls Talk. Minimum 64 characters, `a-zA-Z0-9` recommended |
| `external_call_service_auth_user`     | `nextcloud`                                                      | HTTP Basic Auth username used when Talk calls the external service                                                                                                                                                      |
| `external_call_service_auth_password` | *random string*                                                  | HTTP Basic Auth password used when Talk calls the external service                                                                                                                                                      |
| `external_call_service_iframe_field`  | `iframeUrl`                                                      | JSON field name in the external service response that contains the iframe URL                                                                                                                                           |

### Sample setup commands

```bash
occ config:app:set spreed start_calls --value '3'
occ config:app:set spreed external_call_service --value 'https://pcs.example.tld/nextcloud/meeting/{meetingId}'
occ config:app:set spreed external_call_service_frame_origins --value '["https://service.example.tld","https://service2.example.tld"]' --type array
occ config:app:set spreed external_call_service_shared_secret --sensitive --value 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
occ config:app:set spreed external_call_service_auth_user --value 'nextcloud'
occ config:app:set spreed external_call_service_auth_password --sensitive --value 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
occ config:app:set spreed external_call_service_iframe_field --value 'iframeUrl'
```

## Talk → external service: resolving the iframe URL

When a user clicks the call button in an `external_call` conversation, Talk makes a server-side request to the configured endpoint and returns the iframe URL to the client.

* Method: `GET`
* URL: value of `external_call_service` with `{meetingId}` replaced by the conversation's `objectId`
* Authentication: HTTP Basic Auth — username from `external_call_service_auth_user`, password from `external_call_service_shared_secret`
* Headers sent by Talk:
    - `x-nextcloud-user-id`: actor ID of the requesting participant (only for authenticated users, not guests)
    - `Accept: application/json`

The external service must respond with a JSON object. Talk reads the field named by `external_call_service_iframe_field` and uses it as the `src` of the iframe.

Example response (when `external_call_service_iframe_field = iframeUrl`):

```json
{
  "iframeUrl": "https://service.example.tld/meeting/abc123?token=xyz"
}
```

## External service → Talk: creating a conversation

The external service may create Nextcloud Talk conversations on behalf of a user without that user being logged in, by authenticating with the shared secret.

* Method: `POST`
* Endpoint: `/ocs/v2.php/apps/spreed/api/v4/room`
* Authentication: send the shared secret in the `x-nextcloud-talk-external-service` header (instead of a user session cookie)
* The `owner` body parameter is required: the Nextcloud user ID that will be set as the conversation owner and used as the actor

Only `roomType = 2` (group) and `roomType = 3` (public) are allowed when using this authentication method.

### Request headers

| Header                              | Value                    |
|-------------------------------------|--------------------------|
| `x-nextcloud-talk-external-service` | Configured shared secret |
| `OCS-APIRequest`                    | `true`                   |
| `Content-Type`                      | `application/json`       |

### Request body (additional field)

| Field    | Type    | Description                                                                                                                                |
|----------|---------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `owner`  | string  | Nextcloud user ID to act as and make owner of the new conversation. Required when using `x-nextcloud-talk-external-service` authentication |

### Response codes

| Status             | Meaning                                                                                                                                |
|--------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| `201 Created`      | Room created successfully                                                                                                              |
| `400 Bad Request`  | Missing or invalid parameter (`error` field names the offending parameter, e.g. `owner`, `type`)                                       |
| `401 Unauthorized` | Missing or invalid `x-nextcloud-talk-external-service` secret (also returned when no user session is present and the header is absent) |

Requests with an invalid secret are brute-force protected (`talkExternalCallServiceSecret` action).

## Talk → iframe communication

The iframe URL is loaded in a sandboxed `<iframe>` with the following attributes:

```html
<iframe
  sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-presentation"
  allow="camera; microphone; display-capture; fullscreen"
  referrerpolicy="no-referrer"
/>
```

The Talk UI hides its own top bar while the iframe is visible. The iframe can close itself and return to the chat view by sending a `postMessage` (see below).

## iframe → Talk: postMessage API

The external service page communicates call lifecycle events to the parent Talk window via `window.parent.postMessage`. Messages from origins not listed in `external_call_service_frame_origins` are silently ignored.

### Message format

```js
window.parent.postMessage({ type: '<event>' }, targetOrigin)
```

### Supported event types

| `type`                | When to send                                                            | Effect in Talk                                                              |
|-----------------------|-------------------------------------------------------------------------|-----------------------------------------------------------------------------|
| `externalCallJoined`  | The local user has successfully joined the call in the external service | Reserved for future side-effects (e.g. updating call presence, user status) |
| `externalCallLeft`    | The local user has left or ended the call                               | Unmounts the iframe and returns Talk to the chat view                       |

### Example (inside the iframe page)

```js
// Notify Talk the user has joined
window.parent.postMessage({ type: 'externalCallJoined' }, 'https://nextcloud.example.tld')

// Notify Talk the user has left — this closes the iframe
window.parent.postMessage({ type: 'externalCallLeft' }, 'https://nextcloud.example.tld')
```

The iframe can also be closed by the external service removing the element from the DOM directly (e.g. `iframe.parentNode.removeChild(iframe)`), which Talk detects via a `MutationObserver`.
