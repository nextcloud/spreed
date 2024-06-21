# TURN server configuration

## Background
The configuration of Nextcloud Talk mainly depends on your desired usage:

- As long as it shall be used only **within one local network**, besides the app, nothing else should be required. Just verify that all browsers support the underlying [WebRTC](https://en.wikipedia.org/wiki/WebRTC) protocol - most contemporary browsers do with current versions, though mobile browsers tend to lag behind a little - and you should be good to go. Browser support can be tested for example here: [https://test.webrtc.org/](https://test.webrtc.org/)

- Talk tries to establish a direct [peer-to-peer (P2P)](https://en.wikipedia.org/wiki/Peer-to-peer) connection, thus on connections **beyond the local network** (behind a [NAT](https://en.wikipedia.org/wiki/Network_address_translation) or router), clients do not only need to know each other's public IP, but the participants local IPs as well. Processing this, is the job of a [STUN](https://en.wikipedia.org/wiki/STUN) server. As there is one preconfigured for Nextcloud Talk that is operated by Nextcloud GmbH, for this case nothing else needs to be done.

- But in many cases, especially **in combination with firewalls or [symmetric NAT](https://en.wikipedia.org/wiki/Network_address_translation#Symmetric_NAT)**, a direct P2P connection is not possible, even with the help of a STUN server. For this a so-called [TURN server](https://en.wikipedia.org/wiki/Traversal_Using_Relays_around_NAT) needs to be configured additionally.

- Nextcloud Talk will try direct P2P in the first place, use STUN if needed and TURN as last resort fallback. Thus, to be most flexible and guarantee functionality of your Nextcloud Talk instance, in all possible connection cases, you would want to set up a TURN server.

### TURN server and Nextcloud Talk High Performance Backend

A TURN server might be needed even if the Nextcloud Talk High Performance Backend is used and publicly accessible.

The High Performance Backend uses a certain range of ports for WebRTC media connections (20000-40000 by default). A client could be behind a restrictive firewall that only allows connections to port 443, so even if the High Performance Backend is publicly accessible the client would need to connect to a TURN server in port 443, and the TURN server will then relay the packets to the 20000-40000 range in the High Performance Backend.

For maximum compatibility the TURN server should be configured to listen on port 443. Therefore, when both a TURN server and the High Performance Backend are used each one should run in its own server, or in the same server but each one with its own IP address, as the High Performance Backend will need to bind to port 443 too.

## Install and set up your TURN server

This documentation provides two examples for TURN server implementations:

* [coturn](coturn.md)
* [eturnal](eturnal.md)

After you have setup the server part above, continue here on this page with the following steps.

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

- The TURN server on `<yourChosenPortNumber>` needs to be accessible for all Talk participants, so you need to open it to the web and if your TURN server is running **behind a NAT**, forward it to the related machine. Also make sure to set coturn's [`--external-ip` option](https://github.com/coturn/coturn/wiki/turnserver#options) or eturnal's [`relay_ipv4_addr` configuration item](https://eturnal.net/documentation/#relay_ipv4_addr) when your TURN server is in a private network.

- If the High Performance Backend is used the TURN server and the High Performance Backend must be able to reach each other. If set, the `external-ip` option/`relay_ipv4_addr` parameter defines the IP address of the TURN server that the High Performance Backend will try to connect to. Therefore, if both the TURN server and the High Performance Backend are in the same private network they may be able to reach each other using their local IP addresses, and thus it may not be needed to set the `external-ip` option/`relay_ipv4_addr` parameter. Moreover, when both servers are behind a firewall, in some cases (depending on the firewall configuration) setting the external IP can even cause the TURN server and the High Performance Backend to fail to reach each other (for example, if the firewall is not able to "loop" a packet from an internal address to an external one which then should go back to another internal address).

    * Note that in some cases additional addresses can be found during the negotiation of the connection, the so-called peer reflexive candidates. Due to this even if the external IP of the TURN server is not reachable by the High Performance Backend the connection may still work, but this should not be relied on.

#### 6. Testing the TURN server

##### Test if the TURN server is accessible from outside

For _coTURN_:

Install _coTURN_ on your client. Please [refer above for details](coturn.md#1-download-and-install). Note that in the case of the client you only need to install it, you do not need to perform any configuration after that.

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

For _eturnal_:

The easiest way is to refer to eturnal's Quick-Start guide, e.g. [in a shell](https://github.com/processone/eturnal/blob/master/doc/QUICK-TEST.md) or with [Docker](https://github.com/processone/eturnal/blob/master/doc/CONTAINER-QUICK-TEST.md).

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
