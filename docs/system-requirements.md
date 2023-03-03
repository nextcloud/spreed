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

### WebAssembly and TensorFlow Lite files

Since Talk 13 the web server needs to handle WebAssembly (.wasm) and TensorFlow Lite (.tflite) files in a similar way to JavaScript and CSS files, as they will be requested by Talk clients to provide certain features (for example, the background blur when Talk is running in a browser). If Apache is used the default configuration provided by the Nextcloud server should be enough; if NGINX is used please refer to the [_NGINX configuration_ section in Nextcloud Administration manual](https://docs.nextcloud.com/server/stable/admin_manual/installation/nginx.html).

Besides that the web server should associate _.wasm_ files with the right MIME type. This is not strictly needed, though, but if they are not the browser console may show a warning similar to:
```
wasm streaming compile failed: TypeError: WebAssembly: Response has unsupported MIME type '' expected 'application/wasm'
falling back to ArrayBuffer instantiation
```

In Apache, if _mod_mime_ and _.htaccess_ files are enabled, the default _.htaccess_ file in Nextcloud server associates the _application/wasm_ MIME type with _.wasm_ files. Alternatively the association can be done by adding `AddType application/wasm .wasm` in _/etc/apache2/mods-enabled/mime.conf_, or `application/wasm wasm` to _/etc/mime.types_. Similarly, the default configuration for NGINX does the association too, but alternatively it can be done by adding `application/wasm wasm;` to _/etc/nginx/mime.types_.

## TURN server

A TURN server running on **port 443** (or 80) is required in almost all scenarios, see  [Configuring coTURN](TURN.md) for more details.
