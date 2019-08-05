# Nextcloud Talk

**Video- & audio-conferencing app for Nextcloud**

![](https://raw.githubusercontent.com/nextcloud/spreed/master/docs/call-in-action.png)

## Why is this so awesome?

* 💬 **Chat integration!** Nextcloud Talk comes with a simple text chat. Allowing you to share files from your Nextcloud and mentioning other participants.
* 👥 **Private, group, public and password protected calls!** Just invite somebody, a whole group or send a public link to invite to a call.
* 💻 **Screen sharing!** Share your screen with participants of your call. You just need to use Firefox version 52 (or newer), latest Edge or Chrome 49 (or newer) with this [Chrome extension](https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol).
* 🚀 **Integration with other Nextcloud apps** like Files, Contacts and Deck. More to come.
* 🙈 **We’re not reinventing the wheel!** Based on the great [simpleWebRTC](https://simplewebrtc.com/) library.

And in the works for the [coming versions](https://github.com/nextcloud/spreed/milestones/):
* 🙋 [Federated calls](https://github.com/nextcloud/spreed/issues/21), to call people on other Nextclouds

If you have suggestions or problems, please [open an issue](https://github.com/nextcloud/spreed/issues) or contribute directly :)

### Supported Browsers

| Browser | Compatible |
|---|---|
| Firefox | ✔️ 52 or later |
| Chrome/Chromium | ✔️ 49 or later |
| Edge | ⚠️ latest versions <br> 🎤 Speakers are not promoted <br> 🏷 Name changes while a call is on-going are not reflected |
| Safari | ⚠️ 12 or later <br> ❌ No screensharing support <br> 🖥 Viewing screens of others works |


## Installing for Production

Nextcloud Talk is really easy to install. You just need to enable the app from the [Nextcloud App Store](https://apps.nextcloud.com/apps/spreed) and everything will work out of the box.

There are some scenarios (users behind strict firewalls / symmetric NATs) where a TURN server is needed. That's a bit more tricky installation, but the guys from [Nextcloud VM](https://github.com/nextcloud/vm) have developed a script which takes care of everything for you. You can find the script [here](https://github.com/nextcloud/vm/blob/master/apps/talk.sh). The script is tested on Ubuntu Server 18.04, but should work on 16.04 as well. Please keep in mind that it's developed for the VM specifically and any issues should be reported in that repo, not here.

Here's a short [video](https://youtu.be/KdTsWIy4eN0) on how it's done.


## Development setup

1. Simply clone this repository into the `apps` folder of your Nextcloud development instance.
2. Run `make dev-setup` to install the dependencies;
3. Run `make build-js`
4. Then activate it through the apps management. :tada:

We are also available on [Freenode IRC #nextcloud-talk](https://webchat.freenode.net/?channels=nextcloud-talk), if you want to join the discussion.


### Milestones and Branches

#### Branches

In the Talk app we have one branch per Nextcloud server version. stable* branches of the app should always work with the same branch of the Nextcloud server.
This is only off close to releases of the server, to allow easier finishing of features, so we don't have to backport them.

#### Milestones

* 5.0.0 - **Numeric** milestones are settled and waiting for their release or some final polishing
* 💙 Next Minor (15) - The **next minor** milestone is for issues/PR that go into the next Dot-Release for the given Nextcloud version (in the example 15 - e.g. 5.0.1)
* 💚 Next Major - The **next major** milestone is for issues/PR that go into the next feature release for the new Major Nextcloud version (as there are Minors for 15, this would be 16)
* 💛 Following Major - The **following major** milestone is for issues/PR that should be worked towards/on but didn't make it into the next major due to timing constraints
* 💔 Backlog - The **backlog** milestone is assigned to all remaining issues

You can always pick a task of any of the milestones and we will help you to get it into the assigned milestone or also an earlier one if time permits. It's just a matter of having an overview and better visibility what we think should be worked on, but it's not exclusive.


### Disabling internal camera/audio for testing

* Disable camera until reboot: `sudo modprobe -r uvcvideo`
* Re-enable camera: `sudo modprobe uvcvideo`


## Contribution Guidelines

For more information please see the [guidelines for contributing](https://github.com/nextcloud/spreed/blob/master/.github/contributing.md) to this repository.
