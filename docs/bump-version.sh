#!/bin/bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
#
# Check out a backport changelog PR, bump the version, and commit the result.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_error()   { echo -e "${RED}✗ $1${NC}" >&2; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }
print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_step()    { echo -e "\n${CYAN}→ $1${NC}"; }

usage() {
    cat << EOF
Usage: $0 <changelog-pr-number>

Check out the given changelog backport PR, bump the version in
appinfo/info.xml and package.json, then commit the result.

ARGUMENTS:
    <changelog-pr-number>   GitHub PR number of the backported changelog PR

OPTIONS:
    -h, --help   Show this help message

EXAMPLE:
    $0 18500

EOF
    exit 0
}

PR_NUMBER=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help) usage ;;
        [0-9]*) PR_NUMBER="$1"; shift ;;
        *) print_error "Unknown argument: $1"; usage ;;
    esac
done

if [ -z "$PR_NUMBER" ]; then
    print_error "Changelog PR number is required"
    usage
fi

# ============================================================================
# Pre-flight checks
# ============================================================================
PREFLIGHT_OK=true

for cmd in git gh xmllint npm; do
    if ! command -v "$cmd" &>/dev/null; then
        print_error "$cmd is not installed"
        PREFLIGHT_OK=false
    fi
done

if ! git rev-parse --git-dir &>/dev/null; then
    print_error "Not in a git repository"
    PREFLIGHT_OK=false
fi

[ "$PREFLIGHT_OK" = false ] && exit 1

# ============================================================================
# Check out the PR branch
# ============================================================================
print_step "Checking out PR #${PR_NUMBER}"

gh pr checkout "$PR_NUMBER"

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
print_success "Now on branch: ${CURRENT_BRANCH}"

# ============================================================================
# Read and increment version
# ============================================================================
if [ ! -f "appinfo/info.xml" ]; then
    print_error "appinfo/info.xml not found"
    exit 1
fi

CURRENT_VERSION=$(xmllint --xpath '/info/version/text()' appinfo/info.xml 2>/dev/null)
if [ -z "$CURRENT_VERSION" ]; then
    print_error "Could not read version from appinfo/info.xml"
    exit 1
fi

increment_version() {
    local v="$1"
    if [[ "$v" =~ ^(.*\.)([0-9]+)$ ]]; then
        echo "${BASH_REMATCH[1]}$((BASH_REMATCH[2] + 1))"
    else
        echo "$v"
    fi
}

NEXT_VERSION=$(increment_version "$CURRENT_VERSION")

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Bump version: v${CURRENT_VERSION} → v${NEXT_VERSION}${NC}"
echo -e "${BLUE}  Branch: ${CURRENT_BRANCH}${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# ============================================================================
# Bump appinfo/info.xml
# ============================================================================
print_step "Bumping appinfo/info.xml"

sed -i "s|<version>${CURRENT_VERSION}</version>|<version>${NEXT_VERSION}</version>|" appinfo/info.xml

VERIFY=$(xmllint --xpath '/info/version/text()' appinfo/info.xml 2>/dev/null)
if [ "$VERIFY" != "$NEXT_VERSION" ]; then
    print_error "Version mismatch after edit — expected ${NEXT_VERSION}, got ${VERIFY}"
    exit 1
fi
print_success "appinfo/info.xml → ${NEXT_VERSION}"

# ============================================================================
# Bump package.json
# ============================================================================
print_step "Bumping package.json"

NPM_VERSION=$(npm version --no-git-tag-version "$NEXT_VERSION" 2>/dev/null | sed 's/^v//')

if [ "$NPM_VERSION" != "$NEXT_VERSION" ]; then
    print_error "npm version returned '${NPM_VERSION}', expected '${NEXT_VERSION}'"
    exit 1
fi
print_success "package.json → ${NEXT_VERSION}"

# ============================================================================
# Commit
# ============================================================================
print_step "Committing"

git add appinfo/info.xml package.json
git commit -s -m "chore(release): Prepare release v${NEXT_VERSION}"

print_success "Committed: chore(release): Prepare release v${NEXT_VERSION}"

# ============================================================================
# Next steps
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Done — push when ready:${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "  git push origin ${CURRENT_BRANCH}"
echo ""
