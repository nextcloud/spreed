# Integration tests

The tests in this directory run against a real homeserver and are skipped unless
`MATRIX_HOMESERVER` is set. Registration must be open (no verification) so the tests
can create throw-away users; `docker-compose.yml` starts such a Synapse.

```sh
docker compose -f tests/Integration/docker-compose.yml up -d
MATRIX_HOMESERVER=http://localhost:8008 composer test:integration
```
