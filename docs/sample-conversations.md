# Sample conversations

In Hub 10 (Nextcloud 31) we introduced new sample conversations to better showcase various features of Nextcloud Talk. The default sample conversations are translated just like Nextcloud itself is, so it should be shown to the user in a language they understand.

## Custom samples

It is also possible to replace the sample conversations. For this you need to configure the `samples_directory` app config of the spreed app to point to a directory.
```shell
sudo -u www-data php /var/www/nextcloud/occ \
    config:app:set \
    spreed samples_directory \
    --value '/this/is/the/configured/path'
```

In the directory you put a subdirectory of each language that you want to support. A directory structure looks like the following:

```
/this/is/the/configured/path
  ├─ de/
  ├─ en/
  ├─ es/
  └─ fr/
```
The chosen language is picked from the user language, falling back to the `default_language` and can be overwritten with the `force_language` system config. It's also falling back from languages like `de_DE` to `de` if the first one is picked but does not exist.

In each of the languages you can have a unique set of sample Markdown files.

### Conversation information

The first section of the Markdown files must contain the following information, prefixed with the matching keyword:

```
NAME: Let's get started!
EMOJI: 💡
COLOR: #0082c9
---
```

End this segment with 3 dashes `---`.

Optionally you can have a description in the second section, indicated by the `DESCRIPTION:` marker as a beginning and ending again with `---`:

```markdown
DESCRIPTION:

This is a *sample* description! Including **Markdown** support.

---
```

Please note that descriptions are currently limited to 2.000 characters.

### Messages

Afterwards you can have unlimited amount of messages, each separated by `---`. Messages support markdown and mentions.

Additionally, the following additional features can be used:

- Reply: `{REPLY}` (at the start of the message) The message will be posted as a reply to the previous message
- Reactions: `{REACTION:👍}` (anywhere in the message) will be removed and a reaction with the given emoji will be down by the system
- File link: `{FILE:Readme.md}` (anywhere in the message) will be removed from the message and a link to the specified file inside the user's Files app root will be posted

## Disable samples

If you don't like the samples we provide, and you don't want to write your own samples, you can also disable them by setting the `create_samples` app config of the spreed app to false:
```shell
sudo -u www-data php /var/www/nextcloud/occ \
    config:app:set \
    spreed create_samples  \
    --value false --type boolean
```
