# ADR 0001: Adopt Adaptive Cards for Interactive Bot Elements

## Status

**Proposed** - 2025-12-09

## Context

Nextcloud Talk currently supports basic bot interactions through text messages and reactions. Bots can:
- Send and receive text messages via webhooks
- Add/remove emoji reactions
- Receive notification of user reactions (with `FEATURE_REACTION` flag)

However, bots cannot request structured input from users or provide rich interactive experiences beyond polls. Use cases that require more sophisticated interactions include:

1. **Form-based data collection** - Gathering structured feedback, registration data, configuration settings
2. **Multi-step workflows** - Guiding users through processes with dynamic branching
3. **Action buttons** - Quick yes/no confirmations, menu selections, action triggers
4. **Rich content display** - Tables, factsets, images, formatted text layouts
5. **Date/time selection** - Scheduling meetings, setting reminders, choosing deadlines

We evaluated two approaches:
1. **Create a proprietary Nextcloud Talk interactive framework** - Custom JSON schema and components
2. **Adopt Adaptive Cards** - Use Microsoft's open-source, platform-agnostic standard

### What are Adaptive Cards?

[Adaptive Cards](https://adaptivecards.io/) is an open-source framework for exchanging card content in a common and consistent way. Key characteristics:

- **JSON-based declarative schema** defining card structure
- **Platform-native rendering** allowing each platform to style cards appropriately
- **Versioned specification** (currently 1.6) with backwards compatibility
- **Industry adoption** by Microsoft Teams, Outlook, Bot Framework, Cortana, Windows Timeline, Webex
- **Rich ecosystem** including visual designer, SDKs, validation tools, sample library

### Evaluation Criteria

We assessed both approaches against these criteria:

| Criterion | Weight | Proprietary | Adaptive Cards |
|-----------|--------|-------------|----------------|
| **Feature completeness** | High | Limited (buttons, forms, text input) | Comprehensive (20+ element types, 6 input types) |
| **Developer experience** | High | Custom documentation needed | Visual designer, extensive docs, familiar to Teams/Outlook developers |
| **Extensibility** | Medium | Full control but starting from scratch | Extension via custom actions/elements in `x-*` namespace |
| **Maintenance burden** | High | Full responsibility for spec evolution | Community-maintained, ongoing development |
| **Cross-platform compatibility** | Medium | Talk-only | Potential federation with external bot frameworks |
| **Implementation effort** | Medium | 12-16 weeks for basic features | 6-8 weeks using existing SDK |
| **Vendor lock-in** | Medium | Complete lock-in to Talk | Open standard, portable bots |

### Feature Gap Analysis

The proprietary approach lacks critical capabilities that Adaptive Cards provides:

**Missing in Proprietary Design:**
- Multi-column layouts (ColumnSet, Column)
- Rich media (Image, ImageSet, Media elements)
- Structured data display (FactSet, Table)
- Date/time pickers (Input.Date, Input.Time)
- Number inputs with validation (Input.Number)
- Toggle switches (Input.Toggle)
- Data binding and templating (`${variable}` expressions)
- Conditional rendering (`$when` conditions)
- Nested cards (Action.ShowCard)
- Visibility controls (Action.ToggleVisibility)
- Comprehensive input validation (regex, min/max, required)
- Refresh actions for dynamic updates
- Accessibility features (ARIA labels, fallback text)

**Present in Both:**
- Text input fields
- Choice sets (dropdowns, radio buttons)
- Button actions
- Submit/callback mechanism

### Developer Experience Comparison

**Adaptive Cards provides:**
- [Visual designer tool](https://adaptivecards.io/designer/) for building cards interactively
- JSON schema validation in IDEs
- Extensive sample library organized by scenario
- Official SDKs for JavaScript, .NET, iOS, Android, React Native
- Active community and support channels
- Comprehensive documentation with tutorials

**Proprietary approach requires:**
- Custom documentation from scratch
- Custom validation tools
- Custom examples and best practices
- Ongoing specification maintenance
- Support burden entirely on Talk team

### Example: Meeting Scheduler

**Adaptive Cards version:**
```json
{
    "type": "AdaptiveCard",
    "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
    "version": "1.5",
    "body": [
        {
            "type": "TextBlock",
            "text": "📅 Schedule Your 1:1 Meeting",
            "size": "Large",
            "weight": "Bolder"
        },
        {
            "type": "Input.Date",
            "id": "date",
            "label": "Preferred date"
        },
        {
            "type": "Input.Time",
            "id": "time",
            "label": "Preferred time"
        },
        {
            "type": "Input.Text",
            "id": "agenda",
            "label": "Agenda (optional)",
            "isMultiline": true
        }
    ],
    "actions": [
        {
            "type": "Action.Submit",
            "title": "Schedule"
        }
    ]
}
```

**Proprietary version would require:**
- Custom type definition for each interaction pattern
- Manual date/time input (text field or custom picker)
- Less flexible layout options
- More verbose for complex scenarios

## Decision

**We will adopt Adaptive Cards as the standard for interactive bot elements in Nextcloud Talk.**

This decision means:

1. **Bots will send Adaptive Card JSON** via bot messages or new dedicated API endpoint
2. **Talk frontend will render cards** using the official [adaptivecards.io JavaScript SDK](https://www.npmjs.com/package/adaptivecards)
3. **User interactions will trigger bot webhooks** following Activity Streams 2.0 format (existing pattern)
4. **Nextcloud-specific extensions** will use the `x-nextcloud` namespace for custom actions
5. **Polls will remain unchanged** as they predate this decision and serve a specific use case

### Phased Implementation

#### Phase 1: Basic Card Rendering (Target: 6 weeks)

**Scope:**
- Add `adaptivecards` npm dependency to Talk frontend
- Create `AdaptiveCardRenderer.vue` component wrapping the SDK
- Integrate renderer into `MessageBody.vue` for bot messages
- Support core elements: TextBlock, Image, Container, ColumnSet
- Support basic inputs: Input.Text, Input.ChoiceSet, Input.Toggle
- Support Action.Submit and Action.OpenUrl

**Bot Message Format:**
```json
{
    "message": "Please fill out this form",
    "parameters": {
        "object": {
            "type": "adaptivecard",
            "id": "card-abc123",
            "card": { /* Adaptive Card JSON */ }
        }
    }
}
```

**API Changes:**
- Add `adaptivecards` capability to capabilities endpoint
- Extend bot message schema to support card objects

#### Phase 2: Action Handling (Target: +3 weeks)

**Scope:**
- Intercept Action.Submit in renderer
- Collect all Input.* values from card
- Send webhook to bot with Activity Streams 2.0 payload:
  ```json
  {
      "type": "adaptivecard_submit",
      "actor": { /* participant info */ },
      "target": { /* conversation info */ },
      "card": {
          "id": "card-abc123",
          "values": { /* collected input values */ },
          "data": { /* from Action.Submit data field */ }
      }
  }
  ```
- Handle Action.OpenUrl with security (whitelist or confirmation)
- Support Action.ShowCard for nested interactions

#### Phase 3: Card Updates & Nextcloud Extensions (Target: +4 weeks)

**Scope:**
- Store card state in database (optional persistence)
- Bot API for updating cards:
  ```
  PUT /ocs/v2.php/apps/spreed/api/v1/bot/{token}/card/{cardId}
  ```
- Real-time card updates via WebSocket signaling
- Custom Nextcloud actions in `x-nextcloud` namespace:
  - `x-nextcloud.startCall` - Initiate video/audio call
  - `x-nextcloud.shareFile` - Open file picker
  - `x-nextcloud.mentionUser` - Mention participant
  - `x-nextcloud.createPoll` - Launch poll creator
- Collaborative mode (show live responses like polls)

**Nextcloud Extension Schema:**
```json
{
    "type": "AdaptiveCard",
    "version": "1.5",
    "x-nextcloud": {
        "collaborative": true,
        "showResponses": true,
        "expiresIn": 3600
    },
    "body": [ /* card content */ ],
    "actions": [
        {
            "type": "Action.Execute",
            "verb": "x-nextcloud.startCall",
            "title": "Start Video Call",
            "data": {"callType": "video"}
        }
    ]
}
```

#### Phase 4: Advanced Features (Target: +3 weeks)

**Scope:**
- Support Action.Execute for custom verbs
- Implement Action.ToggleVisibility
- Support data binding with `${variable}` expressions
- Conditional rendering with `$when` rules
- Input validation enforcement (regex, min/max, required)
- Refresh actions for dynamic updates
- Full Adaptive Cards 1.5 spec compliance

### Backwards Compatibility

**For older clients:**
- Cards will appear as system messages: "🤖 Bot Name sent an interactive card (view in updated client)"
- Include deeplink to web UI for interaction
- Feature gated by `adaptivecards` capability

**For federated instances:**
- Federation handshake includes capability negotiation
- Instances without Adaptive Cards support receive fallback text
- Bot can detect capability and send appropriate format

### Security Considerations

**Action.OpenUrl:**
- Whitelist allowed domains (admin-configurable)
- Show confirmation dialog for external URLs
- Block javascript: and data: URIs
- Validate against SSRF attacks

**Input Validation:**
- Server-side validation of all submitted values
- Sanitize user input in webhook payloads
- Enforce maxLength limits
- Validate against card schema

**Bot Authentication:**
- Existing HMAC-SHA256 signature verification continues
- Card submission follows same security model as message sending
- Rate limiting: max 100 card submissions per user per hour

**XSS Prevention:**
- All text content escaped before rendering
- No HTML allowed in TextBlock elements
- Markdown rendering uses sanitized renderer
- CSP policies prevent script injection

## Consequences

### Positive

1. **Rich Interactive Experiences** - Bots can create sophisticated UIs with forms, buttons, layouts
2. **Developer Familiarity** - Bot developers from Teams/Outlook can reuse knowledge
3. **Tooling & Ecosystem** - Immediate access to designer, samples, documentation
4. **Future-Proof** - Ongoing spec development by Microsoft and community
5. **Cross-Platform Potential** - Cards could work across federated instances with different platforms
6. **Reduced Maintenance** - Spec evolution handled by Adaptive Cards community
7. **Accessibility** - Built-in ARIA support and accessibility features
8. **Faster Development** - ~13 weeks to full implementation vs. 16+ weeks for proprietary

### Negative

1. **Dependency on External Spec** - Changes to Adaptive Cards spec may require updates
2. **SDK Bundle Size** - `adaptivecards` package adds ~150KB to bundle (minified)
3. **Learning Curve** - Team must learn Adaptive Cards schema and renderer
4. **Limited Customization** - Styling constrained by SDK (though this is also a positive for consistency)
5. **Not Talk-Optimized** - Some patterns may be verbose for simple Talk use cases
6. **Microsoft Association** - Some may perceive as vendor favoritism (though it's an open standard)

### Neutral

1. **Existing Polls Unchanged** - Polls remain as-is; no migration required
2. **Gradual Adoption** - Bots can migrate to Adaptive Cards over time
3. **Capability-Gated** - Feature only available on Nextcloud 29+ with Talk 19+

### Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **SDK rendering bugs** | Medium | Medium | Use well-tested SDK version, comprehensive testing, report issues upstream |
| **Performance issues with complex cards** | Low | Medium | Impose card complexity limits (max elements, max nesting), lazy rendering |
| **Security vulnerabilities in SDK** | Low | High | Regular dependency updates, security scanning, CSP policies |
| **Breaking changes in future spec versions** | Low | Medium | Use semantic versioning, support multiple spec versions, gradual migration |
| **User confusion with new UI** | Medium | Low | Clear documentation, onboarding examples, optional bot guides |
| **Bot developer adoption slow** | Medium | Low | Provide migration guide, example bots, visual designer workflow |

## Alternatives Considered

### Alternative 1: Proprietary Framework

**Description:** Custom JSON schema and Vue components for Talk-specific interactions

**Pros:**
- Full control over specification
- Optimized for Talk use cases
- No external dependencies

**Cons:**
- Missing critical features (see Feature Gap Analysis)
- No tooling or ecosystem
- High maintenance burden
- Vendor lock-in
- Longer development timeline

**Why Rejected:** Feature gaps too significant, reinventing the wheel when better solution exists

### Alternative 2: Slack Block Kit

**Description:** Adopt Slack's [Block Kit](https://api.slack.com/block-kit) framework

**Pros:**
- Mature, widely used
- Good documentation
- JavaScript SDK available

**Cons:**
- Slack-specific, not truly open
- Less flexible than Adaptive Cards
- Smaller ecosystem outside Slack
- Proprietary to Slack

**Why Rejected:** Adaptive Cards is more open, has richer features, and is platform-agnostic

### Alternative 3: Discord Message Components

**Description:** Use Discord's [message components](https://discord.com/developers/docs/interactions/message-components) pattern

**Pros:**
- Simple, focused on interactive elements
- Good for buttons and select menus

**Cons:**
- Very limited (only buttons and selects)
- No form inputs
- No layout control
- Not an open standard

**Why Rejected:** Too limited for our needs

### Alternative 4: Extend Polls

**Description:** Generalize poll architecture to support other interaction types

**Pros:**
- Reuses existing patterns
- Familiar to Talk developers
- No new dependencies

**Cons:**
- Polls are purpose-built for voting
- Would require substantial refactoring
- Still results in proprietary format
- No tooling ecosystem

**Why Rejected:** Architectural mismatch, better to use purpose-built solution

## References

- [Adaptive Cards Official Site](https://adaptivecards.io/)
- [Adaptive Cards Schema Explorer](https://adaptivecards.io/explorer/)
- [Adaptive Cards Designer](https://adaptivecards.io/designer/)
- [Adaptive Cards GitHub Repository](https://github.com/microsoft/AdaptiveCards)
- [JavaScript SDK Documentation](https://docs.microsoft.com/en-us/adaptive-cards/sdk/rendering-cards/javascript/getting-started)
- [Activity Streams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/) (used for bot webhooks)
- Talk Bot Documentation: `docs/bots.md`
- Talk Poll Implementation: `lib/Service/PollService.php`, `src/stores/polls.ts`
- Related GitHub Issue: [#16114 Bot Overlay API](https://github.com/nextcloud/spreed/issues/16114)

## Related Decisions

- Future ADR: Authentication mechanism for Action.Execute custom verbs
- Future ADR: Card persistence and expiration policies
- Future ADR: Federation protocol for Adaptive Cards exchange
- Future ADR: Accessibility compliance for rendered cards

## Notes

- This ADR focuses on **interactive bot elements**, not general message formatting
- **Polls will not be migrated** to Adaptive Cards as they serve a specific, well-functioning purpose
- **Message formatting** (markdown, mentions, etc.) remains unchanged
- Bot developers can use **both text messages and Adaptive Cards** - they are complementary
- Implementation will be **capability-gated** to ensure backwards compatibility

---

**Author:** Architecture Team
**Date:** 2025-12-09
**Last Updated:** 2025-12-09
**Supersedes:** None
**Superseded By:** None
