---
title: Constants
---

## Conversation types
* `1` "one to one"
* `2` group
* `3` public
* `4` changelog

## Read-only states
* `0` read-write
* `1` read-only

## Participant types
* `1` owner
* `2` moderator
* `3` user
* `4` guest
* `5` user following a public link
* `6` guest with moderator permissions

## Actor types of chat messages
* `guests` - guest users
* `users` - logged-in users
* `bots` - used by commands (actor-id is the used `/command`) and the changelog conversation (actor-id is `changelog`)

## Webinary lobby states
* `0` all participants
* `1` moderators only
