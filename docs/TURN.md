### Background
The configuration of Nextcloud Talk mainly depends on your desired usage:

- As long as it shall be used only **within one local network**, besides the app, nothing else should be required. Just verify that all browsers support the underlying [WebRTC](https://en.wikipedia.org/wiki/WebRTC) protocol - most contemporary browsers do with current versions, though mobile browsers tend to lag behind a little - and you should be good to go. Browser support can be tested for example here: [https://test.webrtc.org/](https://test.webrtc.org/)

- Talk tries to establish a direct [peer-to-peer (P2P)](https://en.wikipedia.org/wiki/Peer-to-peer) connection, thus on connections **beyond the local network** (behind a [NAT](https://en.wikipedia.org/wiki/Network_address_translation) or router), clients do not only need to know each other's public IP, but the participants local IPs as well. Processing this, is the job of a [STUN](https://en.wikipedia.org/wiki/STUN) server. As there is one preconfigured for Nextcloud Talk that is operated by Nextcloud GmbH, for this case nothing else needs to be done.

- But in many cases, especially **in combination with firewalls or [symmetric NAT](https://en.wikipedia.org/wiki/Network_address_translation#Symmetric_NAT)**, a direct P2P connection is not possible, even with the help of a STUN server. For this a so-called [TURN server](https://en.wikipedia.org/wiki/Traversal_Using_Relays_around_NAT) needs to be configured additionally.

- Nextcloud Talk will try direct P2P in the first place, use STUN if needed and TURN as last resort fallback. Thus, to be most flexible and guarantee functionality of your Nextcloud Talk instance, in all possible connection cases, you would want to set up a TURN server.

#### TURN server and Nextcloud Talk High Performance Backend

A TURN server might be needed even if the Nextcloud Talk High Performance Backend is used and publicly accessible.

The High Performance Backend uses a certain range of ports for WebRTC media connections (20000-40000 by default). A client could be behind a restrictive firewall that only allows connections to port 443, so even if the High Performance Backend is publicly accessible the client would need to connect to a TURN server in port 443, and the TURN server will then relay the packets to the 20000-40000 range in the High Performance Backend.

For maximum compatibility the TURN server should be configured to listen on port 443. Therefore, when both a TURN server and the High Performance Backend are used each one should run in its own server, or in the same server but each one with its own IP address, as the High Performance Backend will need to bind to port 443 too.

## Install and setup _coTURN_ as TURN server

It is recommended to install the latest _coTURN_ version; at the very minimum _coTURN_ 4.5.0.8 should be used. In previous versions there is a bug that causes [the IPv6 UDP sockets created by coTURN not to be freed](https://github.com/coturn/coturn/issues/217). Due to this the _turn_ process ends not being able to open new ports and thus not being able to serve new connections. Moreover, when that happens, even if there are no connections a high CPU load will be caused by the _turn_ process. Therefore, if you can not install _coTURN_ 4.5.0.8 or a later version you should restart the _turn_ process periodically to work around that issue.

#### 1. Download and install

- On **Debian and Ubuntu** there are official repository packages available:
    ```
    sudo apt install coturn
    ```
- For many **other Linux derivatives and UNIX-likes** packages can be found on [https://pkgs.org/download/coturn](https://pkgs.org/download/coturn)
- For all **other** cases check out the [Downloads in the wiki of coTURN](https://github.com/coturn/coturn/wiki/Downloads)


#### 2. Make coturn run as daemon on startup

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

##### Running coTURN on privileged ports

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

#### 3. Configure `turnserver.conf` for usage with Nextcloud Talk

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

- Support for TLS connections to the TURN server has been added in Talk 11. In some cases clients can be behind very restrictive firewalls that only allow TLS connections; in those cases the clients would be able to connect with other clients or the High Performance Backend only through a TURN server and a TLS connection. However, please note that TLS connections do not provide any additional security, as media streams are always end-to-end* encrypted in WebRTC; enabling TLS is just a matter of providing the maximum compatibility.

  *When the High Performance Backend is used the High Performance Backend is one of the ends; in that case the media streams are not end-to-end encrypted between the participants but only between participants and the High Performance Backend.

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

##### Disabling UDP or TCP protocols

Unless you have some special need, you should always enable both UDP and TCP protocols in your TURN server, as that provides the maximum compatibility. However, if you must limit the connections from clients to the TURN server through UDP or TCP protocols you can do that by enabling one the following settings, depending on the case:
```
no-udp
no-tcp
```

Please note that those settings only limit the protocols from the client to the TURN server. The relayed protocol from the TURN server to the other end (Janus if the High Performance Backend is being used, another client or TURN server if it is not) must be UDP; _coTURN_ provides the setting `no-udp-relay` to disable the UDP protocol for the relayed connection, but enabling it would cause the TURN server to be unusable in a WebRTC context.

Also keep in mind that disabling the UDP protocol from clients to the TURN server with `no-udp` in practice disables STUN on that server, as neither Janus nor the clients currently support STUN over TCP.

##### TURN server and internal networks

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

#### 4. Configure Nextcloud Talk to use your TURN server

- Go to Nextcloud admin panel > Talk settings. Btw. if you already have your own TURN server, you can and may want to use it as STUN server as well:

    * STUN servers: your.domain.org:<yourChosenPortNumber>
    * TURN server: your.domain.org:<yourChosenPortNumber>
    * TURN secret: <yourChosen/GeneratedSecret>
    * Protocol: UDP and TCP

- Do not add `http(s)://` or `turn(s)://` protocol prefix here, just enter the bare `domain:port`. The protocol (`turn:` and/or `turns:`) needs to be selected in the dropdown.

##### Changes in Talk 12

In Talk 11 and previous versions when several STUN or TURN servers were listed in the settings a random one was provided to the clients. Starting with Talk 12 all the STUN and TURN servers listed in the settings are now returned.

Nevertheless, please note that in most cases you will not need to set up several TURN servers to ensure that the clients can connect to them. In general a single TURN server using both _turn:_ and _turns:_ with UDP and TCP on port 443 should be enough. Also keep in mind that clients will try to connect to all configured STUN and TURN servers when joining a call, even if they are not actually used in the end.

If you need to retain the previous behaviour you should now do it by external means. For example, by using a properly configured load balancer in front of the TURN servers and configuring only that load balancer as the TURN server in Talk settings.

#### 5. Port opening/forwarding

- The TURN server on `<yourChosenPortNumber>` needs to be accessible for all Talk participants, so you need to open it to the web and if your TURN server is running **behind a NAT**, forward it to the related machine. Also make sure to set the [`--external-ip` option](https://github.com/coturn/coturn/wiki/turnserver#options) when your TURN server is in a private network.

- If the High Performance Backend is used the TURN server and the High Performance Backend must be able to reach each other. If set, the `external-ip` option defines the IP address of the TURN server that the High Performance Backend will try to connect to. Therefore, if both the TURN server and the High Performance Backend are in the same private network they may be able to reach each other using their local IP addresses, and thus it may not be needed to set the `external-ip` option. Moreover, when both servers are behind a firewall, in some cases (depending on the firewall configuration) setting the external IP can even cause the TURN server and the High Performance Backend to fail to reach each other (for example, if the firewall is not able to "loop" a packet from an internal address to an external one which then should go back to another internal address).

    * Note that in some cases additional addresses can be found during the negotiation of the connection, the so-called peer reflexive candidates. Due to this even if the external IP of the TURN server is not reachable by the High Performance Backend the connection may still work, but this should not be relied on.

#### 6. Testing the TURN server

##### Test if the TURN server is accessible from outside

Install _coTURN_ on your client. Please [refer above for details](#1-download-and-install). Note that in the case of the client you only need to install it, you do not need to perform any configuration after that.

Run `turnutils_uclient -p <port> -W <static-auth-secret> -v -y turn.example.com` where

- `<port>` is the port where your TURN server is listening
- `<static-auth-secret>` is the _static-auth-secret_ value configured in your TURN server
- `-v` enables the verbose mode to be able to check all the details
- `-y` enables _client-to-client_ connections, so _turnutils_uclient_ acts as both the client and the peer that the TURN server relays to; otherwise you would need to also run _turnutils_peer_ to act as the peer to relay to and specify its address and port when running _turnutils_uclient_ with `-e` and `-r`

By default, the connection between the TURN client and the TURN server will be done using UDP. To instead test TCP connections you need to add `-t` to the options.

No matter if you are using UDP or TCP the output should look similar to:

    0: IPv4. Connected from: 192.168.0.2:50988
    0: IPv4. Connected to: 1.2.3.4:3478
    0: allocate sent
    0: allocate response received:
    0: allocate sent
    0: allocate response received:
    0: success
    0: IPv4. Received relay addr: 1.2.3.4:56365
    ....
    4: Total transmit time is 4
    4: Total lost packets 0 (0.000000%), total send dropped 0 (0.000000%)
    4: Average round trip delay 32.500000 ms; min = 15 ms, max = 56 ms
    4: Average jitter 12.600000 ms; min = 0 ms, max = 41 ms

If the output hangs at some point this could mean that the TURN server is not accessible (for example, because a firewall blocks its ports). Pay special attention too to the _Total lost packets_ and _total send dropped_ values, as there would be no error message if the data was successfully sent to the TURN server, but then it was not properly relayed.

Further you should see in the TURN server log the successful connection.

This test only verifies that your TURN server is accessible from the outside, but it does not check if your TURN server can be actually used within Talk. For that please keep reading.

##### Test the TURN server connection from within Talk

When the TURN server is set in the Talk settings a basic test against the TURN server is performed. You can perform a deeper test by forcing your browser to send the media of a call only through the TURN server:

- Join a call
- Open your browser console
- Type `OCA.Talk.SimpleWebRTC.webrtc.config.peerConnectionConfig.iceTransportPolicy = 'relay'` in the console and press Enter
- Leave the call
- Join the call again

Now, in that browser, the media sent to and received from other participants in the call should go through the TURN server. If the call works then the TURN server should work.

##### Differences between Firefox and Chromium

Firefox and Chromium handle `iceTransportPolicy = 'relay'` in slightly different ways. When relay candidates are forced Firefox will use only relay candidates, but Chromium will also take into account peer reflexive candidates that refer to the TURN server. Due to this in the above test, in some specific cases, a connection could be established in Chromium but not in Firefox.

For example, if a Janus gateway is used too, the TURN server is in the same server as the Janus gateway and both are behind a firewall (not recommended), relay candidates could have the public IP address of the server while peer reflexive candidates could have the internal one. If the firewall drops connections between the public IP address and the public IP address the connection between coTURN and Janus may not be established (but without failing either), which would cause that Firefox establishes a connection with the TURN server, but the TURN server does not send or receive any packet to or from Janus. In Chromium, on the other hand, the connection would work as it would use the internal IP address of the server from the peer reflexive candidate.

However, in the scenario above Firefox would not be able to establish a connection only if relay candidates are forced. With a standard Firefox configuration it would take into account peer reflexive candidates too, and thus, it should work without issues. Nevertheless, note that although using `iceTransportPolicy = 'relay'` in the browser console is just a temporary setting there is a persistent setting in Firefox configuration (_about:config_) to force relay candidates, `media.peerconnection.ice.relay_only`. This setting is targeted towards privacy-minded people, so you may want to test the TURN server with Firefox to ensure that it works even with the most restrictive configurations.

### What else
Nextcloud Talk´s WebRTC handling is still mostly based on the one from the [Spreed.ME](https://www.spreed.me/) WebRTC solution. For this reason, all guides about how to configure coTURN for it, applies to Nextcloud Talk too.

If you need to use Talk with more than 5-10 users in the same call, you will need the Spreed High Performance Back-end from Nextcloud GmbH. Check [the website](https://nextcloud.com/talk/) for details.

#### Further reference

- [https://github.com/spreedbox/spreedbox/wiki/Use-TURN-server](https://github.com/spreedbox/spreedbox/wiki/Use-TURN-server)
- [https://github.com/nextcloud/spreed/issues/667](https://github.com/nextcloud/spreed/issues/667)
