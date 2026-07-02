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
# Afterwards the script also creates a file per user and session, plus a
# mapping.log dictionary file (replacement<TAB>original token) so the
# anonymized IDs can be traced back if needed.
#
# The whole log is only ever read once (this file) and written once
# (via a single-pass awk program), regardless of the number of user
# and room sessions found, so it scales to large HPB logs.
#

trap 'exit 130' INT

LOG_FILE="$1"
PARENT_DIR="$(cd "$(dirname "$LOG_FILE")" && pwd)"
OUTPUT_DIR="$PARENT_DIR/$(basename "$LOG_FILE")-simplified"
# Start from a clean output directory. The per-entity files are written in
# append mode during the single pass, so a leftover directory from a previous
# run would otherwise double their contents and keep orphan files for tokens
# that no longer appear in the current log.
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

echo "Scanning for user sessions..."
mapfile -t USER_SESSIONS < <(egrep -o '[-a-zA-Z0-9_]{294,}==' "$LOG_FILE" | sort -u)
NUM_USER_SESSIONS=${#USER_SESSIONS[@]}
echo "User sessions found: $NUM_USER_SESSIONS"

echo "Scanning for room sessions..."
mapfile -t ROOM_SESSIONS < <(egrep -o '[-a-zA-Z0-9_+\/]{255}( |$)' "$LOG_FILE" | sort -u)
NUM_ROOM_SESSIONS=${#ROOM_SESSIONS[@]}
echo "Room sessions found: $NUM_ROOM_SESSIONS"

# Build a token -> replacement map file for awk, instead of doing a
# bash string replacement (and full-content copy) per session.
MAP_FILE="$(mktemp)"
trap 'rm -f "$MAP_FILE"; exit 130' INT
for i in "${!USER_SESSIONS[@]}"; do
  printf '%s\tuser%d\n' "${USER_SESSIONS[$i]}" "$((i + 1))" >> "$MAP_FILE"
done
for i in "${!ROOM_SESSIONS[@]}"; do
  printf '%s\tsession%d\n' "${ROOM_SESSIONS[$i]}" "$((i + 1))" >> "$MAP_FILE"
done

echo "Rewriting log and splitting per user/session (single pass)..."
awk -v mapfile="$MAP_FILE" -v outdir="$OUTPUT_DIR" '
function escape_regex(s,    result, i, c, special) {
  special = "\\^$.[]|()*+?{}"
  result = ""
  for (i = 1; i <= length(s); i++) {
    c = substr(s, i, 1)
    if (index(special, c) > 0) {
      result = result "\\" c
    } else {
      result = result c
    }
  }
  return result
}
BEGIN {
  n = 0
  while ((getline line < mapfile) > 0) {
    split(line, parts, "\t")
    map_tokens[n] = parts[1]
    map_escaped[n] = escape_regex(parts[1])
    map_repl[n] = parts[2]
    n++
  }
  close(mapfile)
  simple_log = outdir "/simple.log"
}
{
  line = $0
  for (i = 0; i < n; i++) {
    if (index(line, map_tokens[i]) > 0) {
      gsub(map_escaped[i], map_repl[i], line)
    }
  }
  print line > simple_log
  for (i = 0; i < n; i++) {
    if (index(line, map_repl[i]) > 0) {
      print line >> (outdir "/" map_repl[i] ".log")
    }
  }
}
' "$LOG_FILE"

awk -F'\t' '{ print $2 "\t" $1 }' "$WORK_DIR/full.map" | sort -V > "$OUTPUT_DIR/mapping.log"

rm -f "$MAP_FILE"

echo "Logs written to: $OUTPUT_DIR"
echo "Token mapping written to: $OUTPUT_DIR/mapping.log"
