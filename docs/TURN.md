### **Background**
The configuration of Nextcloud Talk mainly depends on your desired usage:
- As long as it shall be used only **within one local network**, besides the app, nothing else should be required. Just verify that all browsers support the underlying [WebRTC](https://en.wikipedia.org/wiki/WebRTC) protocol - all famous ones do on current versions - and you should be good to go. Browser support can be tested e.g. here: https://test.webrtc.org/
- Talk tries to establish a direct [peer-to-peer (P2P)](https://en.wikipedia.org/wiki/Peer-to-peer) connection, thus on connections **beyond the local network** (behind a [NAT](https://en.wikipedia.org/wiki/Network_address_translation)/router), clients do not only need to know each others public IP, but the participants local IPs as well. Processing this, is the job of a [STUN](https://en.wikipedia.org/wiki/STUN) server. As there is one preconfigured for Nextcloud Talk, still nothing else needs to be done.
- But in many cases, e.g. **in combination with firewalls or [symmetric NAT](https://en.wikipedia.org/wiki/Network_address_translation#Symmetric_NAT)**, a STUN server will not work as well, and then a so called [TURN](https://en.wikipedia.org/wiki/Traversal_Using_Relays_around_NAT) server is required. Now no direct P2P connection is established, but all traffic is relayed through the TURN server, thus additional (at least internal) traffic and resources are used.
- Nextcloud Talk will try direct P2P in the first place, use STUN if needed and TURN as last resort fallback. Thus to be most flexible and guarantee functionality of your Nextcloud Talk instance in all possible connection cases, you most properly want to setup a TURN server.

### **Install and setup _coTURN_ as TURN server**
1. **Download/install**
   - On **Debian and Ubuntu** there are official repository packages available:
     - `sudo apt install coturn`
   - For many **other Linux derivatives and UNIX-likes**, this is the case as well: https://pkgs.org/download/coturn
   - For all **other** cases check out: https://github.com/coturn/coturn/wiki/Downloads

2. **Make coturn run as daemon on startup**
   - On **Debian and Ubuntu** you just need to enable the deployed sysvinit service by adjusting the related environment variable:
     - `sudo sed -i '/TURNSERVER_ENABLED/c\TURNSERVER_ENABLED=1' /etc/default/coturn`
   - Since **Debian Buster** and **Ubuntu disco** the package ships a systemd unit, which does not use `/etc/default/coturn` but is enabled automatically on install. To check whether a systemd unit is available:
     - `ls -l /lib/systemd/system/coturn.service`
   - If you installed coTURN manually, you may want to create an sysvinit service or systemd unit, or use another method to run the following during boot:
     - `/path/to/turnserver -c /path/to/turnserver.conf -o`
     - `-o` starts the server in daemon mode, `-c` defines the path to the config file.
     - There is also an official example available: https://github.com/coturn/coturn/blob/master/examples/etc/coturn.service

3. **Configure _turnserver.conf_ for usage with Nextcloud Talk**
   - Next you need to adjust the coTURN configuration file to work with Nextcloud Talk.
   - Choose the listening port (default is _3478_) and an authentication secret, where a random hex is recommended:
     - `openssl rand -hex 32`
   - Then uncomment/edit the following settings accordingly:

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
   - <details><summary>(D)TLS is currently not supported by Nextcloud Talk and does not have any real security benefit anyway. Click here for more details.</summary>

     - See the following discussions why **(D)TLS** for TURN has no real security benefit and that Nextcloud Talk not supporting it: https://github.com/coturn/coturn/issues/33, https://github.com/nextcloud/spreed/issues/257
     - However for completeness: When using (D)TLS, you need to provide the path to your certificate and key files, and it is highly recommended to adjust the cipher list:

           tls-listening-port=<yourChosenPortNumber>
           fingerprint
           lt-cred-mech # Only on coTURN below v4.5.0.8!
           use-auth-secret
           static-auth-secret=<yourChosen/GeneratedSecret>
           realm=your.domain.org
           total-quota=100
           bps-capacity=0
           stale-nonce
           cert=/path/to/your/cert.pem
           pkey=/path/to/your/privkey.pem
           cipher-list="ECDH+AESGCM:DH+AESGCM:ECDH+AES256:DH+AES256:ECDH+AES128:DH+AES:ECDH+3DES:DH+3DES:RSA+AES:RSA+3DES:!ADH:!AECDH:!MD5"
           no-loopback-peers
           no-multicast-peers # Only on coTURN below v4.5.1.0!

     - Note that `listening-port`, `alt-listening-port`, `tls-listening-port` and `alt-tls-listening-port` can all be used for (D)TLS and plain text connections. It depends on the client request protocol only, TURN vs _TURNS_ (TURN over TLS). Hence there is usually no point to setup more then one port. Also Nextcloud Talk can only be configured to use a single port.
     - A working cipher example is provided above, that is also used within most other guides. But it makes totally sense to **use the cipher-list from your Nextcloud webserver** to have the same compatibility versus security versus performance for both.
     - If you want it damn secure, you can also configure a custom [Diffie-Hellman](https://en.wikipedia.org/wiki/Diffie–Hellman_key_exchange) file and/or disable TLSv1.0 + TLSv1.1. But again, it does not make much sense to handle it different here than for the webserver. Just decide how much compatibility you need and security/performance you want and configure webserver + coTURN the same:

           dh-file=/path/to/your/dh.pem
           no-tlsv1
           no-tlsv1_1</details>
   - If your TURN server is running **not behind a NAT**, but with direct www connection and **static public IP**, than you can limit the IPs it listens at and answers with, by setting those as `listening-ip` and `relay-ip`. On larger deployments it is recommended to run your TURN server on a dedicated machine that is directly accessible from the internet.
   - The following settings can be used to adjust the **logging behaviour**. On SBCs with SDcards you may want to adjust this, as by default coTURN logs very verbose. The config file explains everything very well:

         no-stdout-log
         log-file=...
         syslog
         simple-log

4. `sudo systemctl restart coturn` or corresponding restart method

5. **Configure Nextcloud Talk to use your TURN server**
   - Go to Nextcloud admin panel > Talk settings. Btw. if you already have your own TURN server, you can and may want to use it as STUN server as well:

         STUN servers: your.domain.org:<yourChosenPortNumber>
         TURN server: your.domain.org:<yourChosenPortNumber>
         TURN secret: <yourChosen/GeneratedSecret>
         UDP and TCP
   - Do not add `http(s)://` or turn(s)://` protocol prefix here, just enter the bare `domain:port`. Nextcloud Talk adds the required `turn://` protocol internally to the request.

6. **Port opening/forwarding**\
   - The TURN server on `<yourChosenPortNumber>` needs to be accessible for all Talk participants, so you need to open it to the web and if your TURN server is running **behind a NAT**, forward it to the related machine.

### **What else**
Nextcloud Talk is still based on the Spreed video calls app (just got renamed) and thus the [Spreed.ME](https://www.spreed.me/) WebRTC solution. For this reason, all guides about how to configure coTURN for one of them, applies to all of them.

If you need to use Talk with more than 5-10 users, you will need the Spreed High Performance Back-end from Nextcloud GmbH. See https://nextcloud.com/talk/ for details.

**Further reference**
- https://github.com/spreedbox/spreedbox/wiki/Use-TURN-server
- https://github.com/nextcloud/spreed/issues/667
