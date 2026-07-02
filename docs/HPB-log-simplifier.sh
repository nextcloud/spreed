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
# 1. Replacing "user sessions" (public session ids) with "userX"
# 2. Replacing "room sessions" with "sessionX"
#
# The signaling server pairs a client's public and private session id on the
# same line as "<public> (private=<private>)". The private id is replaced with
# "private_sessionX" carrying the same X as its paired public "userX", so the
# two stay linked while remaining distinguishable.
#
# E.g. the following line:
# May 26 13:31:36 server nextcloud-spreed-signaling[726]: clientsession.go:425: Session tooJzGsaUllvfGXdh3-74-yjAt-L9gCQKrs_U-DlLkZ8PTBabmJub3J6bEFGNEV6UnRZdEl2ZHpvUnJKMDNtSkFxZHBxZWR1X3VWUkJuZWllc2VUNko0RTFxLURaRXJyYjJ6ckc4dFBJRVVhT0lOLWR5RGtCM1R2MFpRemd3ZUFSLU9qWDZkR3FEQjR6MGt6c3p0VG92NmhqeEFEQkotN1JHM0lnbHl2ODRlbVRDaUlnQlhFQ3M1U1U2MDl4eWc4SU5MR0xjdTlUa0xaSmJqWGJ8Njk4NzAxNTg2MQ== joined room token123 with room session id P9roBo5O0EnRR8N4r+64MMdSHO2tu2ffNqjtICwSG43AHWL3XKn6XYv9xdYCgUYufxiCzIvg/QQk7cv8Uda1uhyDgh1FLPLCdjUe4uHJWKXb31rHig3gm+FdvOEO3GHEcKlJyPtSZzTupiatpanalRvMi6xR3jIXYoGcuvc//R2gzKFYNZQKwGdXXLMHNNHTlHPSAqIoYyj3vo5B+BeG9G1zo9Pq1WC3Akr2dghASkc+KJTHtpT3NbFBCAAH7jH
# is converted to:
# May 26 13:31:36 server nextcloud-spreed-signaling[726]: clientsession.go:425: Session User151 joined room token123 with room session id Session120
#
# Afterwards the script also creates a file per user and room session, plus a
# mapping.log dictionary file (replacement<TAB>original token) so the
# anonymized IDs can be traced back if needed.
#
# Tokens are found by first extracting the MAXIMAL contiguous run of the
# combined charset (rather than a fixed-length slice anchored on a trailing
# space/end-of-line). This guarantees a token is never sliced mid-way just
# because it happens to be followed by punctuation other than a space, which
# would otherwise leave a left-over alphanumeric fragment to be
# misclassified as a session of a different kind. Each unique run is then
# classified by its length. The final rewrite (and per-session file
# split) happens in a single pass over the original file, so this scales to
# large HPB logs.
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

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"; exit 130' INT

# Emits token<TAB>label<TAB>file_target lines. label is what the token gets
# replaced with in the logs; file_target picks the *.log the line goes into
# (label and file_target are the same, except for aliased private tokens).
write_map() {
  local prefix="$1"
  shift
  local i=1
  for token in "$@"; do
    printf '%s\t%s%d\t%s%d\n' "$token" "$prefix" "$i" "$prefix" "$i"
    i=$((i + 1))
  done
}

echo "Scanning for user/room sessions..."
mapfile -t ALL_TOKENS < <(egrep -o '[-a-zA-Z0-9_+\/]+(==)?' "$LOG_FILE" | sort -u)

USER_SESSIONS=()
ROOM_SESSIONS=()
for TOKEN in "${ALL_TOKENS[@]}"; do
  # Skip file/URL paths (e.g. "/custom_apps/spreed/img/..."): they share the
  # session token charset (alnum + "-_+/"), but are not randomly generated,
  # so unlike real session tokens they are all-lowercase (plus digits) and/or
  # start with a leading "/".
  if [[ "$TOKEN" == /* ]]; then
    continue
  fi
  if ! [[ "$TOKEN" =~ [0-9] && "$TOKEN" =~ [A-Z] && "$TOKEN" =~ [a-z] ]]; then
    continue
  fi

  # The signaling server switched to a custom session id codec that base64url-
  # encodes shorter ids WITHOUT padding, so public and private session ids no
  # longer end in "==" and now share the same short format. Both are collected
  # as user sessions and paired up below. The room session id is still provided
  # by the Nextcloud backend and keeps its distinct 255-char length.
  LEN=${#TOKEN}
  if (( LEN == 255 )); then
    ROOM_SESSIONS+=("$TOKEN")
  elif (( LEN >= 50 && LEN <= 293 )); then
    USER_SESSIONS+=("$TOKEN")
  fi
done

declare -A USER_NUM
for i in "${!USER_SESSIONS[@]}"; do
  USER_NUM["${USER_SESSIONS[$i]}"]=$((i + 1))
done

# HPB logs pair a session's public and private id on the same line as
# "<public> (private=<private>)". Both independently look like valid session
# tokens and would otherwise get assigned two different numbers, even though
# they identify the same session. Detect that pattern and alias the private
# token's number to the public one's, so they collapse into a single userN
# (and therefore a single userN.log) instead of two. The private token still
# gets its own "private_sessionN" label in the rewritten log and the mapping
# file, so it stays traceable/distinguishable, but it is never split into its
# own file.
declare -A USER_IS_PRIVATE
PRIVATE_PATTERN='[-a-zA-Z0-9_+/]+(==)?[[:space:]]*\(private=[-a-zA-Z0-9_+/]+(==)?\)'
mapfile -t PRIVATE_PAIR_MATCHES < <(egrep -o "$PRIVATE_PATTERN" "$LOG_FILE" | sort -u)
for MATCH in "${PRIVATE_PAIR_MATCHES[@]}"; do
  if [[ "$MATCH" =~ ^([-a-zA-Z0-9_+/]+(==)?)[[:space:]]*\(private=([-a-zA-Z0-9_+/]+(==)?)\)$ ]]; then
    PUBLIC_TOKEN="${BASH_REMATCH[1]}"
    PRIVATE_TOKEN="${BASH_REMATCH[3]}"
    if [[ -n "${USER_NUM[$PUBLIC_TOKEN]+x}" && -n "${USER_NUM[$PRIVATE_TOKEN]+x}" ]]; then
      USER_NUM["$PRIVATE_TOKEN"]="${USER_NUM[$PUBLIC_TOKEN]}"
      USER_IS_PRIVATE["$PRIVATE_TOKEN"]=1
    fi
  fi
done

# Aliasing above leaves gaps in the numbering (each merged private token
# frees up the number it was originally assigned). Renumber the surviving
# distinct numbers to a contiguous 1, 2, 3, ... sequence.
if (( ${#USER_NUM[@]} > 0 )); then
  mapfile -t DISTINCT_NUMS < <(printf '%s\n' "${USER_NUM[@]}" | sort -un)
  declare -A RENUMBER
  NEW_N=1
  for OLD_N in "${DISTINCT_NUMS[@]}"; do
    RENUMBER["$OLD_N"]=$NEW_N
    NEW_N=$((NEW_N + 1))
  done
  for TOKEN in "${!USER_NUM[@]}"; do
    USER_NUM["$TOKEN"]="${RENUMBER[${USER_NUM[$TOKEN]}]}"
  done
fi

NUM_DISTINCT_USERS=0
if (( ${#USER_NUM[@]} > 0 )); then
  NUM_DISTINCT_USERS=$(printf '%s\n' "${USER_NUM[@]}" | sort -un | wc -l)
fi

echo "User sessions found: ${NUM_DISTINCT_USERS:-0}"
echo "Room sessions found: ${#ROOM_SESSIONS[@]}"

write_map "session" "${ROOM_SESSIONS[@]}" > "$WORK_DIR/room.map"
for TOKEN in "${!USER_NUM[@]}"; do
  N="${USER_NUM[$TOKEN]}"
  if [[ -n "${USER_IS_PRIVATE[$TOKEN]+x}" ]]; then
    printf '%s\tprivate_session%d\tuser%d\n' "$TOKEN" "$N" "$N"
  else
    printf '%s\tuser%d\tuser%d\n' "$TOKEN" "$N" "$N"
  fi
done > "$WORK_DIR/user.map"
cat "$WORK_DIR/user.map" "$WORK_DIR/room.map" > "$WORK_DIR/full.map"

echo "Rewriting log and splitting per user/session (single pass)..."
awk -v mapfile="$WORK_DIR/full.map" -v outdir="$OUTPUT_DIR" '
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
  m = 0
  while ((getline line < mapfile) > 0) {
    split(line, parts, "\t")
    map_tokens[n] = parts[1]
    map_escaped[n] = escape_regex(parts[1])
    map_label[n] = parts[2]
    map_target[n] = parts[3]
    n++
    # Several distinct raw tokens (e.g. a session'"'"'s public and private id)
    # can share the same file_target; only write to its .log once.
    if (!(parts[3] in seen_target)) {
      seen_target[parts[3]] = 1
      uniq_target[m] = parts[3]
      m++
    }
  }
  close(mapfile)
  simple_log = outdir "/simple.log"
}
{
  line = $0
  for (i = 0; i < n; i++) {
    if (index(line, map_tokens[i]) > 0) {
      gsub(map_escaped[i], map_label[i], line)
    }
  }
  print line > simple_log
  for (i = 0; i < m; i++) {
    # Match uniq_target[i] as a whole token: not immediately adjacent to
    # another alphanumeric char (or underscore) on either side. The right
    # boundary stops "user6" from matching "user60"; the left boundary stops
    # the room-session target "sessionN" from matching inside the private
    # label "private_sessionN" (which shares the number but belongs to userN).
    if (line ~ ("(^|[^a-zA-Z0-9_])" uniq_target[i] "([^a-zA-Z0-9_]|$)")) {
      print line >> (outdir "/" uniq_target[i] ".log")
    }
  }
}
' "$LOG_FILE"

awk -F'\t' '{ print $2 "\t" $1 }' "$WORK_DIR/full.map" | sort -V > "$OUTPUT_DIR/mapping.log"

rm -rf "$WORK_DIR"

echo "Logs written to: $OUTPUT_DIR"
echo "Token mapping written to: $OUTPUT_DIR/mapping.log"
