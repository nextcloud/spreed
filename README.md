# Spreed video calls

📞😀 Video- & audio-conferencing app for Nextcloud

![](https://raw.githubusercontent.com/nextcloud/spreed/master/docs/spreed-in-action.png)

## Why is this so awesome?

* :busts_in_silhouette: **1on1 & group calls!** Just invite people, or create a call with a whole group. :)
* :link: **Public rooms!** You can send a link to anyone to have a call with them!
* :rocket: **Integration with other Nextcloud apps!** Currently Contacts and users – more to come.
* :see_no_evil: **We’re not reinventing the wheel!** Based on the great [simpleWebRTC](https://simplewebrtc.com/) library.

And in the works for the [coming versions](https://github.com/nextcloud/spreed/milestones/):
* :speech_balloon: [Chat integration](https://github.com/nextcloud/spreed/issues/35)
* :raising_hand: [Federated calls](https://github.com/nextcloud/spreed/issues/21), to call people on other Nextclouds
* :zap: If you have suggestions or problems, please [open an issue](https://github.com/nextcloud/spreed/issues) or contribute directly :)

## Contribution Guidelines

Please read the [Code of Conduct](https://nextcloud.com/community/code-of-conduct/). This document offers some guidance to ensure Nextcloud participants can cooperate effectively in a positive and inspiring atmosphere, and to explain how together we can strengthen and support each other.

For more information please review the [guidelines for contributing](https://github.com/nextcloud/server/blob/master/CONTRIBUTING.md) to this repository.

### Apply a license

All contributions to this repository are considered to be licensed under
the GNU AGPLv3 or any later version.

Contributors to the Spreed.me app retain their copyright. Therefore we recommend
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
