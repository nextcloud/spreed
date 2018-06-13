# Nextcloud Talk

**Video- & audio-conferencing app for Nextcloud**

![](https://raw.githubusercontent.com/nextcloud/spreed/master/docs/call-in-action.png)

## Why is this so awesome?

* :speech_balloon: **Chat integration!** Nextcloud Talk comes with some simple text chat since Nextcloud 13. More features are planned for future versions.
* :busts_in_silhouette: **Private, group, public and password protected calls!** Just invite somebody, a whole group or send a public link to invite to a call.
* :computer: **Screen sharing!** Share your screen with participants of your call. You just need to use Firefox version 52 (or newer) or Chrome with this [Chrome extension](https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol).
* :rocket: **Integration with other Nextcloud apps!** Currently Contacts and users – more to come.
* :see_no_evil: **We’re not reinventing the wheel!** Based on the great [simpleWebRTC](https://simplewebrtc.com/) library.

And in the works for the [coming versions](https://github.com/nextcloud/spreed/milestones/):
* :raising_hand: [Federated calls](https://github.com/nextcloud/spreed/issues/21), to call people on other Nextclouds

If you have suggestions or problems, please [open an issue](https://github.com/nextcloud/spreed/issues) or contribute directly :)


### Installing

There are several ways of installing Talk. If you just need to enable local access it's enough to just enable the app from the Nextcloud App Store. 

If you need to use Talk from outside your own LAN, or through a strict firewall you need to install and setup a TURN server. That's a bit more tricky, but the guys from [Nextcloud VM](https://github.com/nextcloud/vm) has developed a script which takes care of everything for you. You can find the script [here](https://github.com/nextcloud/vm/blob/master/apps/talk.sh). The script is tested on Ubuntu Server 18.04, but should work on 16.04 as well. Please keep in mind that it's developed for the VM specifically and any issues should be reported in that repo, not here.

**Here's a short video on how it's done:**<br>
[![Install Talk on Nextcloud](https://lh3.googleusercontent.com/crCv9cBtaOz-5BqpXp0Dhxjq3kyh5rbg0oKx2_BlCwZe2i3nuGhkK2zIzzdCMXFVal8=s180)](https://youtu.be/KdTsWIy4eN0)

## Disabling internal camera/audio for testing

* Disable camera until reboot: `sudo modprobe -r uvcvideo`
* Re-enable camera: `sudo modprobe uvcvideo`

## Contribution Guidelines

Please read the [Code of Conduct](https://nextcloud.com/community/code-of-conduct/). This document offers some guidance to ensure Nextcloud participants can cooperate effectively in a positive and inspiring atmosphere, and to explain how together we can strengthen and support each other.

For more information please review the [guidelines for contributing](https://github.com/nextcloud/server/blob/master/CONTRIBUTING.md) to this repository.

### Apply a license

All contributions to this repository are considered to be licensed under
the GNU AGPLv3 or any later version.

Contributors to the Spreed app retain their copyright. Therefore we recommend
to add following line to the header of a file, if you changed it substantially:

```
@copyright Copyright (c) <year>, <your name> (<your email address>)
```

For further information on how to add or update the license header correctly please have a look at [our licensing HowTo][applyalicense].

### Sign your work

We use the Developer Certificate of Origin (DCO) as a additional safeguard
for the Nextcloud project. This is a well established and widely used
mechanism to assure contributors have confirmed their right to license
their contribution under the project's license.
Please read [developer-certificate-of-origin][dcofile].
If you can certify it, then just add a line to every git commit message:

````
  Signed-off-by: Random J Developer <random@developer.example.org>
````

Use your real name (sorry, no pseudonyms or anonymous contributions).
If you set your `user.name` and `user.email` git configs, you can sign your
commit automatically with `git commit -s`. You can also use git [aliases](https://git-scm.com/book/tr/v2/Git-Basics-Git-Aliases)
like `git config --global alias.ci 'commit -s'`. Now you can commit with
`git ci` and the commit will be signed.

[dcofile]: https://github.com/nextcloud/server/blob/master/contribute/developer-certificate-of-origin
[applyalicense]: https://github.com/nextcloud/server/blob/master/contribute/HowToApplyALicense.md
