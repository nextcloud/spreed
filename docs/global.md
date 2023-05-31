# Global API status and headers

## Maintenance mode

Server is in maintenance mode, when the header is not available it could be a missing execution of a database upgrade or any other general server failure.

* Response:
    - Status code:
        + `503 Service Unavailable`

    - Header:

| field                          | type | Description |
|--------------------------------|------|-------------|
| `X-Nextcloud-Maintenance-Mode` | int  | Value `1`   |


## Rate limit

The remote address sent too many requests targeting the same endpoint, see the [Nextcloud Developer manual](https://docs.nextcloud.com//server/stable/developer_manual/basics/controllers.html#rate-limiting) for more information.

* Response:
    - Status code:
       + `429 Too Many Requests`

## Brute force protection

The remote address sent too many requests targeting the same action, see the [Nextcloud Developer manual](https://docs.nextcloud.com//server/stable/developer_manual/basics/controllers.html#brute-force-protection) for more information.

* Response:
    - Status code:
       + `429 Too Many Requests`

## Outdated client

From time to time it is unavoidable to break compatibility. In such cases we try to be as helpful for the users as possible and instead of behaving unexpected, a dedicated error response is returned and the clients should handle it properly and show a message that the client is outdated and needs to be upgraded in order to continue using this server.

* Response:
    - Status code:
       + `426 Upgrade Required`
    - Body:
       + `ocs.meta.message` contains the minimum required version of the used client
