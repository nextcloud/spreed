# Quick install

## Introduction

Installing Nextcloud Talk consists of two main parts:

1. **App:** The Nextcloud Server app available in the [Nextcloud app store](https://apps.nextcloud.com/apps/spreed).
2. **High-performance backend:** The High-performance backend developed by our Partner Struktur AG available in [their GitHub organisation](https://github.com/strukturag/nextcloud-spreed-signaling). The High-performance backend itself consists of multiple modules, the most important ones being a signaling server and a WebRTC media gateway.

!!! note

	While the High-performance backend is **_optional_**, please note, that it is already **helpful in calls with three participants** (reducing required upload bandwidth by 50%). Even more so in calls with 4+ participants as described in the [Scalability documentation](scalability.md).

	Besides performance aspects, some features (e.g. typing indicators) require a High-performance backend as well.

Additional components:

- Most installations require a STUN and TURN server to ensure people can join calls from limited networks and mobile networks.
	- **STUN server:** Resolves public IP addresses of participants. A STUN server is provided by default, but you can replace it with your own and e.g. coTURN brings it by default.
	- **TURN server:** Relays the audio and video data through firewalls and port restrictions. For a quick guide on TURN, please find the relevant information in our [TURN documentation](TURN.md).
- **Recording backend:** A Recording backend to allow recording audio and video calls. See the [Recording backend documentation](https://github.com/nextcloud/nextcloud-talk-recording/blob/main/docs/installation.md) for detailed steps.
- **SIP bridge:** Available with a Nextcloud Talk Enterprise subscription, the SIP bridge allows participating in meetings via phone as well as calling phone numbers from within Nextcloud Talk. More details are available in the [Nextcloud Enterprise Portal](https://portal.nextcloud.com/article/Nextcloud-Talk/High-Performance-Backend/Installation/Setup-Instructions#content-sip-bridge)

## Installation types

This guide explains the steps and instructions required to install the High-performance backend using Docker and how to quickly configure the basics to get started.

In general, the following installation methods are available

### Nextcloud All-in-One

* **Requirements:** Docker
* **Link:** [github.com/nextcloud/all-in-one](https://github.com/nextcloud/all-in-one)

Nextcloud All-in-One (AIO) is the official Nextcloud installation method. It provides easy deployment and maintanence with most features of Nextcloud already included.

For Nextcloud Talk this means that Nextcloud AIO already includes a ready to use High-performance backend and TURN server.

### Packages

* **Requirements:**: Nextcloud Talk subscription
* **Link:** [Nextcloud Enterprise Portal](https://portal.nextcloud.com/article/Nextcloud-Talk/High-Performance-Backend/Installation-of-Nextcloud-Talk-High-Performance-Backend)

Packages for installing the High-performance backend for Ubuntu, Debian und Red Hat Enterprise Linux are available with a Nextcloud Talk subscription. Additionally, extensive documentation for very large installation, e.g. clustered setups, is available there.

### "How to" from the Nextcloud snap team / Docker Stack

* **Requirements:** Docker
* **Link:** [github.com/nextcloud-snap/nextcloud-snap/wiki/How-to-configure-talk-HPB-with-Docker](https://github.com/nextcloud-snap/nextcloud-snap/wiki/How-to-configure-talk-HPB-with-Docker)

Thanks to the Nextcloud snap team, who created a [how to](https://github.com/nextcloud-snap/nextcloud-snap/wiki/How-to-configure-talk-HPB-with-Docker) for installing the High-performance backend using the Talk container from Nextcloud AIO in a Docker Stack. While provided "as-is" it might help you to setup the High-performance backend for your use-case. Please note that it does not require a Nextcloud snap installation! 

### Docker container

* **Requirements:** Docker
* **Link:** _Described in detail below_

Similar to the approach above, we utilize the Nextcloud AIO container to get the High-performance backend up and running.

## System requirements

* A working docker installation
    * Checkout [the docker installation guide](https://docs.docker.com/engine/install/)

## Before you begin

Before installing the High performance backend, ensure you have:

* A working Nextcloud installation.
    * If you don't have a working Nextcloud installation yet, please checkout [Nextcloud All-in-One](https://github.com/nextcloud/all-in-one) or alteratively our [installation documentation](https://docs.nextcloud.com/server/latest/admin_manual/installation/index.html).
* Nextcloud Talk installed from the [Nextcloud app store](https://apps.nextcloud.com/apps/spreed).
    * If you need help installing Nextcloud Talk, please check out [our documentation on apps management](https://docs.nextcloud.com/server/latest/admin_manual/apps_management.html).
* A correctly setup reverse proxy for your Nextcloud installation
    * See [our documentation on reverse proxy](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/reverse_proxy_configuration.html).

## Installation steps

The following procedure explains how to get the High-performance backend up and running with a standalone docker container. It will utilize the pre-built container from Nextcloud All-in-One. 

For more information on how Nextcloud All-in-One builds the container, please [see the Nextcloud AIO repository](https://github.com/nextcloud/all-in-one/tree/main/Containers/talk).

### Step 1 - Create secrets

The High-performance backend requires multiple secrets for authentication with the Nextcloud instance. The talk container from Nextcloud All-in-One needs 3 secrets to work correctly:

* A `TURN` secret
* A `SIGNALING` secret
* A `INTERNAL` secret

While you specify the secrets manually, it is recommended to generate a secure random string. There are multiple ways to do that, one of the easiest is using `openssl`:

```shell
# openssl rand --hex 32
86a057a959534eb761b20afb4122da6b5475a5f8db20ba16abbb91c65132ca03
```

Repeat that another 2 times to have all 3 secrets.

For the following steps we will refer to the secrets as:

* `TURN` = 1111
* `SIGNALING` = 2222
* `INTERNAL` = 3333

Please make sure to replace the values accordingly.

!!! note

	While the talk container from Nextcloud AIO also contains a TURN server (and requires a TURN secret), this guide soley focuses on the HPB part.

### Step 2 - Start the docker container

The following command will start the container, please adjust the command to your needs. In addition to the generated secrets, you'll also need the domain of your Nextcloud installation and the port where the signaling server will listen.

```shell
docker run \
  --name=nextcloud-talk-hpb \
  --restart=always \
  --detach \
  -e NC_DOMAIN=<your_domain> \
  -e TALK_PORT=3478 \
  -e TURN_SECRET=<your_turn_secret> \
  -e SIGNALING_SECRET=<your_signaling_secret> \
  -e INTERNAL_SECRET=<your_internal_secret> \
  -p <your_port>:8081 \
  nextcloud/aio-talk:latest
```

Example:

```shell
docker run \
  --name=nextcloud-talk-hpb \
  --restart=always \
  --detach \
  -e NC_DOMAIN=nextcloud.domain.invalid \
  -e TALK_PORT=3478 \
  -e TURN_SECRET=1111 \
  -e SIGNALING_SECRET=2222 \
  -e INTERNAL_SECRET=3333 \
  -p 8080:8081 \
  nextcloud/aio-talk:latest
```

### Step 3 - Expose the container through the reverse proxy

The container needs to be exposed through the reverse proxy that does TLS offloading, e.g. as `signaling.domain.invalid`. Please checkout the example configs in [the nextcloud-spreed-signaling repository](https://github.com/strukturag/nextcloud-spreed-signaling?tab=readme-ov-file#setup-of-frontend-webserver).

## Verify installation

After finishing all the installation steps, please verify if the signaling server is correctly reachable. You can use query the welcome endpoint for that:

```shell
# curl https://signaling.domain.invalid/api/v1/welcome
{"nextcloud-spreed-signaling":"Welcome","version":"2.0.1~docker"}
```

If you would like to verify it locally from the docker host, you can query the welcome endpoint as well:

```shell
# curl http://localhost:8081/api/v1/welcome
{"nextcloud-spreed-signaling":"Welcome","version":"2.0.1~docker"}
```

Also to show the logs of the docker container, use `docker logs nextcloud-talk-hpb`.

## Post installation

After successfully starting the container, please go to the administrative settings of Talk (`/settings/admin/talk`) to configure the High-performance backend with the following parameters:

* High-performance backend URL: `https://signaling.domain.invalid`
* Shared secret: `2222` (The generated `SIGNALING` secret)

Verify that the connection check succeeds with a green checkmark.

### TURN option

In case you would like to use the TURN option that the Nextcloud AIO container provides, you can modify the docker command like this:

```shell
docker run \
  --name=nextcloud-talk-hpb \
  --restart=always \
  --detach \
  -e NC_DOMAIN=<your_domain> \
  -e TALK_HOST=<your_signaling_domain> \
  -e TALK_PORT=3478 \
  -e TURN_SECRET=<your_turn_secret> \
  -e SIGNALING_SECRET=<your_signaling_secret> \
  -e INTERNAL_SECRET=<your_internal_secret> \
  -p <your_port>:8081 \
  -p 3478:3478/tcp \
  -p 3478:3478/udp \
  nextcloud/aio-talk:latest
```

Note that we added the environment variable `TALK_HOST` and additionally exposed port TCP 3478 and UDP ports. The domain entered needs to be the one where the TURN server will be reachable on port 3478.

### TURN post installation

After successfully starting the container, please go to the administrative settings of Talk (`/settings/admin/talk`) to configure the TURN server with the following parameters:

* `turn: only`
* TURN server URL: `signaling.domain.invalid:3478` (no protocol)
* TURN server secret: `1111` (The generated `TURN` secret)
* `UDP and TCP`
