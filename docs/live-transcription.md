# Live transcription API

* API v1: Base endpoint `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 32

## Start live transcription

* Required capability: `config => call => live-transcription`
* Method: `POST`
* Endpoint: `/live-transcription/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Message: `app`. The external app `live_transcription` is not available.
        + `400 Bad Request` Message: `in-call`. Participant is not in the call.

## Stop live transcription

* Required capability: `config => call => live-transcription`
* Method: `DELETE`
* Endpoint: `/live-transcription/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Message: `app`. The external app `live_transcription` is not available.
        + `400 Bad Request` Message: `in-call`. Participant is not in the call.
