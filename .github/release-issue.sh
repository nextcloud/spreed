#!/usr/bin/env bash
#
# Generate a Talk release issue from the .github/release-issue.txt template by
# performing all the case-sensitive replacements documented at the top of that
# template.
#
# Usage:
#   .github/release-issue.sh <version>[:<nextcloud-branch>[:<emoji>]] [...]
#
# Examples:
#   .github/release-issue.sh 22.1.4
#   .github/release-issue.sh 22.1.4:32
#   .github/release-issue.sh 22.1.4:32:💚
#   .github/release-issue.sh 20.1.9 21.0.13 22.1.4
#
# The Talk version (e.g. 22.1.4) drives:
#   X.Y.Z      -> the version itself          (22.1.4)
#   X.Y.[Z-1]  -> the previous patch version  (22.1.3)
#   TX         -> the Talk major version       (22)
# The Nextcloud stable branch number drives:
#   stableX.Y  -> the stable branch name      (stable32)
#   X          -> the Nextcloud branch number (32)
# The "Next Patch" milestone emoji varies per major version and is kept
# identical within each version's block:
#   💚         -> the per-major-version milestone emoji
#
# If the Nextcloud branch is not given via ":<n>", it defaults to the Talk major
# plus 10 (the current Talk<->server offset) and a warning is printed.
#
# The milestone emoji is resolved from the ":<emoji>" field, else read from the
# existing "Next Patch (<branch>)" milestone via the gh CLI, else it falls back
# to 💚 with a warning (e.g. when gh is unavailable or the milestone is missing).
#
# When several versions are passed they are emitted oldest-first (one release
# section each); the shared Preparation/Conclude sections use the newest version.
#
# The rendered issue is written to stdout; warnings/info go to stderr.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="$SCRIPT_DIR/release-issue.txt"

die() { printf 'Error: %s\n' "$*" >&2; exit 1; }
warn() { printf 'Warning: %s\n' "$*" >&2; }

# Print the leading comment block (everything between the shebang and the first
# non-comment line) as help text.
usage() {
	awk 'NR>1 && /^#/ { sub(/^# ?/, ""); print; next } NR>1 { exit }' "${BASH_SOURCE[0]}"
}

# Repository whose milestones are queried for the per-major "Next Patch" emoji.
REPO="nextcloud/spreed"
DEFAULT_EMOJI="💚"

# Read the leading emoji of the open "Next Patch (<nc>)" milestone from GitHub.
# Prints the emoji on success, nothing (and returns non-zero) on failure.
fetch_milestone_emoji() {
	local nc="$1" title
	command -v gh >/dev/null 2>&1 || return 1
	title="$(gh api "repos/$REPO/milestones" --paginate \
		--jq '.[] | select(.title | endswith("Next Patch ('"$nc"')")) | .title' \
		2>/dev/null | head -n1)" || return 1
	[[ -n "$title" ]] || return 1
	# Strip the trailing " Next Patch (<nc>)" to keep just the leading emoji.
	printf '%s' "${title%% Next Patch*}"
}

if [[ $# -eq 0 || "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit $(( $# == 0 ? 1 : 0 ))
fi

[[ -f "$TEMPLATE" ]] || die "template not found: $TEMPLATE"

# --- Parse arguments: version[:nextcloud-branch[:emoji]] ---------------------

declare -A NC_OF=()
declare -A EMOJI_FOR=()
VERSIONS=()

for arg in "$@"; do
	IFS=':' read -r version nc emoji <<<"$arg"

	[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-].+)?$ ]] \
		|| die "invalid version '$version' (expected e.g. 22.1.4 or 22.1.4-rc.1)"

	talk_major="${version%%.*}"

	if [[ -z "$nc" ]]; then
		nc=$(( talk_major + 10 ))
		warn "no Nextcloud branch given for $version, assuming stable$nc (override with $version:<n>)"
	fi
	# Allow ":stable32" as well as ":32"
	nc="${nc#stable}"
	[[ "$nc" =~ ^[0-9]+$ ]] || die "invalid Nextcloud branch '$nc' for $version"

	if [[ -z "$emoji" ]]; then
		if ! emoji="$(fetch_milestone_emoji "$nc")" || [[ -z "$emoji" ]]; then
			emoji="$DEFAULT_EMOJI"
			warn "could not read the 'Next Patch ($nc)' milestone emoji from gh, using $emoji (override with $version:$nc:<emoji>)"
		fi
	fi

	NC_OF["$version"]="$nc"
	EMOJI_FOR["$version"]="$emoji"
	VERSIONS+=("$version")
done

# Sort versions oldest-first for the release sections.
mapfile -t VERSIONS < <(printf '%s\n' "${VERSIONS[@]}" | sort -V)
NEWEST="${VERSIONS[${#VERSIONS[@]}-1]}"

# --- Helpers -----------------------------------------------------------------

# Previous version: decrement the last integer in the version string.
prev_version() {
	local v="$1"
	if [[ "$v" =~ ^(.*[^0-9])([0-9]+)$ || "$v" =~ ^()([0-9]+)$ ]]; then
		local prefix="${BASH_REMATCH[1]}" num="${BASH_REMATCH[2]}"
		if (( num == 0 )); then
			echo ""
			return 1
		fi
		printf '%s%d' "$prefix" "$(( num - 1 ))"
		return 0
	fi
	echo ""
	return 1
}

# Perform the documented replacements on the given text.
# Order matters: most specific placeholders first, bare "X" last.
render() {
	local text="$1" version="$2" prev="$3" stable="$4" tmajor="$5" nc="$6" emoji="$7"
	text="${text//X.Y.\[Z-1\]/$prev}"
	text="${text//X.Y.Z/$version}"
	text="${text//stableX.Y/$stable}"
	text="${text//TX/$tmajor}"
	text="${text//stableX/$stable}"
	text="${text//X/$nc}"
	text="${text//💚/$emoji}"
	printf '%s' "$text"
}

# --- Split the template into prep / release / conclude -----------------------

# Preparation: everything up to the "## 🚀 vX.Y.Z" heading, with the leading
# instruction comment block stripped.
PREP="$(awk '
	/^## .*vX\.Y\.Z/ { exit }
	started { print; next }
	!/^<!--/ && NF { started = 1; print }
' "$TEMPLATE")"

# Release: the "## 🚀 vX.Y.Z" section up to the "## 🛣️ Conclude" heading.
RELEASE="$(awk '
	/^## .*vX\.Y\.Z/ { inrel = 1 }
	/^## .*Conclude/ { inrel = 0 }
	inrel { print }
' "$TEMPLATE")"

# Conclude: from the "## 🛣️ Conclude" heading to the end.
CONCLUDE="$(awk '
	/^## .*Conclude/ { inconc = 1 }
	inconc { print }
' "$TEMPLATE")"

[[ -n "$RELEASE" ]] || die "could not locate the release section in $TEMPLATE"

# --- Render ------------------------------------------------------------------

# Resolve every placeholder value for a version into REPLY_* globals.
values_for() {
	local version="$1" nc="${NC_OF[$1]}"
	local tmajor="${version%%.*}"
	local prev
	if ! prev="$(prev_version "$version")"; then
		prev="<previous-version>"
		warn "could not derive the previous version for $version, using placeholder '$prev'"
	fi
	REPLY_VERSION="$version"
	REPLY_PREV="$prev"
	REPLY_STABLE="stable${nc}"
	REPLY_TMAJOR="$tmajor"
	REPLY_NC="$nc"
	REPLY_EMOJI="${EMOJI_FOR[$version]}"
}

# Shared preparation section uses the newest version.
values_for "$NEWEST"
render "$PREP" "$REPLY_VERSION" "$REPLY_PREV" "$REPLY_STABLE" "$REPLY_TMAJOR" "$REPLY_NC" "$REPLY_EMOJI"
printf '\n\n'

# One release section per version, oldest first.
for version in "${VERSIONS[@]}"; do
	values_for "$version"
	render "$RELEASE" "$REPLY_VERSION" "$REPLY_PREV" "$REPLY_STABLE" "$REPLY_TMAJOR" "$REPLY_NC" "$REPLY_EMOJI"
	printf '\n\n'
done

# Shared conclude section uses the newest version.
values_for "$NEWEST"
render "$CONCLUDE" "$REPLY_VERSION" "$REPLY_PREV" "$REPLY_STABLE" "$REPLY_TMAJOR" "$REPLY_NC" "$REPLY_EMOJI"
printf '\n'
