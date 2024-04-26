#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

###########################################
# High-performance backend log simplifier #
###########################################
#
# When modifying this script, please also upstream to:
# https://github.com/strukturag/nextcloud-spreed-signaling/pull/480
#
# Creates a neutralized and simplified log from the HPB logs by:
# 1. Replacing "user sessions" with "userX"
# 2. Replacing "room sessions" with "sessionX"
#
# E.g. the following line:
# May 26 13:31:36 server nextcloud-spreed-signaling[726]: clientsession.go:425: Session tooJzGsaUllvfGXdh3-74-yjAt-L9gCQKrs_U-DlLkZ8PTBabmJub3J6bEFGNEV6UnRZdEl2ZHpvUnJKMDNtSkFxZHBxZWR1X3VWUkJuZWllc2VUNko0RTFxLURaRXJyYjJ6ckc4dFBJRVVhT0lOLWR5RGtCM1R2MFpRemd3ZUFSLU9qWDZkR3FEQjR6MGt6c3p0VG92NmhqeEFEQkotN1JHM0lnbHl2ODRlbVRDaUlnQlhFQ3M1U1U2MDl4eWc4SU5MR0xjdTlUa0xaSmJqWGJ8Njk4NzAxNTg2MQ== joined room token123 with room session id P9roBo5O0EnRR8N4r+64MMdSHO2tu2ffNqjtICwSG43AHWL3XKn6XYv9xdYCgUYufxiCzIvg/QQk7cv8Uda1uhyDgh1FLPLCdjUe4uHJWKXb31rHig3gm+FdvOEO3GHEcKlJyPtSZzTupiatpanalRvMi6xR3jIXYoGcuvc//R2gzKFYNZQKwGdXXLMHNNHTlHPSAqIoYyj3vo5B+BeG9G1zo9Pq1WC3Akr2dghASkc+KJTHtpT3NbFBCAAH7jH
# is converted to:
# May 26 13:31:36 server nextcloud-spreed-signaling[726]: clientsession.go:425: Session User151 joined room token123 with room session id Session120
#
# Afterwards the script also creates a file per user and session
#

LOG_CONTENT="`cat $1`"
USER_SESSIONS=$(echo "$LOG_CONTENT" | egrep -o '[-a-zA-Z0-9_]{294,}==' | sort | uniq)
NUM_USER_SESSIONS=$(echo "$USER_SESSIONS" | wc -l)
echo "User sessions found: $NUM_USER_SESSIONS"

for i in $(seq 1 $NUM_USER_SESSIONS);
do
  SESSION_NAME=$(echo "$USER_SESSIONS" | head -n $i | tail -n 1)
  LOG_CONTENT=$(echo "${LOG_CONTENT//$SESSION_NAME/user$i}")
done

ROOM_SESSIONS=$(echo "$LOG_CONTENT" | egrep -o '[-a-zA-Z0-9_+\/]{255}( |$)' | sort | uniq)
NUM_ROOM_SESSIONS=$(echo "$ROOM_SESSIONS" | wc -l)
echo "Room sessions found: $NUM_ROOM_SESSIONS"

for i in $(seq 1 $NUM_ROOM_SESSIONS);
do
  SESSION_NAME=$(echo "$ROOM_SESSIONS" | head -n $i | tail -n 1)
  LOG_CONTENT=$(echo "${LOG_CONTENT//$SESSION_NAME/session$i}")
done

echo "$LOG_CONTENT" > simple.log

for i in $(seq 1 $NUM_USER_SESSIONS);
do
  echo "$LOG_CONTENT" | egrep "user$i( |$)" > user$i.log
done

for i in $(seq 1 $NUM_ROOM_SESSIONS);
do
  echo "$LOG_CONTENT" | egrep "session$i( |$)" > session$i.log
done

