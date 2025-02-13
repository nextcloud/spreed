#!/bin/bash

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
    local full_path=$1           # Full path passed as an argument
    local new_name="openapi_${full_path#apps/}"

    find "$SERVER_DIR/$full_path" -maxdepth 1 -name "openapi.json" -exec sh -c 'cp "$1" "$2/$3"' _ {} "$TEMP_DIR" "$new_name.json" \;
	npx openapi-typescript --redocly $TYPES_DIR "$TEMP_DIR/$new_name.json" -t -o "$CORE_TYPES_OUTPUT_DIR/$new_name.ts"
}

generate_ts_files "core"
generate_ts_files "apps/files_sharing"
generate_ts_files "apps/dav"
generate_ts_files "apps/provisioning_api"

rm -rf "$TEMP_DIR"
