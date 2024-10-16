# Nextcloud Talk development setup

The following guide is intended to setup an environment for Nextcloud Talk development. It is _not_ intended to be used in production at any time.

## Preface

Nextcloud Talk delivers private audio/video conferencing and text chat through browser and mobile apps. It consists of several components, some of which are optional:

* **Nextcloud Server**: The core component of Nextcloud that offers file storage capabilities and the framework for building/running apps.

* **Nextcloud Talk Server**: The Nextcloud app that is the core of Nextcloud Talk. It consists of the backend (PHP) and a frontend (Vue.js) to use Nextcloud Talk directly in the web browser. **Important**: Due to legacy reasons, the app id of "talk" is "spreed"!

* **High performance backend**: _(optional)_ The high performance backend is an optional component that consists of a signaling server and a WebRTC gateway. It ensures that calls with more than 4 participants work smoothly. It is also required for features like typing indicators or federated calling to work properly.

* **Nextcloud Talk Recording Server**: _(optional)_ The Nextcloud Talk recording server is required when calls should be recorded on the server side. It takes care of the recording itself and uploading the result to Nextcloud.

* **Nextcloud Talk Android**: _(optional)_ Nextcloud Talk Android is the recommended way of using Nextcloud Talk on Android devices. Mostly written in Kotlin nowadays, it still contains some Java code.

* **Nextcloud Talk iOS**: _(optional)_ The recommended way of using Nextcloud Talk on your iOS devices. Major parts are still written in Objective-C while the transition to Swift is an ongoing effort.

## Prerequirements

There are many ways of running Nextcloud for development purposes. In the following guide we are using a docker based installation of Nextcloud. The following components are required:

* A working docker installation (see https://docs.docker.com/engine/install/)
* Node.js >= 20 and NPM >= 10 (optionally, see _Prepare Nextcoud Talk for development_ below for an alternative)
* PHP Composer (optionally, see _Prepare Nextcoud Talk for development_ below for an alternative)
* Git

## Install the development environment

Clone `nextcloud-docker-dev` in a directory of your choice:

```shell
cd /path/to/directory
git clone https://github.com/juliushaertl/nextcloud-docker-dev
cd nextcloud-docker-dev
```

`bootstrap.sh` is used to setup the enviroment, it supports multiple parameters that may depend on your development focus:

```shell
bootstrap.sh [--full-clone|--clone-no-blobs] [--clone-all-apps-filtered] [--] APPS

This command will initialize the debug environment for app developers.

The following options can be provided:

  --full-clone      Clone the server repository with the complete history included
  --clone-no-blobs  Clone the server repository with the history but omitting the
                    file contents. A network connection might be required if checking
                    out commits is done.
                    --full-clone and --clone-no-blobs is mutually exclusive.
  --clone-all-apps-filtered
                    Do not only reduce the history of the server repository but also
                    the cloned apps.

  APPS              The apps to add to the development setup on top of the default apps
```

#### Example 1: Latest `master/main` without history for server

Clones only the latest `master/main` from Nextcloud Server without history, but full history for all other apps, including Nextcloud Talk. This is perfectly fine if you plan on working with the latest version of Nextcloud / Nextcloud Talk.

```shell
./bootstrap.sh -- spreed
```

#### Example 2: Latest `master/main` with full history, but no blobs for server

Clones the full Nextcloud Server repository, but omitting file contents.  All other apps, including Nextcloud Talk are still fully cloned. This way you also have the full history of Nextcloud Server, without needing to download everything at once

```shell
./bootstrap.sh --clone-no-blobs -- spreed
```

#### Example 3: Latest `master/main` with full history (_Slow!_)

Clones the full Nextcloud Server repository and all apps with full history. This is the slowest option

```shell
./bootstrap.sh --full-clone -- spreed
```

### Install `mkcert` for self-signed certificate support

Nextcloud Talk needs camera and microphone access for audio/video conferencing. Some browsers do not allow access to these components, if the connection was not done in a secure way. Therefore we can use `mkcert` to have trusted self-signed certificates.

* Follow the installation instructions for your OS at https://github.com/FiloSottile/mkcert?tab=readme-ov-file#installation
* Execute `mkcert -install` create a new CA and trust it
* Execute `./scripts/update-certs` to automatically generate certificates for all possible containers used for development

### Prepare Nextcoud Talk for development

Enter the Nextcloud Talk directory and install the required components for development:

```shell
cd workspace/server/apps-extra/spreed
make dev-setup
```

If you do not have NPM, Node or Composer you can run all of them from inside the container instead (note that they will be run as root, so the file owner of the created files will be also root):
```shell
docker compose exec nextcloud bash --login -c "cd /var/www/html/apps-extra/spreed && make dev-setup"
```

## Start Nextcloud

In the directory of `nextcloud-docker-dev` execute the following:

```shell
docker compose up -d nextcloud
```

Enable Nextcloud Talk:

```shell
./scripts/occ.sh nextcloud -- app:enable spreed
```

After that you should be able to login with `admin` / `admin` at https://nextcloud.local

### Enable High Performance Backend

If you want to enable the HPB for your development setup, just execute

```
./scripts/enable-talk-hpb.sh nextcloud
```

to start the needed containers and add the configuration to the Nextcloud installation. You can check if everything is working in Settings -> Talk -> High Performance Backend.

### Enable Nextcloud Talk Recording Server

**Important:** Make sure that the HPB is correctly running!

Start the container of the recording server:

```
docker compose up -d talk-recording
```

Go to the admin settings of talk and add the recording server (`http://talk-recording.local` with shared secret `6789`)

### Use federation between 2 Nextcloud Servers

The provided `docker-compose.yml` file from `nextcloud-docker-dev` supports spinning up multiple instances out of the box. For the `master/main` branch, up to 3 instances can be started (e.g. `docker compose up -d nextcloud nextcloud2 nextcloud3`). This allows easy testing/development for federated scenarios.

1. Make sure to have 2 instances of Nextcloud running with

         docker compose up -d nextcloud nextcloud2

2. In case of federated calling, the HPB needs to be enabled on both instances

        ./scripts/enable-talk-hpb.sh nextcloud
        ./scripts/enable-talk-hpb.sh nextcloud2

3. Allow self-signed certs on both instances

        ./scripts/occ.sh nextcloud -- config:system:set sharing.federation.allowSelfSignedCertificates --value true --type bool
        ./scripts/occ.sh nextcloud2 -- config:system:set sharing.federation.allowSelfSignedCertificates --value true --type bool

4. Copy and import the certificates to the other instance

        docker compose cp data/ssl/nextcloud2.local.crt nextcloud:/tmp
        docker compose cp data/ssl/nextcloud.local.crt nextcloud2:/tmp

        ./scripts/occ.sh nextcloud2 -- security:certificates:import /tmp/nextcloud.local.crt
        ./scripts/occ.sh nextcloud -- security:certificates:import /tmp/nextcloud2.local.crt

5. Optional: Verify certs

        ./scripts/occ.sh nextcloud2 -- security:certificates
        ./scripts/occ.sh nextcloud -- security:certificates

6. Enable federation in the admin settings of Nextcloud Talk or alternatively via occ:

        ./scripts/occ.sh nextcloud -- config:app:set spreed federation_enabled --value yes
        ./scripts/occ.sh nextcloud2 -- config:app:set spreed federation_enabled --value yes

### Rebuild / update Talk after code changes

#### JavaScript

If you modify any JavaScript file, either directly or by switching to a different branch, you will need to rebuild them. You can do that with:

```shell
cd workspace/server/apps-extra/spreed
make build-js
```

For JavaScript development rather than manually rebuilding the files whenever you modify them you can instead run a watcher that will automatically rebuild them when they are changed:
```shell
cd workspace/server/apps-extra/spreed
make watch-js
```

Note, however, that in some cases the watcher may stop running (for example, if it gets killed by the system after some time due to its RAM consumption), so you might want to check it from time to time.

Also note that both `make build-js` and `make watch-js` are just wrappers for `npm run dev` and `npm run watch`, so you could run those commands instead if they feel more familiar.

No matter if you manually rebuilt the JavaScript files or if you use the watcher note that you will need to force refresh Talk in the browser, as otherwise the previous version of the built JavaScript files could be cached and used instead of the latest one.

Besides manually force refreshing the page you can disable the cache in the _Network_ tab of the developer tools of the browser, but keep in mind that it will only have effect if the developer tools are open (and the _Network_ tab might need to be open once after the developer tools were open, even if the cache was already disabled).

In the case of Firefox you can disable the cache in all cases by opening `about:config` and setting `browser.cache.disk.enable` and `browser.cache.memory.enable` to `false`.

If you do not have NPM or Node you can rebuild the JavaScript files from inside the container instead (like before, note that the file owner of the created files will be also root):
```shell
docker compose exec nextcloud bash --login -c "cd /var/www/html/apps-extra/spreed && make build-js"
```

Or, in the case of the watcher, with:
```shell
docker compose exec nextcloud bash --login -c "cd /var/www/html/apps-extra/spreed && make watch-js"
```

#### PHP dependencies

PHP files themselves do not need to be rebuilt, although if the PHP dependencies change they need to be updated. Note that this does not happen frequently, so in most cases you can switch to another branch without needing to update the PHP dependencies. Nevertheless, if the PHP dependencies changed (that is, if the `composer.lock` file changed) you can update them with:
```shell
cd workspace/server/apps-extra/spreed
make composer-install-dev
```

If you do not have Composer you can update the PHP dependencies from inside the container instead (like before, note that the file owner of the created files will be also root):
```shell
docker compose exec nextcloud bash --login -c "cd /var/www/html/apps-extra/spreed && make composer-install-dev"
```
