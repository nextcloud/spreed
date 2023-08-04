# eturnal configuration

## Install and setup _eturnal_ as TURN server

Quick-Test instructions in a [Linux Shell](https://github.com/processone/eturnal/blob/master/QUICK-TEST.md) or with [Docker](https://github.com/processone/eturnal/blob/master/docker-k8s/QUICK-TEST.md) are available as well.

## 1. Download and install

eturnal is available from a variety of sources such as native package managers, binary packages, installation from source or [container image](https://eturnal.net/documentation/code/docker.html). They are all described [here](https://github.com/processone/eturnal#installation).


## 2. Make eturnal run as daemon on startup

- On Linux systems, the eturnal server is usually invoked by systemd. 
    ```
    sudo systemctl status eturnal
    ```

- For non-systemd platforms, example init and OpenRC scripts are shipped below the `etc` directory. 
  
  For controlling eturnal, the eturnalctl command can be used; see:
    ```
    eturnalctl help
    ```

### Running eturnal on privileged ports

On systemd-managed systems, the systemd unit grants `NET_BIND_SERVICE` capability to the [service](https://github.com/processone/eturnal/blob/29e82f260d369a39bd4a395cb981e914b141875b/config/eturnal.service#L23).

Depending on the system configuration Linux kernel capabilities could be used to overcome this limitation. Capabilities can be associated with executable files using _setcap_, so you could allow eturnal's process manager _beam.smp_ in eturnal's `lib` directory, e.g.: 
```
setcap 'cap_net_bind_service=+ep' $(find /opt/eturnal -name beam.smp)
```

## 3. Configure `eturnal.yml` for usage with Nextcloud Talk

- Next you need to adjust eturnal's configuration file in `/etc/eturnal.yml` to work with Nextcloud Talk. This file uses the (indentation-sensitive!) YAML format. The shipped configuration file contains further explanations.
- Choose the listening port (default is _3478_) and an authentication secret, where a random hex is recommended
    ```
    openssl rand -hex 32
    ```

- Then uncomment/edit the following settings accordingly:

```yaml
eturnal:
  ## Shared secret for deriving temporary TURN credentials (default: $RANDOM):
  secret: "long-and-cryptic"     # Shared secret, CHANGE THIS.

  ## The server's public IPv4 address (default: autodetected):
  #relay_ipv4_addr: "203.0.113.4"
  ## The server's public IPv6 address (optional):
  #relay_ipv6_addr: "2001:db8::4"
  
  listen:
    -
      ip: "::"
      port: <yourChosenPortNumber>
      transport: udp
    -
      ip: "::"
      port: <yourChosenPortNumber>
      transport: tcp
```

- Support for TLS connections to the TURN server has been added in Talk 11.

  In some cases clients can be behind very restrictive firewalls that only allow TLS connections; in those cases the clients would be able to connect with other clients or the High Performance Backend only through a TURN server and a TLS connection. However, please note that TLS connections do not provide any additional security, as media streams are always end-to-end (When the High Performance Backend is used the High Performance Backend is one of the ends; in that case the media streams are not end-to-end encrypted between the participants but only between participants and the High Performance Backend) encrypted in WebRTC; enabling TLS is just a matter of providing the maximum compatibility.

  Also note that even with TURN over TLS a client may not be able to connect with the TURN server if the firewall performs deep packet inspection and drops packets to port 443 that are not really HTTPS packets. This would be a corner case, though, as given that the connection is encrypted in order to inspect the packets that means that the firewall acts as a man-in-the-middle and the connection is not actually encrypted end-to-end. There is nothing that can be done in that case, but it should be rather uncommon.

  In order to use TLS connections to the TURN server the TURN server requires an SSL certificate and, therefore, a domain. The path to the certificate file must be set in the [`tls_crt_file` parameter](https://eturnal.net/documentation/#tls_crt_file), and the private key file must be set in the [`tls_key_file` parameter](https://eturnal.net/documentation/#tls_key_file) within eturnal's configuration file as well as corresponding [listen](https://eturnal.net/documentation/#listen)er needs to be enabled. To listen for encrypted and unencrypted traffic on one port, the transport can be set to `auto` for `tcp`/`tls` multiplexing. 
  
```yaml
eturnal: 
  ...
  listen:
  ...
  -
    ip: "::"
    port: <yourChosenPortNumber>
    transport: auto
    ...
  ## TLS certificate/key files (must be readable by 'eturnal' user!):
  tls_crt_file: /etc/eturnal/tls/crt.pem
  tls_key_file: /etc/eturnal/tls/key.pem
  ...
```
  
  Besides that in [Talk settings](TURN.md#4-configure-nextcloud-talk-to-use-your-turn-server) you must set the TURN server scheme as `turns:` or `turn: and turns:`.

  Note that, even if TLS provides the maximum compatibility, using a domain can cause problems with Firefox on a very specific scenario: [currently Firefox does not perform DNS requests through HTTP tunnels](https://bugzilla.mozilla.org/show_bug.cgi?id=1239006), so even if the WebRTC connection would work through the TURN server the TURN server may not be reachable.

- The recommended listening port is port 443, even if only _turn:_ but not _turns:_ is used. In some cases firewalls restrict connections only to port 443, but they do not actually check whether the connection is a TLS connection or not. Nevertheless, as mentioned above using both _turn:_ and _turns:_ is recommended for maximum compatibility.

- If your TURN server is running **not behind a NAT**, but with direct www connection and **static public IP**, than you can limit the IPs it listens at and answers with, by setting those as `ip` and `relay_ipv4_addr`/`relay_ipv6_addr` (IPv6 is optional). On larger deployments it is recommended to run your TURN server on a dedicated machine that is directly accessible from the internet.

- If `eturnal` was started by systemd, log files are written into the `/var/log/eturnal` directory by default. In order to log to the [journal](https://www.freedesktop.org/software/systemd/man/systemd-journald.service.html) instead, the `log_dir` option can be set to `stdout` in the configuration file.

- `sudo systemctl restart eturnal` or corresponding restart method

### TURN server and internal networks

If your TURN server has access to an internal network you should prevent access to the local/internal IPs from the TURN server, except those that are actually needed (like the High Performance Backend if you are using it) by setting the `blacklist`, [see also the official documentation](https://eturnal.net/documentation/#blacklist):

```yaml
eturnal:
  ...
  ## Reject TURN relaying from/to the following addresses/networks:
  blacklist:             # This is the default blacklist.
  - "127.0.0.0/8"        # IPv4 loopback.
  - "::1"                # IPv6 loopback.
  - recommended          # Expands to a number of networks recommended to be
                         # blocked, but includes private networks. Those
                         # would have to be 'whitelist'ed if eturnal serves
                         # local clients/peers within such networks.
  ...
```

To whitelist IP addresses (like the High Performance Backend if you are using it) or specific (private) networks, you need to **add** a whitelist part into the configuration file, e.g.:

```yaml
eturnal:
  ...
  whitelist:
  - {IP_ADDRESS_OF_THE_HIGH_PERFORMANCE_BACKEND}
  - "192.168.0.0/16"
  - "203.0.113.113"
  - "2001:db8::/64"
  ...
```

The more specific, the better.

Otherwise, [a malicious user could access services in that internal network through your TURN server](https://www.rtcsec.com/2020/04/01-slack-webrtc-turn-compromise/).

Alternatively you could of course prevent access to that internal network from the TURN server by means of a firewall.

## eturnalctl opterations script

`eturnal` offers a handy [operations script](https://eturnal.net/documentation/#Operation) which can be called e.g. to check, whether the service is up, to restart the service, to query how many active sessions exist, to change logging behaviour and so on.

Hint: If `eturnalctl` is not part of your `$PATH`, consider either sym-linking it (e.g. ´ln -s /opt/eturnal/bin/eturnalctl /usr/local/bin/eturnalctl´) or call it from the default `eturnal` directory directly: e.g. `/opt/eturnal/bin/eturnalctl info`

## Continue with the integration into Nextcloud Talk

Now you can go back to the [TURN overview page](TURN.md#4-configure-nextcloud-talk-to-use-your-turn-server). 
