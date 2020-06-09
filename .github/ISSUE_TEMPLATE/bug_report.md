---
name: Bug report
about: Create a report to help us improve
title: ''
labels: 0. Needs triage, bug
assignees: ''

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

**Talk app version:** (see apps admin page: `/index.php/settings/apps`)

**Custom Signaling server configured:** yes/no (see additional admin settings: `/index.php/settings/admin/additional`)

**Custom TURN server configured:** yes/no (see additional admin settings: `/index.php/settings/admin/additional`)

**Custom STUN server configured:** yes/no (see additional admin settings: `/index.php/settings/admin/additional`)


## Browser

**Microphone available:** yes/no

**Camera available:** yes/no

**Operating system:** Windows/Ubuntu/...

**Browser name:** Firefox/Chrome/...

**Browser version:** 50.1/55/...

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

**PHP version:**

**Nextcloud Version:** (see admin page)

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
