#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-only
#

# By default is expected that pwd returns <server_directory>/<apps or apps-extra>/spreed
SERVER_DIR="${1:-$(dirname $(dirname $(pwd)))}"
TYPES_DIR="$(pwd)/src/types"
CORE_TYPES_OUTPUT_DIR="$TYPES_DIR/openapi/core"
TEMP_DIR="$TYPES_DIR/tmp"

# Create the temporary directory if it doesn't exist
mkdir -p "$CORE_TYPES_OUTPUT_DIR"
mkdir -p "$TEMP_DIR"

# Find and copy openapi.json files, then translate to ts types
generate_ts_files() {
	local full_path=$1
	local file_name=$2
	local openapi_file="$SERVER_DIR/$full_path/$file_name"
	local temp_file="$TEMP_DIR/openapi_${full_path#apps/}.json"
	local ts_file="$CORE_TYPES_OUTPUT_DIR/openapi_${full_path#apps/}.ts"

	if [ -f "$openapi_file" ]; then
		cp "$openapi_file" "$temp_file"
	else
		echo "Error: $openapi_file is not found"
		return 1
	fi

	npx openapi-typescript --redocly $TYPES_DIR "$temp_file" -t -o "$ts_file"
}

generate_ts_files "core" "openapi.json"
generate_ts_files "apps/files" "openapi.json"
generate_ts_files "apps/files_sharing" "openapi.json"
generate_ts_files "apps/dav" "openapi.json"
generate_ts_files "apps/provisioning_api" "openapi.json"

rm -rf "$TEMP_DIR"
