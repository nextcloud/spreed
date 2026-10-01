#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Runs run.sh against a throwaway SQLite instance instead of the configured one.
# Usage: ./run-local.sh [features/path.feature[:line]]
# Set TEST_INSTANCE_DIR to keep and reuse an instance between runs.

ROOT_DIR=$(realpath "$PWD/../../../..")
if [ -z "$TEST_INSTANCE_DIR" ]; then
	TEST_INSTANCE_DIR=$(mktemp -d)
	trap 'rm -rf "$TEST_INSTANCE_DIR"' EXIT
fi
export NEXTCLOUD_CONFIG_DIR="$TEST_INSTANCE_DIR/config"

if [ ! -f "$NEXTCLOUD_CONFIG_DIR/config.php" ]; then
	mkdir --parents "$NEXTCLOUD_CONFIG_DIR" "$TEST_INSTANCE_DIR/data"
	"$ROOT_DIR/occ" maintenance:install --admin-pass=admin --data-dir="$TEST_INSTANCE_DIR/data" || exit 1
	# Inherit apps_paths so spreed and its dependency apps are found
	php -r 'include $argv[1]; echo json_encode(["system" => ["apps_paths" => $CONFIG["apps_paths"] ?? []]]);' \
		"$ROOT_DIR/config/config.php" | "$ROOT_DIR/occ" config:import || exit 1
fi

# run.sh relies on "pkill -P" to stop the servers, provide it when procps is missing
if ! command -v pkill > /dev/null; then
	pkill() {
		[ "$1" = "-P" ] && kill $(cat /proc/"$2"/task/*/children 2> /dev/null) 2> /dev/null
		return 0
	}
	export -f pkill
fi

LANG=C ./run.sh "$@"
