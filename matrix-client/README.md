# nextcloud/matrix-client-php

A framework-free [Matrix](https://matrix.org) Client-Server API client for PHP ≥ 8.1.
Developed for [Nextcloud Talk](https://github.com/nextcloud/spreed) but deliberately free of
any Nextcloud dependency: bring your own PSR-18 HTTP client and PSR-17 factories and it works
in any PHP application.

* Login (`m.login.password`), logout, whoami, server discovery
* `/sync` parsed into typed `SyncBatch` / `JoinedRoom` / `InvitedRoom` / `LeftRoom` objects
* Room state aggregation (`RoomState`) incl. power-level checks (`PowerLevels`)
* Sending messages/events/state, redactions, receipts, typing
* Membership & lifecycle: join, knock, leave, forget, invite, kick, ban, createRoom, alias resolution
* Media download/upload (authenticated media when the server supports v1.11)
* `formatted_body` sanitiser + HTML ⇄ Markdown converters
* Canonical JSON, identifier parsing (`matrix.to`, `matrix:` URIs)

* End-to-end encryption in pure PHP (`Nextcloud\Matrix\Crypto`): Olm double ratchet,
  Megolm group sessions, device keys and signatures, to-device / room envelopes, encrypted
  attachments, SAS (emoji) verification, room key sharing and requests via the `Machine`
  orchestrator. Wire-compatible with libolm/vodozemac — `tests/interop/vodozemac_check.py`
  drives both directions against Element's own library.

Key backup (`m.megolm_backup.v1`) and cross-signing key *creation* are not implemented yet
(the device can be cross-signed by another client).

## Usage

```php
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Http\Transport;

$factory = new HttpFactory();
$transport = new Transport('https://matrix.example.org', new Guzzle(['timeout' => 30]), $factory, $factory);
$client = new Client($transport);

$login = $client->loginWithPassword('alice', 'secret', 'My PHP client');
$client = $client->withAccessToken($login->accessToken);

$since = null;
while (true) {
    $batch = $client->sync($since, Client::defaultFilter(), timeoutMs: 30000);
    foreach ($batch->joined as $roomId => $room) {
        foreach ($room->timeline as $event) {
            if ($event->type === 'm.room.message') {
                echo "[$roomId] {$event->sender}: {$event->getBody()}\n";
            }
        }
    }
    $since = $batch->nextBatch;
}
```

See `examples/echo-bot.php` for a complete program.

## Boundary rules

* No `OCP\`/`OCA\` code in `src/` (enforced by `composer check:boundary`).
* All I/O goes through PSR interfaces; persistence through `Store\StoreInterface`.
* Only the configured homeserver base URL is ever contacted; media is fetched through it.

## Development

```sh
composer install
composer test               # unit tests
composer test:integration   # needs a Synapse: MATRIX_HOMESERVER=http://localhost:8008 (see tests/Integration/README.md)
composer psalm
pip install vodozemac && python3 tests/interop/vodozemac_check.py   # crypto interop against Element's library
```

## License

AGPL-3.0-or-later, © Nextcloud GmbH and contributors.
