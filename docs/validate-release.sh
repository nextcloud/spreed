#!/bin/bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
#
# Pre-release preparation report for Nextcloud Spreed
# Use --prepare-changelog to generate changelog entries as a local commit

DRY_RUN=false
VERBOSE=false
PREPARE_CHANGELOG=false
declare -a STABLE_BRANCHES=()

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

print_section() {
    echo ""
    echo -e "${CYAN}→ $1${NC}"
}

print_info() {
    echo -e "  $1"
}

print_item() {
    echo -e "  • $1"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

usage() {
    cat << EOF
Usage: $0 [OPTIONS] [STABLE_BRANCH...]

Gather release preparation information for Nextcloud Spreed

Run from 'main' branch to report on upcoming releases.

OPTIONS:
    --prepare-changelog   Generate changelog entries and commit locally (push and PR are manual)
    --dry-run               Preview changelog output without making any git/PR changes
                            (implies --prepare-changelog)
    --verbose               Show detailed output
    -h, --help              Show this help message

ARGUMENTS:
    STABLE_BRANCH   Specific stable branches to check (e.g., stable33, stable34)
                    If not provided, automatically selects the 3 highest versions

EXAMPLES:
    $0                           # Check top 3 stable branches
    $0 stable33 stable34         # Check specific branches
    $0 --dry-run                 # Preview changelog without making changes
    $0 --prepare-changelog     # Generate changelog and open a PR

EOF
    exit 0
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; PREPARE_CHANGELOG=true; shift ;;
        --verbose) VERBOSE=true; shift ;;
        --prepare-changelog) PREPARE_CHANGELOG=true; shift ;;
        -h|--help) usage ;;
        *) STABLE_BRANCHES+=("$1"); shift ;;
    esac
done

if [ ${#STABLE_BRANCHES[@]} -eq 0 ]; then
    if [ -f ".github/dependabot.yml" ]; then
        STABLE_BRANCHES=($(grep 'target-branch:' .github/dependabot.yml | grep -oE 'stable[0-9.]+' | sort -Vr | uniq))
    else
        STABLE_BRANCHES=($(git branch -r 2>/dev/null | grep -oE 'origin/stable[0-9.]+' | sed 's|origin/||' | sort -V -r | head -3 || true))
    fi
fi

print_header "Nextcloud Spreed Release Preparation Report"

# ============================================================================
# Pre-run checks
# ============================================================================
PREFLIGHT_OK=true

if ! command -v git &> /dev/null; then
    print_error "git is not installed"
    PREFLIGHT_OK=false
elif ! git rev-parse --git-dir > /dev/null 2>&1; then
    print_error "Not in a git repository"
    PREFLIGHT_OK=false
fi

if ! command -v gh &> /dev/null; then
    print_error "GitHub CLI (gh) is not installed — https://github.com/cli/cli#installation"
    PREFLIGHT_OK=false
elif ! gh auth status &> /dev/null; then
    print_error "GitHub CLI (gh) is not authenticated — run: gh auth login"
    PREFLIGHT_OK=false
fi

if ! command -v jq &> /dev/null; then
    print_error "jq is not installed"
    PREFLIGHT_OK=false
fi

if ! command -v xmllint &> /dev/null; then
    print_error "xmllint is not installed (libxml2-utils)"
    PREFLIGHT_OK=false
fi

if [ "$PREFLIGHT_OK" = false ]; then
    exit 1
fi

print_success "All required tools available"

if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}[DRY RUN – no changes will be made]${NC}"
fi

if [ ${#STABLE_BRANCHES[@]} -eq 0 ]; then
    echo -e "Scope: ${BLUE}main branch (preparation only)${NC}"
else
    BRANCHES_DISPLAY=$(IFS=,; echo "${STABLE_BRANCHES[*]}")
    echo -e "Target branches: ${BLUE}$BRANCHES_DISPLAY${NC}"
fi

# ============================================================================
# Git setup
# ============================================================================

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
print_info "Current branch: $CURRENT_BRANCH"

print_info "Fetching remote info..."
git fetch origin --quiet 2>/dev/null || print_warning "Could not fetch from origin"

# ============================================================================
# Version info
# ============================================================================
print_section "Version Information"

VERSION=""
PKG_VERSION=""

if [ -f "appinfo/info.xml" ]; then
    VERSION=$(xmllint --xpath '/info/version/text()' appinfo/info.xml 2>/dev/null)
    [ -n "$VERSION" ] && print_info "appinfo/info.xml: $VERSION"
fi

if [ -f "package.json" ]; then
    PKG_VERSION=$(grep '"version"' package.json | head -1 | sed 's/.*"version": "\([^"]*\)".*/\1/')
    [ -n "$PKG_VERSION" ] && print_info "package.json:     $PKG_VERSION"
fi

if [ -n "$VERSION" ] && [ -n "$PKG_VERSION" ] && [ "$VERSION" != "$PKG_VERSION" ]; then
    print_warning "Version mismatch between appinfo/info.xml and package.json"
fi

# ============================================================================
# 1. Pending backports
# ============================================================================
print_section "Pending Backports"

if ! command -v gh &> /dev/null; then
    print_warning "GitHub CLI (gh) not installed — install: https://github.com/cli/cli#installation"
else
    BACKPORTS=$(gh pr list --label "backport-request" --state open --repo nextcloud/spreed --json number,title,baseRefName 2>/dev/null)
    BACKPORT_COUNT=$(echo "$BACKPORTS" | jq 'length' 2>/dev/null || echo "0")
    BACKPORT_COUNT=$((${BACKPORT_COUNT:-0} + 0))

    if [ "$BACKPORT_COUNT" -eq 0 ]; then
        print_success "No pending backports"
    else
        print_warning "$BACKPORT_COUNT pending backport(s):"
        echo "$BACKPORTS" | jq -r '.[] | "  • #\(.number) [\(.baseRefName)]: \(.title)"'
    fi
fi

# ============================================================================
# 2. Milestones Status
# ============================================================================
print_section "Milestones Status"

if ! command -v gh &> /dev/null; then
    print_warning "GitHub CLI (gh) not installed"
else
    MILESTONES_JSON=$(gh api repos/nextcloud/spreed/milestones 2>/dev/null || echo "[]")
    MILESTONES=$(echo "$MILESTONES_JSON" | jq -r '.[] | select(.state=="open") | .title' 2>/dev/null || echo "")

    if [ -z "$MILESTONES" ]; then
        print_info "No open milestones found"
    else
        while IFS= read -r milestone; do
            # One call per milestone: fetch all open issues with labels, filter client-side
            MILESTONE_ISSUES=$(gh issue list --milestone "$milestone" --state open --repo nextcloud/spreed --json number,title,labels 2>/dev/null || echo "[]")

            OPEN_ISSUES=$(echo "$MILESTONE_ISSUES" | jq 'length' 2>/dev/null || echo "0")
            OPEN_ISSUES=$((${OPEN_ISSUES:-0} + 0))

            HIGH_PRIORITY=$(echo "$MILESTONE_ISSUES" | jq '[.[] | select(any(.labels[]; .name == "high"))] | length' 2>/dev/null || echo "0")
            HIGH_PRIORITY=$((${HIGH_PRIORITY:-0} + 0))

            if [ "$OPEN_ISSUES" -eq 0 ]; then
                print_item "$milestone (ready)"
            elif [ "$HIGH_PRIORITY" -gt 0 ]; then
                print_item "$milestone: ${YELLOW}$OPEN_ISSUES open issue(s)${NC} (${RED}$HIGH_PRIORITY high-priority${NC})"
                echo "$MILESTONE_ISSUES" | jq -r '.[] | select(any(.labels[]; .name == "high")) | "#\(.number): \(.title)"' | while read -r line; do
                    echo -e "    ${RED}${line}${NC}"
                done
            else
                print_item "$milestone: ${YELLOW}$OPEN_ISSUES open issue(s)${NC}"
                if [ "$VERBOSE" = true ]; then
                    echo "$MILESTONE_ISSUES" | jq -r '.[] | "#\(.number): \(.title)"'
                fi
            fi
        done <<< "$MILESTONES"
    fi
fi

# ============================================================================
# 3. Open Pull Requests against stable branches
# ============================================================================
print_section "Open Pull Requests"

if ! command -v gh &> /dev/null; then
    print_warning "GitHub CLI (gh) not installed"
elif [ ${#STABLE_BRANCHES[@]} -eq 0 ]; then
    print_info "No stable branches to check"
else
    for branch in "${STABLE_BRANCHES[@]}"; do
        if git rev-parse --verify "origin/$branch" > /dev/null 2>&1; then
            PRS=$(gh pr list --base "$branch" --state open --repo nextcloud/spreed --json number,title 2>/dev/null)
            PR_COUNT=$(echo "$PRS" | jq 'length' 2>/dev/null || echo "0")
            PR_COUNT=$((${PR_COUNT:-0} + 0))

            if [ "$PR_COUNT" -eq 0 ]; then
                print_item "$branch: no open PRs"
            else
                print_item "$branch: ${YELLOW}$PR_COUNT open PR(s)${NC}"
                echo "$PRS" | jq -r '.[] | "      #\(.number): \(.title)"'
            fi
        else
            print_warning "Branch '$branch' not found in origin"
        fi
    done
fi

# ============================================================================
# 4. Dependabot Coverage
# ============================================================================
print_section "Dependabot Coverage"

if [ ${#STABLE_BRANCHES[@]} -eq 0 ]; then
    print_info "No stable branches to check"
elif [ ! -f ".github/dependabot.yml" ]; then
    print_warning ".github/dependabot.yml not found"
else
    for branch in "${STABLE_BRANCHES[@]}"; do
        if grep -qF "target-branch: $branch" .github/dependabot.yml 2>/dev/null; then
            print_success "$branch: patch updates configured"
        else
            print_warning "$branch: missing from .github/dependabot.yml — add composer and npm patch update entries"
        fi
    done
fi

# ============================================================================
# 5. First RC of major release checks (conditional per branch)
# ============================================================================
# Fires when: minor=0 and patch=0 (major release series) and no RC tags exist yet.
# This catches the preparation phase before rc.1 is tagged, not just when already at rc.1.
FIRST_RC_FOUND=false

if [ ${#STABLE_BRANCHES[@]} -gt 0 ]; then
    for branch in "${STABLE_BRANCHES[@]}"; do
        if ! git rev-parse --verify "origin/$branch" > /dev/null 2>&1; then
            continue
        fi

        BRANCH_VERSION=$(git show "origin/$branch:appinfo/info.xml" 2>/dev/null | xmllint --xpath '/info/version/text()' - 2>/dev/null || echo "")
        [ -z "$BRANCH_VERSION" ] && continue

        TALK_MAJOR=$(echo "$BRANCH_VERSION" | cut -d. -f1)
        TALK_MINOR=$(echo "$BRANCH_VERSION" | cut -d. -f2)
        TALK_PATCH=$(echo "$BRANCH_VERSION" | cut -d. -f3 | grep -oE '^[0-9]+')

        [ "$TALK_MINOR" != "0" ] || [ "$TALK_PATCH" != "0" ] && continue

        EXISTING_RCS=$(git ls-remote --tags origin "refs/tags/v${TALK_MAJOR}.0.0-rc.*" 2>/dev/null | wc -l | tr -d ' ')
        [ "$EXISTING_RCS" -gt 0 ] && continue

        if [ "$FIRST_RC_FOUND" = false ]; then
            print_section "First RC of Major Release — Additional Checks"
            FIRST_RC_FOUND=true
        fi

        print_item "${branch} at v${BRANCH_VERSION} — preparing first RC of Talk ${TALK_MAJOR}"

        print_warning "  Manual: Create 'New in Talk ${TALK_MAJOR}' entries in the 'Talk updates ✅' conversation"
        print_warning "  Manual: Review GDPR document for any new database tables/columns"
        print_info   "  Hint:   Run 'make appstore' to verify packaging exclude list in Makefile is up to date"

        # Dependabot check for this branch (template item: "patch updates to the stable branch")
        if [ -f ".github/dependabot.yml" ]; then
            if grep -qF "target-branch: $branch" .github/dependabot.yml 2>/dev/null; then
                print_success "  dependabot.yml: patch updates configured for $branch"
            else
                print_warning "  dependabot.yml: $branch is missing — add composer and npm patch update entries"
            fi
        fi

        # New DB migrations since last tag (to assist the GDPR check)
        LAST_TAG=$(git describe --tags --abbrev=0 "origin/$branch" 2>/dev/null || echo "")
        if [ -n "$LAST_TAG" ]; then
            NEW_MIGRATIONS=$(git diff --name-only "${LAST_TAG}..origin/${branch}" -- 'lib/Migration/' 2>/dev/null | grep '\.php$' || true)
            if [ -z "$NEW_MIGRATIONS" ]; then
                print_success "  No new DB migration files since $LAST_TAG"
            else
                print_warning "  New DB migration files since $LAST_TAG (verify GDPR document):"
                echo "$NEW_MIGRATIONS" | while read -r f; do echo "      • $f"; done
            fi
        else
            print_info "  No previous tag found — check DB migrations manually"
        fi
    done
fi

# ============================================================================
# 6. Changelog preparation (only when --prepare-changelog is passed)
# ============================================================================

# Increments the last numeric component of a version string
# 24.0.0-rc.3 → 24.0.0-rc.4   |   23.0.5 → 23.0.6
increment_version() {
    local version="$1"
    if [[ "$version" =~ ^(.*\.)([0-9]+)$ ]]; then
        echo "${BASH_REMATCH[1]}$((BASH_REMATCH[2] + 1))"
    else
        echo "$version"
    fi
}

# Outputs a formatted changelog section from a milestone's merged PRs
generate_changelog_section() {
    local milestone_number="$1"
    local version="$2"
    local today
    today=$(date '+%Y-%m-%d')

    local pr_data
    pr_data=$(gh api --paginate "repos/nextcloud/spreed/issues?milestone=${milestone_number}&state=closed&per_page=100" 2>/dev/null || echo "[]")

    local has_deps=false
    local has_l10n=false
    local entries_added=()
    local entries_changed=()
    local entries_fixed=()
    local entries_removed=()

    while IFS=$'\x1f' read -r number title; do
        [ -z "$number" ] && continue

        if echo "$title" | grep -qE '^(chore|build)\(deps'; then
            has_deps=true
            continue
        fi
        if echo "$title" | grep -qiE '^(chore|fix)\(l10n'; then
            has_l10n=true
            continue
        fi

        local link="  [#${number}](https://github.com/nextcloud/spreed/pull/${number})"
        local entry
        entry="- ${title}"$'\n'"${link}"

        if echo "$title" | grep -qE '^feat'; then
            entries_added+=("$entry")
        elif echo "$title" | grep -qE '^fix'; then
            entries_fixed+=("$entry")
        elif echo "$title" | grep -qE '^revert'; then
            entries_removed+=("$entry")
        else
            entries_changed+=("$entry")
        fi
    done < <(echo "$pr_data" | jq -r '.[] | select(.pull_request != null) | [(.number | tostring), .title] | join("")' 2>/dev/null)

    echo "## ${version} – ${today}"
    echo ""

    if [ "${#entries_added[@]}" -gt 0 ]; then
        echo "### Added"
        for e in "${entries_added[@]}"; do printf '%s\n' "$e"; done
        echo ""
    fi

    local show_changed=false
    [ "${#entries_changed[@]}" -gt 0 ] && show_changed=true
    [ "$has_deps" = true ] && show_changed=true
    [ "$has_l10n" = true ] && show_changed=true

    if [ "$show_changed" = true ]; then
        echo "### Changed"
        [ "$has_deps" = true ] && echo "- Update dependencies"
        [ "$has_l10n" = true ] && echo "- Update translations"
        for e in "${entries_changed[@]}"; do printf '%s\n' "$e"; done
        echo ""
    fi

    if [ "${#entries_fixed[@]}" -gt 0 ]; then
        echo "### Fixed"
        for e in "${entries_fixed[@]}"; do printf '%s\n' "$e"; done
        echo ""
    fi

    if [ "${#entries_removed[@]}" -gt 0 ]; then
        echo "### Removed"
        for e in "${entries_removed[@]}"; do printf '%s\n' "$e"; done
        echo ""
    fi
}

# Inserts a changelog section before the first "## " line in a file
prepend_changelog_section() {
    local file="$1"
    local content="$2"

    if [ ! -f "$file" ]; then
        {
            printf '<!--\n'
            printf '  - SPDX-FileCopyrightText: %s Nextcloud GmbH and Nextcloud contributors\n' "$(date +%Y)"
            printf '  - SPDX-License-Identifier: CC0-1.0\n'
            printf '-->\n'
            printf '# Changelog\n'
            printf 'All notable changes to this project will be documented in this file.\n\n'
            printf '%s\n' "$content"
        } > "$file"
        return 0
    fi

    local first_section_line
    first_section_line=$(grep -n '^## ' "$file" | head -1 | cut -d: -f1)

    local tmp
    tmp=$(mktemp)
    if [ -z "$first_section_line" ]; then
        cat "$file" > "$tmp"
        printf '\n%s\n' "$content" >> "$tmp"
    else
        head -n $((first_section_line - 1)) "$file" > "$tmp"
        printf '%s\n\n' "$content" >> "$tmp"
        tail -n +$((first_section_line)) "$file" >> "$tmp"
    fi
    mv "$tmp" "$file"
}

if [ "$PREPARE_CHANGELOG" = true ]; then
    print_section "Changelog"

if [ ${#STABLE_BRANCHES[@]} -eq 0 ]; then
    print_info "No stable branches — nothing to generate"
else
    declare -a CHANGELOG_VERSIONS=()
    declare -A BRANCH_MAJORS=()
    declare -A BRANCH_NEXT_VERSIONS=()
    declare -A BRANCH_SECTIONS=()

    for branch in "${STABLE_BRANCHES[@]}"; do
        if ! git rev-parse --verify "origin/$branch" > /dev/null 2>&1; then
            continue
        fi

        NC_MAJOR=$(echo "$branch" | grep -oE '[0-9.]+')
        BRANCH_VERSION=$(git show "origin/$branch:appinfo/info.xml" 2>/dev/null | xmllint --xpath '/info/version/text()' - 2>/dev/null || echo "")

        if [ -z "$BRANCH_VERSION" ]; then
            print_warning "$branch: could not read version from appinfo/info.xml"
            continue
        fi

        TALK_MAJOR=$(echo "$BRANCH_VERSION" | cut -d. -f1)

        MILESTONE_DATA=$(echo "${MILESTONES_JSON:-[]}" | \
            jq --arg major "$NC_MAJOR" '.[] | select(.title | test("Next Patch \\(" + $major + "\\)"))' 2>/dev/null || echo "")

        if [ -z "$MILESTONE_DATA" ] || [ "$MILESTONE_DATA" = "null" ]; then
            print_warning "$branch: no 'Next Patch ($NC_MAJOR)' milestone found"
            continue
        fi

        MILESTONE_NUMBER=$(echo "$MILESTONE_DATA" | jq -r '.number')
        MILESTONE_TITLE=$(echo "$MILESTONE_DATA" | jq -r '.title')
        MILESTONE_OPEN=$(echo "$MILESTONE_DATA" | jq -r '.open_issues')

        NEXT_VERSION=$(increment_version "$BRANCH_VERSION")

        print_item "${branch}: v${BRANCH_VERSION} → v${NEXT_VERSION} ← ${MILESTONE_TITLE} (${MILESTONE_OPEN} open issues)"

        SECTION=$(generate_changelog_section "$MILESTONE_NUMBER" "$NEXT_VERSION")

        CHANGELOG_VERSIONS+=("v${NEXT_VERSION}")
        BRANCH_MAJORS["$branch"]="$TALK_MAJOR"
        BRANCH_NEXT_VERSIONS["$branch"]="$NEXT_VERSION"
        BRANCH_SECTIONS["$branch"]="$SECTION"
    done

    if [ "${#CHANGELOG_VERSIONS[@]}" -gt 0 ]; then
        print_section "Preparing Changelog Commits"

        TODAY=$(date '+%Y%m%d')
        PR_BRANCH="chore/release/changelog-${TODAY}"
        VERSIONS_STR=$(IFS=", "; echo "${CHANGELOG_VERSIONS[*]}")

        if [ "$DRY_RUN" = true ]; then
            print_info "Branch: $PR_BRANCH (from main)"

            for branch in "${STABLE_BRANCHES[@]}"; do
                MAJOR="${BRANCH_MAJORS[$branch]:-}"
                NEXT_VERSION="${BRANCH_NEXT_VERSIONS[$branch]:-}"
                SECTION="${BRANCH_SECTIONS[$branch]:-}"
                [ -z "$MAJOR" ] || [ -z "$SECTION" ] && continue

                CHANGELOG_FILE="docs/changelogs/changelog-${MAJOR}.md"
                echo ""
                print_info "Commit: chore(release): Changelog for v${NEXT_VERSION}"
                echo -e "  ${CYAN}--- a/${CHANGELOG_FILE}${NC}"
                echo -e "  ${CYAN}+++ b/${CHANGELOG_FILE}${NC}"
                printf '%s\n' "$SECTION" | while IFS= read -r line; do
                    echo -e "  ${GREEN}+${line}${NC}"
                done
            done
            echo ""
        else
            if git rev-parse --verify "$PR_BRANCH" > /dev/null 2>&1; then
                print_warning "Branch '$PR_BRANCH' already exists — delete it first or use a different date suffix"
            else
                git checkout -b "$PR_BRANCH" origin/main > /dev/null 2>&1

                COMMIT_COUNT=0

                for branch in "${STABLE_BRANCHES[@]}"; do
                    MAJOR="${BRANCH_MAJORS[$branch]:-}"
                    NEXT_VERSION="${BRANCH_NEXT_VERSIONS[$branch]:-}"
                    SECTION="${BRANCH_SECTIONS[$branch]:-}"
                    [ -z "$MAJOR" ] || [ -z "$SECTION" ] && continue

                    CHANGELOG_FILE="docs/changelogs/changelog-${MAJOR}.md"
                    prepend_changelog_section "$CHANGELOG_FILE" "$SECTION"
                    git add "$CHANGELOG_FILE"
                    git commit -s -m "chore(release): Changelog for v${NEXT_VERSION}"
                    print_success "Committed $CHANGELOG_FILE"
                    COMMIT_COUNT=$((COMMIT_COUNT + 1))
                done

                echo ""
                print_success "Branch '$PR_BRANCH' ready — review and adjust the changelog, then:"
                echo "  git push -u origin $PR_BRANCH"
                echo "  gh pr create --title \"chore(release): Changelog for ${VERSIONS_STR}\" --base main --body \"\$(git diff HEAD~${COMMIT_COUNT}..HEAD -- docs/changelogs/ | grep '^+[^+]' | sed 's/^+//')\" --repo nextcloud/spreed"
            fi
        fi
    fi
fi

fi # PREPARE_CHANGELOG

# ============================================================================
# 7. Repository Status
# ============================================================================
print_section "Repository Status"

if [ -n "$(git status --porcelain)" ]; then
    print_warning "Uncommitted changes detected:"
    git status --short | sed 's/^/    /'
else
    print_success "Working directory is clean"
fi

# ============================================================================
# Next Steps
# ============================================================================
print_header "Next Steps"

echo ""
echo -e "${CYAN}Address any blockers above, then:${NC}"
echo "  1. Prepare changelog:  make prepare-changelog"
echo "     Review and adjust docs/changelogs/*.md, then push and open a PR"
echo "  2. Follow https://github.com/nextcloud/spreed/issues/5879 template"
