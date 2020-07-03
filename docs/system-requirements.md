# Server

Nextcloud Talk will significantly increase load on your server, depending on the number of concurrent active participants - no matter whether they use audio call, video call or just the chat. This is due to required signalling and message pulling. By default Talk will use its integrated signalling server, which works just fine for small and medium installations. If you're running Talk on a dedicated or decent virtual server, you shouldn't experience any problems. However, if you're running Talk on a shared webhoster, you might indeed experience issues. Also note that many shared webhosters particularly prohibit running chat server software like Nextcloud Talk; please make sure to check your provider's Terms & Conditions. In this case you might be required to upgrade your package.

Additional to normal Nextcloud requirements the following constraints apply to use Nextcloud Talk:

## HTTPS
 
HTTPS is required to be able to use WebRTC (the video call technology of browsers used by Nextcloud Talk calls).

## Database

* SQLite: must not be used, to grant a decent experience for chats and calls
* MySQL/Maria DB: Must enable utf8mb4 support as per documentation at [Enabling MySQL 4-byte support](https://docs.nextcloud.com/server/latest/admin_manual/configuration_database/mysql_4byte_support.html)

## Webserver

Apache and Nginx must use:

* PHP FPM + mpm_events or
* PHP + mpm_prefork

Other combinations will not work due to the long polling used for chat and signaling messages, see [this issue](https://github.com/nextcloud/spreed/issues/2211#issuecomment-610198026) for details.

## TURN server

A TURN server running on **port 443** (or 80) is required in almost all scenarios, see  [Configuring coTURN](TURN.md) for more details.

# Browsers

## Recommended

* Firefox: latest
* Chrome/Chromium: latest

## Supported

* Firefox / Firefox ESR: 52 or later
* Chrome / Chromium: 49 or later
* Edge: latest
* Safari: 13 or later

# Mobile apps

* Android: 5 or later
* iOS: 10 or later
