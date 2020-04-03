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

There are some scenarios (users behind strict firewalls / symmetric NATs) where a TURN server is needed. That's a bit more tricky to install. You can [find instructions in our documentation](https://nextcloud-talk.readthedocs.io/en/latest/TURN/) and the team behind the Nextcloud VM has developed a script which takes care of everything for you ([vm-talk.sh](https://github.com/nextcloud/vm/blob/master/apps/talk.sh)). The script is tested on Ubuntu Server 18.04, but should work on 16.04 as well. Please keep in mind that it's developed for the VM specifically and any issues should be reported in that repo, not here.

Here's a short [video](https://youtu.be/KdTsWIy4eN0) on how it's done.

## Scalability 

Talk works peer to peer, that is, each participant sends an end-to-end encrypted stream to each other participant and receives one stream per other participant. This grows bandwidth usage with the number of participants. As most users are on an asymetric local internet connection, the sending bandwidth often becomes the bottleneck. A typical Nextcloud Talk instance can handle a few dozen calls, but each call can have only 4-6 participants with video depending on the participants' bandwidth. Without video, more is possible.

A single video stream currently uses about 1 mbit/sec and the total required bandwidth can be calculated as follows:

```
1 mbit/s * (participants - 1)
```

![](https://nextcloud.com/wp-content/themes/next/assets/img/features/HPB-P2P.svg.png)

This means that in a call with 5 participants, each has to send and receive about 4 mbit/sec. Given the asymetric nature of most typical broadband connections, it is sending video that quickly becomes the bottleneck. Moreover, decoding all those video streams put a big strain on the system of each participant.

To limit and CPU bandwidth usage, participants can disable video. This will drop the bandwidth use to audio only (about 50 kbit/sec), about 1/20th of the bandwidth of video, and eliminates most decoding work. When all participants are on a fast network, a call with 20 people without video could be doable.

Still a call creates a load on the members' browsers (decoding streams) and on the server as it handles signaling. This, for example, has consequences also for the devices that support calls. Mobile device browsers will sooner run out of compute capacity and cause issues to the call. While we continously work to optimize Talk for performance, there is still work to be done so it is not unlikely that the bottleneck will be there for the time being. We very much welcome help in optimization of calls!

### How to have the maximum number of participants in a call

To make sure a call can sustain the largest number of participants, make sure that:
* each participant has a fast upload and download
* each participant has a fast enough system (desktop/laptop browser, mobile device browsers will run out of computing power quickly) or uses the Android/iOS app. Best use a desktop browser like Firefox or Chrome. The WebRTC implementation in other browsers is often sub-par.
* each participant disables video

With this setup, 20 users should be doable in a typical setup.

### Scaling beyond 5-20 users in a call

Nextcloud offers a partner product, the Talk High Performance Back-end, which deals with this scalability issue by including a Selective Forwarding Unit (SFU). Each participant sends one stream to the SFU which distributes it under the participants. This typically scales to 30-50 or even more active participants. Further more, the HPB setup also allows calls with hundreds of passive participants. With this number of participants is only limited by the bandwidth of the SFU setup. This is ideal for one-to-many streaming like webinars or remote teaching lessons.

The HPB also takes care of signaling, decreasing the load of many calls on the Talk server and optional SIP integration so users can dial in to calls by phone.

If you need to use Talk in an enterprise environment, [contact our sales team](https://nextcloud.com/enterprise/buy/) for access to the high performance back-end. See our website for more details and [pricing](https://nextcloud.com/talk/#scalability).

## Development setup

1. Simply clone this repository into the `apps` folder of your Nextcloud development instance.
2. Run `make dev-setup` to install the dependencies;
3. Run `make build-js`
4. Then activate it through the apps management. :tada:
5. To build the docs locally, install mkdocs locally: `apt install mkdocs mkdocs-bootstrap`

We are also available on [our public Talk team conversation](https://cloud.nextcloud.com/call/c7fz9qpr), if you want to join the discussion.

### API documentation

The API documentation is available at https://nextcloud-talk.readthedocs.io/en/latest/

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


### Useful tricks for testing

* Disable camera until reboot: `sudo modprobe -r uvcvideo`
* Re-enable camera: `sudo modprobe uvcvideo`
* Send fake-stream (audio and video) in firefox:
  1. Open `about:config`
  2. Search for `fake`
  3. Toggle `media.navigator.streams.fake` to **true**


## Contribution Guidelines

For more information please see the [guidelines for contributing](https://github.com/nextcloud/spreed/blob/master/.github/contributing.md) to this repository.
