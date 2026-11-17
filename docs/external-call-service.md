# External Call Service

Nextcloud Talk can delegate in-browser calls to a third-party video conferencing service (e.g. Pexip) by rendering it inside an iframe. The integration has two directions:

* **Talk → external service** – when a user clicks the call button, Talk calls a configured HTTP endpoint to obtain the iframe URL.
* **External service → Talk** – the service can create Nextcloud Talk conversations and communicate call lifecycle events back to the parent page via `postMessage`.

## Requirements

* Nextcloud Talk built-in calls must be disabled (`start_calls = 3`).
* The conversation must have `objectType = external_call` with an `objectId` that identifies the meeting on the external service side. The `objectId` must be a UUID in lowercase (e.g. `fe455a7b-3ab3-4481-b385-080783dc1ad7`).

## Server configuration

All settings are set with `occ config:app:set spreed`:

| Config key                            | Expected value                                                   | Description                                                                                                                                                                                                             |
|---------------------------------------|------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `start_calls`                         | `3`                                                              | Disables Nextcloud Talk's built-in calls so the external service button is shown instead                                                                                                                                |
| `external_call_service`               | `https://pcs.example.tld/nextcloud/meeting/{meetingId}`          | URL of the external service endpoint. `{meetingId}` is replaced with the conversation's `objectId` when Talk makes the request                                                                                          |
| `external_call_service_frame_origins` | `["https://service.example.tld","https://service2.example.tld"]` | JSON array of scheme+host(+port) origins that may be loaded in the iframe. Added to `Content-Security-Policy: frame-src` and the `Permissions-Policy` for camera/microphone                                             |
| `external_call_service_public_key`    | *PEM encoded public key*                                         | ECDSA P-256 public key of the external service, used to verify the ES256 JWT the external service sends to Talk                                                                                                         |
| `external_call_service_auth_user`     | `nextcloud`                                                      | HTTP Basic Auth username used when Talk calls the external service                                                                                                                                                      |
| `external_call_service_auth_password` | *random string*                                                  | HTTP Basic Auth password used when Talk calls the external service                                                                                                                                                      |
| `external_call_service_iframe_field`  | `iframeUrl`                                                      | JSON field name in the external service response that contains the iframe URL                                                                                                                                           |

### Sample setup commands

```bash
occ config:app:set spreed start_calls --value '3'
occ config:app:set spreed external_call_service --value 'https://pcs.example.tld/nextcloud/meeting/{meetingId}'
occ config:app:set spreed external_call_service_frame_origins --value '["https://service.example.tld","https://service2.example.tld"]' --type array
occ config:app:set spreed external_call_service_public_key --value "$(cat external-call-service-public.pem)"
occ config:app:set spreed external_call_service_auth_user --value 'nextcloud'
occ config:app:set spreed external_call_service_auth_password --sensitive --value 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
occ config:app:set spreed external_call_service_iframe_field --value 'iframeUrl'
```

## Talk → external service: resolving the iframe URL

When a user clicks the call button in an `external_call` conversation, Talk makes a server-side request to the configured endpoint and returns the iframe URL to the client.

* Method: `GET`
* URL: value of `external_call_service` with `{meetingId}` replaced by the conversation's `objectId`
* Authentication: HTTP Basic Auth — username from `external_call_service_auth_user`, password from `external_call_service_auth_password`
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

## External service → Talk: request authentication

Requests from the external service to Talk are authenticated with an ES256 JWT. The external service signs it with its ECDSA P-256 private key, Talk verifies it with the public key configured in `external_call_service_public_key`. A key pair can be created with:

```bash
openssl ecparam -name prime256v1 -genkey -noout -out external-call-service-private.pem
openssl ec -in external-call-service-private.pem -pubout -out external-call-service-public.pem
```

Each request must carry the following headers (no `Authorization` header):

| Header                                  | Value                                     |
|-----------------------------------------|-------------------------------------------|
| `x-nextcloud-talk-external-service-jwt` | JWT as described below                    |
| `OCS-APIRequest`                        | `true`                                    |
| `Content-Type`                          | `application/json`                        |

The JWT must be signed with `ES256` (other algorithms are rejected) and contain the following claims:

| Claim    | Value                                                                                                                                                                       |
|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `iat`    | Current Unix timestamp in seconds                                                                                                                                           |
| `exp`    | Expiry as Unix timestamp, at most 60 seconds after `iat`                                                                                                                    |
| `jti`    | At least 32 random characters, newly generated for every request (e.g. 32 random bytes, hex encoded)                                                                        |
| `method` | The HTTP method in upper case, e.g. `PUT`                                                                                                                                   |
| `uri`    | The request URI including query parameters as received by Nextcloud, e.g. `/ocs/v2.php/apps/spreed/api/v4/room/abc123/participants` or `/nextcloud/ocs/v2.php/…` in a subdirectory |
| `body`   | Lowercase hex SHA256 hash of the raw request body exactly as sent                                                                                                           |

Talk rejects a request when `iat` differs more than 1 minute from the server time, when the token expired, when the `jti` was already used, or when method, URI or body do not match the claims.

```php
use Firebase\JWT\JWT;

$body = json_encode(['owner' => 'alice', 'roomName' => 'Weekly sync'], JSON_THROW_ON_ERROR);
$now = time();
$jwt = JWT::encode([
    'iat' => $now,
    'exp' => $now + 60,
    'jti' => bin2hex(random_bytes(32)),
    'method' => 'PUT',
    'uri' => '/ocs/v2.php/apps/spreed/api/v4/room/abc123',
    'body' => hash('sha256', $body),
], file_get_contents('external-call-service-private.pem'), 'ES256');
```

## External service → Talk: creating a conversation

The external service may create Nextcloud Talk conversations on behalf of a user without that user being logged in.

* Method: `POST`
* Endpoint: `/ocs/v2.php/apps/spreed/api/v4/room`
* Authentication: see [request authentication](#external-service--talk-request-authentication)
* The `owner` body parameter is required: the Nextcloud user ID that will be set as the conversation owner and used as the actor

Only `roomType = 2` (group) and `roomType = 3` (public) are allowed when using this authentication method.

### Request body (additional field)

| Field    | Type    | Description                                                                                                                                |
|----------|---------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `owner`  | string  | Nextcloud user ID to act as and make owner of the new conversation. Required when authenticating as external service |

### Response codes

| Status             | Meaning                                                                                                                                |
|--------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| `201 Created`      | Room created successfully                                                                                                              |
| `400 Bad Request`  | Missing or invalid parameter (`error` field names the offending parameter, e.g. `owner`, `type`)                                       |
| `401 Unauthorized` | Invalid, expired or reused signature (also returned when no user session is present and the headers are absent) |

Requests with an invalid signature are brute-force protected (`talkExternalCallServiceSecret` action).

## External service → Talk: synchronizing participants

The external service can replace the participant list of an existing conversation. Only conversations with `objectType = external_call` are supported. Participants missing from the list are removed (owners are always kept), participants new in the list are added.

* Method: `PUT`
* Endpoint: `/ocs/v2.php/apps/spreed/api/v4/room/{token}/participants`
* Authentication: see [request authentication](#external-service--talk-request-authentication)
* `owner` (string, required): user ID of an owner of the conversation, used as the actor
* `participants` (object): same format as `participants` when creating a conversation, e.g. `{"users": ["alice"], "emails": ["bob@example.tld"]}`

| Status             | Meaning                                                                                 |
|--------------------|-----------------------------------------------------------------------------------------|
| `200 OK`           | Participants synchronized                                                               |
| `202 Accepted`     | Participants synchronized, but some could not be added (listed in `invalidParticipants`) |
| `400 Bad Request`  | Conversation does not have `objectType = external_call`                                 |
| `401 Unauthorized` | Invalid, expired or reused signature                                                                        |
| `403 Forbidden`    | `owner` is not an owner of the conversation                                             |
| `404 Not Found`    | Conversation or `owner` not found                                                       |

## External service → Talk: renaming a conversation

The external service can rename its own conversations (`objectType = external_call`) with the regular rename endpoint.

* Method: `PUT`
* Endpoint: `/ocs/v2.php/apps/spreed/api/v4/room/{token}`
* Authentication: see [request authentication](#external-service--talk-request-authentication)
* `owner` (string, required): user ID of an owner of the conversation, used as the actor
* `roomName` (string, required): new name of the conversation

| Status             | Meaning                                                                       |
|--------------------|-------------------------------------------------------------------------------|
| `200 OK`           | Conversation renamed                                                          |
| `400 Bad Request`  | The name is empty or too long                                                 |
| `401 Unauthorized` | Invalid, expired or reused signature                                                              |
| `403 Forbidden`    | `owner` is not an owner of the conversation                                   |
| `404 Not Found`    | Conversation, `owner` not found or conversation is not an `external_call` one |

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
