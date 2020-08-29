### Background
The configuration of Nextcloud Talk mainly depends on your desired usage:

- As long as it shall be used only **within one local network**, besides the app, nothing else should be required. Just verify that all browsers support the underlying [WebRTC](https://en.wikipedia.org/wiki/WebRTC) protocol - most contemporary browsers do with current versions, though mobile browsers tend to lag behind a little - and you should be good to go. Browser support can be tested for example here: [https://test.webrtc.org/](https://test.webrtc.org/)

- Talk tries to establish a direct [peer-to-peer (P2P)](https://en.wikipedia.org/wiki/Peer-to-peer) connection, thus on connections **beyond the local network** (behind a [NAT](https://en.wikipedia.org/wiki/Network_address_translation) or router), clients do not only need to know each others public IP, but the participants local IPs as well. Processing this, is the job of a [STUN](https://en.wikipedia.org/wiki/STUN) server. As there is one preconfigured for Nextcloud Talk that is operated by Nextcloud GmbH, for this case nothing else needs to be done.

- But in many cases, especially **in combination with firewalls or [symmetric NAT](https://en.wikipedia.org/wiki/Network_address_translation#Symmetric_NAT)**, a direct P2P connection is not possible, even with the help of a STUN server. For this a so called [TURN server](https://en.wikipedia.org/wiki/Traversal_Using_Relays_around_NAT) needs to be configured additionally.

- Nextcloud Talk will try direct P2P in the first place, use STUN if needed and TURN as last resort fallback. Thus to be most flexible and guarantee functionality of your Nextcloud Talk instance in all possible connection cases, you would want to setup a TURN server.

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

- Since **Debian Buster** and **Ubuntu disco** the package ships a systemd unit, which does not use `/etc/default/coturn` but is enabled automatically on install. To check whether a systemd unit is available:
    ```
    ls -l /lib/systemd/system/coturn.service
    ```

- If you installed coTURN manually, you may want to create an sysvinit service or systemd unit, or use another method to run the following during boot:
    ```
    /path/to/turnserver -c /path/to/turnserver.conf -o
    ```

- `-o` starts the server in daemon mode, `-c` defines the path to the config file.
- There is also an official example available at [https://github.com/coturn/coturn/blob/master/examples/etc/coturn.service](https://github.com/coturn/coturn/blob/master/examples/etc/coturn.service)

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
total-quota=100
bps-capacity=0
stale-nonce
no-loopback-peers # Only on coTURN below v4.5.1.0!
no-multicast-peers
```

!!! note

    (D)TLS is currently not supported by Nextcloud Talk and does not have any real security benefit anyway. See the following discussions why (D)TLS for TURN has no real security benefit and why Nextcloud Talk is not supporting it:
    
    - [https://github.com/coturn/coturn/issues/33](https://github.com/coturn/coturn/issues/33)
    - [https://github.com/nextcloud/spreed/issues/257](https://github.com/nextcloud/spreed/issues/257)

- If your TURN server is running **not behind a NAT**, but with direct www connection and **static public IP**, than you can limit the IPs it listens at and answers with, by setting those as `listening-ip` and `relay-ip`. On larger deployments it is recommended to run your TURN server on a dedicated machine that is directly accessible from the internet.

- The following settings can be used to adjust the **logging behaviour**. On SBCs with SDcards you may want to adjust this, as by default coTURN logs very verbose. The config file explains everything very well:

```
no-stdout-log
log-file=...
syslog
simple-log
```

- `sudo systemctl restart coturn` or corresponding restart method

#### 4. Configure Nextcloud Talk to use your TURN server

- Go to Nextcloud admin panel > Talk settings. Btw. if you already have your own TURN server, you can and may want to use it as STUN server as well:

    * STUN servers: your.domain.org:<yourChosenPortNumber>
    * TURN server: your.domain.org:<yourChosenPortNumber>
    * TURN secret: <yourChosen/GeneratedSecret>
    * Protocol: UDP and TCP

- Do not add `http(s)://` or `turn(s)://` protocol prefix here, just enter the bare `domain:port`. Nextcloud Talk adds the required `turn://` protocol internally to the request.

#### 5. Port opening/forwarding

- The TURN server on `<yourChosenPortNumber>` needs to be accessible for all Talk participants, so you need to open it to the web and if your TURN server is running **behind a NAT**, forward it to the related machine. Also make sure to set the [`--external-ip` option](https://github.com/coturn/coturn/wiki/turnserver#options) when your TURN server is in a private network.

#### 6. Testing the TURN server

When the TURN server is set in the Talk settings a basic test against the TURN server is performed. You can perform a deeper test by forcing your browser to send the media of a call only through the TURN server:

- Join a call
- Open your browser console
- Type `OCA.Talk.SimpleWebRTC.webrtc.config.peerConnectionConfig.iceTransportPolicy = 'relay'` in the console and press Enter
- Leave the call
- Join the call again

Now, in that browser, the media sent to and received from other participants in the call should go through the TURN server. If the call works then the TURN server should work.

### What else
Nextcloud Talk´s WebRTC handling is still mostly based on the one from the [Spreed.ME](https://www.spreed.me/) WebRTC solution. For this reason, all guides about how to configure coTURN for it, applies to Nextcloud Talk too.

If you need to use Talk with more than 5-10 users in the same call, you will need the Spreed High Performance Back-end from Nextcloud GmbH. Check [the website](https://nextcloud.com/talk/) for details.

#### Further reference

- [https://github.com/spreedbox/spreedbox/wiki/Use-TURN-server](https://github.com/spreedbox/spreedbox/wiki/Use-TURN-server)
- [https://github.com/nextcloud/spreed/issues/667](https://github.com/nextcloud/spreed/issues/667)
