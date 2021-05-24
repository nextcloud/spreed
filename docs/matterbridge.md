# Matterbridge integration

While we tried to keep the setup and integration as smooth as possible, we also wanted to make sure you can get fixes on the Matterbridge as soon as possible and don't have to wait until we made a new Talk release available. Therefore, we have put the [Matterbridge binaries from 42wim](https://github.com/42wim/matterbridge) into a separate app in the [App Store](https://apps.nextcloud.com/apps/talk_matterbridge). This also helps to keep the normal Talk package at a reasonable size as the download of the binaries is around 120 MB.

When this app is used, the basic configuration should work out of the box.

## Use a custom Matterbridge binary

In case you need a non-default binary (currently we ship 32-bit, 64-bit and arm-64 for linux) or want to check a different version of Matterbridge, you can also download the Matterbridge binary directly from [42wim's repo](https://github.com/42wim/matterbridge).
You then manually set the path for the Matterbridge binary by changing the `matterbridge_binary` spreed app config key:
```
occ config:app:set spreed matterbridge_binary --value="/absolute/path/to/binary"
```

Afterwards the Matterbridge version should be shown in the admin settings of Nextcloud Talk, confirming that it works and allowing you to configure it in the individual chats in which you are a moderator.
