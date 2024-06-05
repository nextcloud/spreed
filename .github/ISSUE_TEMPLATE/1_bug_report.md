---
name: 🐞 Bug report
about: Help us to improve by reporting a bug
labels: 0. Needs triage, bug
---

<!--- Please keep this note for other contributors -->

### How to use GitHub

* Please use the 👍 [reaction](https://blog.github.com/2016-03-10-add-reactions-to-pull-requests-issues-and-comments/) to show that you are affected by the same issue.
* Please don't comment if you have no relevant information to add. It's just extra noise for everyone subscribed to this issue.
* Subscribe to receive notifications on status change and new comments.

---

## Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen

### Actual behaviour
Tell us what happens instead

## Talk app

**Talk app version:** (see apps administration page: `/index.php/settings/apps`)

**Custom Signaling server configured:** yes/no and version (see  Talk administration settings: `/index.php/settings/admin/talk#signaling_server`)

**Custom TURN server configured:** yes/no (see Talk administration settings: `/index.php/settings/admin/talk#turn_server`)

**Custom STUN server configured:** yes/no (see  Talk administration settings: `/index.php/settings/admin/talk#stun_server`)


## Browser

**Microphone available:** yes/no

**Camera available:** yes/no

**Operating system:** Windows/Ubuntu/Mac/...

**Browser name:** Firefox/Chrome/Safari/...

**Browser version:** 124/125/...

### Browser log

<details>
```
Insert your browser log here, this could for example include:
a) The javascript console log
b) The network log
c) ...
```

</details>

## Server configuration
<!--
You can use the Issue Template application to prefill most of the required information: https://apps.nextcloud.com/apps/issuetemplate
-->


**Operating system**: Ubuntu/RedHat/...

**Web server:** Apache/Nginx

**Database:** MySQL/Maria/SQLite/PostgreSQL

**PHP version:** 8.1/8.2/8.3

**Nextcloud Version:** (see administration page)

**List of activated apps:**

<details>

```
If you have access to your command line run e.g.:
sudo -u www-data php occ app:list
from within your server installation folder
```
</details>

**Nextcloud configuration:**

<details>

```
If you have access to your command line run e.g.:
sudo -u www-data php occ config:list system
from within your Nextcloud installation folder
```
</details>

### Server log (data/nextcloud.log)
<details>

```
Insert your server log here
```
</details>
