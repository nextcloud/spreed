# Architecture Decision Records (ADRs)

This directory contains Architecture Decision Records (ADRs) for Nextcloud Talk.

## What is an ADR?

An Architecture Decision Record (ADR) captures an important architectural decision made along with its context and consequences. ADRs help:

- Document why decisions were made
- Onboard new team members
- Avoid revisiting settled decisions
- Track the evolution of the architecture
- Provide context for future decisions

## Format

Each ADR follows this structure:

1. **Title** - Short descriptive name
2. **Status** - Proposed, Accepted, Deprecated, Superseded
3. **Context** - What is the issue that we're seeing that is motivating this decision?
4. **Decision** - What is the change that we're actually proposing or have agreed to?
5. **Consequences** - What becomes easier or more difficult to do because of this change?
6. **Alternatives Considered** - What other options were evaluated?
7. **References** - Links to related resources

## Index

| ADR | Title | Status | Date |
|-----|-------|--------|------|
| [0001](0001-adaptive-cards-for-bot-interactions.md) | Adopt Adaptive Cards for Interactive Bot Elements | Proposed | 2025-12-09 |

## Creating a New ADR

1. Copy the template from an existing ADR
2. Number sequentially (e.g., `0002-your-decision.md`)
3. Fill in all sections thoughtfully
4. Submit as a pull request for review
5. Update this index when merged

## Status Definitions

- **Proposed** - Under discussion, not yet decided
- **Accepted** - Decision has been agreed upon and is being/has been implemented
- **Deprecated** - No longer applicable but kept for historical reference
- **Superseded** - Replaced by a newer ADR (link to replacement)

## Best Practices

- Write ADRs **before** implementing significant changes
- Keep them **concise** but **complete**
- Focus on **why**, not just what
- Include **alternatives considered**
- Update status as decisions evolve
- Link to related ADRs

## Questions?

See [Michael Nygard's original ADR article](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions) for more background on the ADR pattern.
