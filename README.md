<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Nextcloud Talk

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/spreed)](https://api.reuse.software/info/github.com/nextcloud/spreed)

**A video & audio conferencing app for Nextcloud**

| Elevator                                              |
|-------------------------------------------------------|
| [✨ Why is this so awesome?](#-why-is-this-so-awesome) |
| [📚 Documentation](#-documentation)                   |
| [🚧 Development Setup](#-development-setup)           |


![](https://raw.githubusercontent.com/nextcloud/spreed/main/docs/call-in-action.jpg)

## ✨ Why is this so awesome?

* 💬 **Chat** Nextcloud Talk comes with a simple text chat, allowing you to share or upload files from your Nextcloud Files app or local device and mention other participants.
* 👥 **Private, group, public and password protected calls!** Invite someone, a whole group or send a public link to invite to a call.
* 🌐 **Federated chats** Chat with other Nextcloud users on their servers
* 💻 **Screen sharing!** Share your screen with the participants of your call.
* 🚀 **Integration with other Nextcloud apps** like Files, Calendar, User status, Dashboard, Flow, Maps, Smart picker, Contacts, Deck, and many more.
* 🌉 **Sync with other chat solutions** With [Matterbridge](https://github.com/42wim/matterbridge/) being integrated in Talk, you can easily sync a lot of other chat solutions to Nextcloud Talk and vice-versa.

More in the works for the [coming versions](https://github.com/nextcloud/spreed/milestones/).

If you have suggestions or problems, please [open an issue](https://github.com/nextcloud/spreed/issues) or contribute directly 🤓

---

## 📚 Documentation

* **[👤 User system requirements](https://nextcloud-talk.readthedocs.io/en/latest/user-requirements/)**
* **[📙 User documentation](https://docs.nextcloud.com/server/latest/user_manual/en/talk/index.html)**
* **[💻 Server system requirements](https://nextcloud-talk.readthedocs.io/en/latest/system-requirements/)**
* **[📗 Administration documentation](https://nextcloud-talk.readthedocs.io/en/latest/#administration-documentation)**
* **[🤖 Bots/Webhooks documentation](https://nextcloud-talk.readthedocs.io/en/latest/bots/)**
* **[⚙️ API documentation](https://nextcloud-talk.readthedocs.io/en/latest/#talk-api)**

### 📦 Installing for Production

Nextcloud Talk is really easy to install. You just need to enable the app from the [Nextcloud App Store](https://apps.nextcloud.com/apps/spreed) and everything will work out of the box.

There are some scenarios (users behind strict firewalls / symmetric NATs) where a TURN server is needed. That's a bit more tricky to install. You can [find instructions in our documentation](https://nextcloud-talk.readthedocs.io/en/latest/TURN/) and the team behind the Nextcloud VM has developed a script that takes care of everything for you ([vm-talk.sh](https://github.com/nextcloud/vm/blob/master/apps/talk.sh)). The script is tested on the recent Ubuntu Server LTS. Please keep in mind that it's developed for the VM specifically and any issues should be reported in that repository, not here.

Here's a short [video](https://youtu.be/KdTsWIy4eN0) on how it's done.

---

## 🚧 Development Setup

1. Simply clone this repository into the `apps` folder of your Nextcloud development instance.
2. Run `make dev-setup` to install the dependencies.
3. Run `make build-js`.
4. Then activate it through the apps management. 🎉
5. To build the docs locally, install mkdocs locally: `apt install mkdocs mkdocs-bootstrap`.

Also see our **[step by step guide](https://nextcloud-talk.readthedocs.io/en/latest/developer-setup/)** on how to set up a full development environment.

### 🏎️ Faster frontend developing with HMR

You can enable HMR (Hot module replacement) to avoid page reloads when working on the frontend:

1. Install and enable [`hmr_enabler` app](https://github.com/nextcloud/hmr_enabler)
2. Run `npm run serve`
3. Open the normal Nextcloud server URL (not the URL given by above command)

We are also available on [our public Talk team conversation](https://cloud.nextcloud.com/call/c7fz9qpr), if you want to join the discussion.

### 🙈 Ignore code style updates in git blame

```sh
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

### 🌏 Testing federation locally

When testing federated conversations locally, some additional steps might be needed,
to improve the behaviour and allowing the servers to talk to each others:

1. Allow self-signed certificates
	```shell
	occ config:system:set sharing.federation.allowSelfSignedCertificates --value true --type bool
	occ security:certificates:import /path/to/the/nextcloud.crt
	occ security:certificates
	```
2. Allow local servers to be remote servers
	```shell
	occ config:system:set allow_local_remote_servers --value true --type bool
	```

Additionally you can enable debug mode that will list local users as federated users options
allowing you to federate with accounts on the same instance. Federation will still work
and use the full federation experience and opposed to the federated files sharing **not**
create a local share instead.

### 🪄 Useful tricks for testing video calls

#### 👥 Joining a test call with multiple users

* Send fake-stream (audio and video) in Firefox:
	1. Open `about:config`
	2. Search for `fake`
	3. Toggle `media.navigator.streams.fake` to **true**
	4. Set `media.navigator.audio.fake_frequency` to **60** for more pleasant sound experience
* Afterwards install the [Firefox Multi-Account Containers](https://addons.mozilla.org/en-US/firefox/addon/multi-account-containers/) addon
* Now you can create multiple account containers, log in with a different Nextcloud account on each of them and join the same call with multiple different users

#### 📸 Modifying available media devices

* Disable camera until reboot: `sudo modprobe -r uvcvideo`
* Re-enable camera: `sudo modprobe uvcvideo`

### 🔃 Milestones and Branches

#### Branches

In the Talk app we have one branch per Nextcloud server version. `stable*` branches of the app should always work with the same branch of the Nextcloud server.
This is only off close to releases of the server, to allow easier finishing of features, so we don't have to backport them.

#### Milestones

* `v17.0.0` - *Numeric* milestones are settled and waiting for their release or some final polishing
* `💛 Next Patch (27)` - The **next patch** milestone is for issues/PR that go into the next Dot-Release for the given Nextcloud version (in the example 27 - e.g. 17.0.1)
* `💚 Next Major (28)` - The **next major** milestone is for issues/PR that go into the next feature release for the new Major Nextcloud version (as there are Patch releases for 27, this would be 28)
* `💔 Backlog` - The **backlog** milestone is assigned to all remaining issues

You can always pick a task of any milestone, and we will help you to get it into the assigned milestone or also an earlier one if time permits. It's just a matter of having an overview and better visibility what we think should be worked on, but it's not exclusive.

### 💙 Contribution Guidelines

For more information please see the [guidelines for contributing](https://github.com/nextcloud/spreed/blob/main/.github/contributing.md) to this repository.
