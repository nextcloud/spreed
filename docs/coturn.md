# CoTURN configuration

## Install and setup _coTURN_ as TURN server

It is recommended to install the latest _coTURN_ version; at the very minimum _coTURN_ 4.5.0.8 should be used. In previous versions there is a bug that causes [the IPv6 UDP sockets created by coTURN not to be freed](https://github.com/coturn/coturn/issues/217). Due to this the _turn_ process ends not being able to open new ports and thus not being able to serve new connections. Moreover, when that happens, even if there are no connections a high CPU load will be caused by the _turn_ process. Therefore, if you can not install _coTURN_ 4.5.0.8 or a later version you should restart the _turn_ process periodically to work around that issue.

## 1. Download and install

- On **Debian and Ubuntu** there are official repository packages available:
    ```
    sudo apt install coturn
    ```
- For many **other Linux derivatives and UNIX-likes** packages can be found on [https://pkgs.org/download/coturn](https://pkgs.org/download/coturn)
- For all **other** cases check out the [Downloads in the wiki of coTURN](https://github.com/coturn/coturn/wiki/Downloads)


## 2. Make coturn run as daemon on startup

- On **Debian and Ubuntu** you just need to enable the deployed sysvinit service by adjusting the related environment variable:
    ```
    sudo sed -i '/TURNSERVER_ENABLED/c\TURNSERVER_ENABLED=1' /etc/default/coturn
    ```

- Since **Debian Buster** and **Ubuntu disco** the package ships a systemd unit, which does not use `/etc/default/coturn` but is enabled automatically on installation. To check whether a systemd unit is available:
    ```
    ls -l /lib/systemd/system/coturn.service
    ```

- If you installed coTURN manually, you may want to create a sysvinit service or systemd unit, or use another method to run the following during boot:
    ```
    /path/to/turnserver -c /path/to/turnserver.conf -o
    ```

- `-o` starts the server in daemon mode, `-c` defines the path to the config file.
- There is also an official example available at [https://github.com/coturn/coturn/blob/master/examples/etc/coturn.service](https://github.com/coturn/coturn/blob/master/examples/etc/coturn.service)

### Running coTURN on privileged ports

On some GNU/Linux distributions (for example, **Ubuntu Focal and later**) when _coTURN_ is installed from the official package the _coturn_ service is executed as an unprivileged user like _turnserver_. Due to this by default _coTURN_ can not use privileged ports, like port 443.

Depending on the system configuration Linux kernel capabilities could be used to overcome this limitation. Capabilities can be associated with executable files using _setcap_, so you could allow the _/usr/bin/turnserver_ executable to bind sockets to privileged ports with:
```
setcap cap_net_bind_service=+ep /usr/bin/turnserver
```

Alternatively, if the system configuration does not allow to set the capability, or if the coturn process needs to access files only readable by root like an SSL certificate for TLS connections, you could configure the _coturn_ service to be executed by root instead of the unprivileged user by executing:
```
systemctl edit coturn
```
and then setting the following configuration, which will override the default one:
```
[Service]
User=root
Group=root
```

## 3. Configure `turnserver.conf` for usage with Nextcloud Talk

- Next you need to adjust the coTURN configuration file to work with Nextcloud Talk.
- Choose the listening port (default is _3478_) and an authentication secret, where a random hex is recommended
    ```
    openssl rand -hex 32
    ```

- Then uncomment/edit the following settings accordingly:

```
listening-port=<yourChosenPortNumber>
fingerprint
lt-cred-mech # Only on coTURN below v4.5.0.8!
use-auth-secret
static-auth-secret=<yourChosen/GeneratedSecret>
realm=your.domain.org
total-quota=0
bps-capacity=0
stale-nonce
no-loopback-peers # Only on coTURN below v4.5.1.0!
no-multicast-peers
```

- Support for TLS connections to the TURN server has been added in Talk 11.

  In some cases clients can be behind very restrictive firewalls that only allow TLS connections; in those cases the clients would be able to connect with other clients or the High Performance Backend only through a TURN server and a TLS connection. However, please note that TLS connections do not provide any additional security, as media streams are always end-to-end (When the High Performance Backend is used the High Performance Backend is one of the ends; in that case the media streams are not end-to-end encrypted between the participants but only between participants and the High Performance Backend) encrypted in WebRTC; enabling TLS is just a matter of providing the maximum compatibility.

  Also note that even with TURN over TLS a client may not be able to connect with the TURN server if the firewall performs deep packet inspection and drops packets to port 443 that are not really HTTPS packets. This would be a corner case, though, as given that the connection is encrypted in order to inspect the packets that means that the firewall acts as a man-in-the-middle and the connection is not actually encrypted end-to-end. There is nothing that can be done in that case, but it should be rather uncommon.

  In order to use TLS connections to the TURN server the TURN server requires an SSL certificate and, therefore, a domain. The path to the certificate file must be set in the [`cert` parameter](https://github.com/coturn/coturn/blob/upstream/4.5.1.3/README.turnserver#L442-L446), and the private key file must be set in the [`pkey` file](https://github.com/coturn/coturn/blob/upstream/4.5.1.3/README.turnserver#L448-L452). Besides that in [Talk settings](#4-configure-nextcloud-talk-to-use-your-turn-server) you must set the TURN server scheme as `turns:` or `turn: and turns:`.

  Note that, even if TLS provides the maximum compatibility, using a domain can cause problems with Firefox on a very specific scenario: [currently Firefox does not perform DNS requests through HTTP tunnels](https://bugzilla.mozilla.org/show_bug.cgi?id=1239006), so even if the WebRTC connection would work through the TURN server the TURN server may not be reachable.

- The recommended listening port is port 443, even if only _turn:_ but not _turns:_ is used. In some cases firewalls restrict connections only to port 443, but they do not actually check whether the connection is a TLS connection or not. Nevertheless, as mentioned above using both _turn:_ and _turns:_ is recommended for maximum compatibility.

- The `total-quota` parameter limits the number of allowed simultaneous connections to the TURN server. Along with [`max-bps` and `bps-capacity`](https://github.com/coturn/coturn/blob/upstream/4.5.1.3/README.turnserver#L414-L423) it can be used to limit the effects of a [DoS attack against the TURN server](https://tools.ietf.org/html/rfc8656#section-21.3.1). The value of _0_ shown above means _unlimited_; if a connection limit is desired it should be adjusted depending on your specific setup.

  Please note that the number of allowed simultaneous connections limited by `total-quota` are not only fully established connections, but also the connections being tested during the negotiation phase used to establish the actual connection. During the negotiation phase each peer generates several candidates (an IP address and port) that can be used to establish a connection with that peer. Then the peers try to establish a connection between them with different candidate combinations until a valid one is found. If there is a TURN server, then the client will connect to the TURN server too, and it will generate additional candidates with the IP address of the TURN server (the so-called "relay" candidates). Each of those relay candidates will try to connect to the candidates of the other peer, and each of those connection attempts allocates a slot in the available quota of the TURN server. If there are no more available slots "Allocation Quota Reached" message is written to coTURN logs.

  In most cases the candidates that will be generated, and thus the connections to the TURN server during the negotiation phase, can not be known beforehand. When Janus is used the number of candidate combinations is reduced, as the Janus candidates can be known, but the number of relay candidates that will be generated by the client may still be unknown. For example, it seems that browsers generate one relay candidate for each host candidate. Host candidates are those with the IP address known to the client, so typically there will be one for each network device in the system; in the case of Firefox host candidates are also generated for the IP addresses of local bridge network devices.

  You should take all that into account if you intend to set a specific value to the `total-quota` parameter, but for maximum availability an unlimited quota is recommended.

- If your TURN server is running **not behind a NAT**, but with direct www connection and **static public IP**, than you can limit the IPs it listens at and answers with, by setting those as `listening-ip` and `relay-ip`. On larger deployments it is recommended to run your TURN server on a dedicated machine that is directly accessible from the internet.

- The following settings can be used to adjust the **logging behaviour**. On SBCs with SDcards you may want to adjust this, as by default coTURN logs very verbose. The config file explains everything very well:

```
no-stdout-log
log-file=...
syslog
simple-log
```

- `sudo systemctl restart coturn` or corresponding restart method

### Disabling UDP or TCP protocols

Unless you have some special need, you should always enable both UDP and TCP protocols in your TURN server, as that provides the maximum compatibility. However, if you must limit the connections from clients to the TURN server through UDP or TCP protocols you can do that by enabling one the following settings, depending on the case:
```
no-udp
no-tcp
```

Please note that those settings only limit the protocols from the client to the TURN server. The relayed protocol from the TURN server to the other end (Janus if the High Performance Backend is being used, another client or TURN server if it is not) must be UDP; _coTURN_ provides the setting `no-udp-relay` to disable the UDP protocol for the relayed connection, but enabling it would cause the TURN server to be unusable in a WebRTC context.

Also keep in mind that disabling the UDP protocol from clients to the TURN server with `no-udp` in practice disables STUN on that server, as neither Janus nor the clients currently support STUN over TCP.

### TURN server and internal networks

If your TURN server has access to an internal network you should prevent access to the local/internal IPs from the TURN server, except those that are actually needed (like the High Performance Backend if you are using it) by setting the [`denied-peer-ip` and `allowed-peer-ip` parameters](https://github.com/coturn/coturn/blob/upstream/4.5.1.3/README.turnserver#L523-L537). For example:
```
allowed-peer-ip={IP_ADDRESS_OF_THE_HIGH_PERFORMANCE_BACKEND}
denied-peer-ip=0.0.0.0-0.255.255.255
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=100.64.0.0-100.127.255.255
denied-peer-ip=127.0.0.0-127.255.255.255
denied-peer-ip=169.254.0.0-169.254.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.0.0.0-192.0.0.255
denied-peer-ip=192.0.2.0-192.0.2.255
denied-peer-ip=192.88.99.0-192.88.99.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=198.18.0.0-198.19.255.255
denied-peer-ip=198.51.100.0-198.51.100.255
denied-peer-ip=203.0.113.0-203.0.113.255
denied-peer-ip=240.0.0.0-255.255.255.255
```

Otherwise, [a malicious user could access services in that internal network through your TURN server](https://www.rtcsec.com/2020/04/01-slack-webrtc-turn-compromise/).

Alternatively you could of course prevent access to that internal network from the TURN server by means of a firewall.

## Continue with the integration into Nextcloud Talk

Now you can go back to the [TURN overview page](TURN.md#4-configure-nextcloud-talk-to-use-your-turn-server). 
