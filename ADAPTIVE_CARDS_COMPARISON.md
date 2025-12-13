# Interactive Bot Framework Comparison: Adaptive Cards vs. Proposed Design

## Executive Summary

Microsoft's **Adaptive Cards** is a mature, industry-standard framework for rich, interactive bot experiences. This document compares the proposed Spreed/Talk interactive bot design with Adaptive Cards, identifies gaps, and recommends adopting an Adaptive Cards-compatible approach.

**Recommendation:** Adopt Adaptive Cards schema with Nextcloud-specific extensions rather than creating a proprietary format.

---

## What is Adaptive Cards?

Adaptive Cards is a platform-agnostic standard for exchanging UI content between applications. Key characteristics:

- **JSON-based declarative schema** - Cards defined in structured JSON
- **Platform-native rendering** - Each platform renders cards in its own style
- **Industry adoption** - Used by Microsoft Teams, Outlook, Bot Framework, Cortana, Windows Timeline
- **Open standard** - Schema and SDKs are open-source and extensible
- **Versioned schema** - Current version 1.6 with backwards compatibility

---

## Feature Comparison

### Content Elements

| Feature | Adaptive Cards | Proposed Design | Winner |
|---------|---------------|-----------------|--------|
| **Text blocks** | ✅ TextBlock, RichTextBlock | ✅ (implicit in content.text) | **Adaptive Cards** (more control) |
| **Images** | ✅ Image, ImageSet | ❌ Not in proposal | **Adaptive Cards** |
| **Media** | ✅ Media, MediaSource (audio/video) | ❌ Not in proposal | **Adaptive Cards** |
| **Structured data** | ✅ FactSet (key-value pairs), Table | ❌ Not in proposal | **Adaptive Cards** |
| **Layout containers** | ✅ Container, ColumnSet, Column | ❌ Not in proposal | **Adaptive Cards** |
| **Rich text formatting** | ✅ Markdown support, inline styling | ⚠️ Limited | **Adaptive Cards** |

### Input Types

| Input Type | Adaptive Cards | Proposed Design | Winner |
|------------|---------------|-----------------|--------|
| **Text input** | ✅ Input.Text (single/multiline) | ✅ TextInput type | **Tie** |
| **Number input** | ✅ Input.Number | ❌ Not specified | **Adaptive Cards** |
| **Date picker** | ✅ Input.Date | ❌ Not in proposal | **Adaptive Cards** |
| **Time picker** | ✅ Input.Time | ❌ Not in proposal | **Adaptive Cards** |
| **Toggle/checkbox** | ✅ Input.Toggle | ❌ Not in proposal | **Adaptive Cards** |
| **Choice set** | ✅ Input.ChoiceSet (dropdown, radio, multiselect) | ✅ Menu type, form fields | **Adaptive Cards** (more flexible) |
| **File upload** | ❌ Not supported | ❌ Not in proposal | **Tie** (neither) |

### Actions

| Action Type | Adaptive Cards | Proposed Design | Winner |
|-------------|---------------|-----------------|--------|
| **Submit data** | ✅ Action.Submit | ✅ Button response callback | **Tie** |
| **Open URL** | ✅ Action.OpenUrl | ❌ Not in proposal | **Adaptive Cards** |
| **Show nested card** | ✅ Action.ShowCard | ⚠️ Can replace interaction | **Adaptive Cards** (cleaner) |
| **Toggle visibility** | ✅ Action.ToggleVisibility | ❌ Not in proposal | **Adaptive Cards** |
| **Custom actions** | ✅ Action.Execute (v1.4+) | ✅ Bot webhook callback | **Tie** |

### Advanced Features

| Feature | Adaptive Cards | Proposed Design | Winner |
|---------|---------------|-----------------|--------|
| **Data binding** | ✅ Template expressions `${variable}` | ❌ Static content only | **Adaptive Cards** |
| **Conditional rendering** | ✅ `$when` conditions, visibility rules | ❌ Not in proposal | **Adaptive Cards** |
| **Input validation** | ✅ `isRequired`, regex patterns, min/max | ⚠️ Basic validation only | **Adaptive Cards** |
| **Accessibility** | ✅ Labels, ARIA roles, fallback text | ⚠️ Not specified | **Adaptive Cards** |
| **Responsive layouts** | ✅ Target width hints, adaptive sizing | ❌ Not in proposal | **Adaptive Cards** |
| **Theming** | ✅ Host-defined styling | ✅ Platform renders natively | **Tie** |
| **Refresh mechanism** | ✅ Built-in refresh action | ⚠️ Via bot update API | **Adaptive Cards** |
| **Authentication** | ✅ Action.Authentication | ❌ Not in proposal | **Adaptive Cards** |

---

## Architectural Comparison

### Schema Definition

**Adaptive Cards:**
```json
{
    "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
    "type": "AdaptiveCard",
    "version": "1.5",
    "body": [
        {
            "type": "TextBlock",
            "text": "What is your feedback?",
            "size": "Medium",
            "weight": "Bolder"
        },
        {
            "type": "Input.Text",
            "id": "feedback",
            "placeholder": "Enter your feedback here",
            "isMultiline": true,
            "maxLength": 500
        },
        {
            "type": "Input.ChoiceSet",
            "id": "rating",
            "label": "Rating",
            "style": "compact",
            "isRequired": true,
            "choices": [
                {"title": "⭐ Poor", "value": "1"},
                {"title": "⭐⭐ Fair", "value": "2"},
                {"title": "⭐⭐⭐ Good", "value": "3"},
                {"title": "⭐⭐⭐⭐ Great", "value": "4"},
                {"title": "⭐⭐⭐⭐⭐ Excellent", "value": "5"}
            ]
        }
    ],
    "actions": [
        {
            "type": "Action.Submit",
            "title": "Submit",
            "data": {
                "action": "submit_feedback"
            }
        }
    ]
}
```

**Proposed Design:**
```json
{
    "type": "form",
    "content": {
        "title": "Meeting Feedback",
        "description": "Please rate today's meeting",
        "fields": [
            {
                "id": "rating",
                "type": "select",
                "label": "Overall rating",
                "required": true,
                "options": ["⭐ Poor", "⭐⭐ Fair", "⭐⭐⭐ Good", "⭐⭐⭐⭐ Great", "⭐⭐⭐⭐⭐ Excellent"]
            },
            {
                "id": "comments",
                "type": "textarea",
                "label": "Additional comments",
                "required": false,
                "maxLength": 500
            }
        ],
        "submitLabel": "Submit Feedback"
    }
}
```

**Analysis:**
- Adaptive Cards: More verbose but **more powerful and flexible**
- Proposed: Simpler but **less expressive and extensible**
- Adaptive Cards schema is **self-documenting** with type properties
- Proposed design **locks into specific interaction types** (buttons, forms, etc.)

### Data Flow

**Adaptive Cards:**
```
Bot sends card JSON
  ↓
Host platform renders natively
  ↓
User interacts (fills inputs, clicks Action.Submit)
  ↓
Host collects all input values
  ↓
Host sends JSON payload to bot:
{
    "type": "message",
    "value": {
        "feedback": "Great meeting!",
        "rating": "4",
        "action": "submit_feedback"  // from Action.Submit data
    }
}
  ↓
Bot processes and responds (new card or message)
```

**Proposed Design:**
```
Bot creates interaction via API
  ↓
System message references interaction ID
  ↓
Frontend fetches interaction JSON
  ↓
User interacts
  ↓
Frontend submits response to API
  ↓
API sends webhook to bot
  ↓
Bot responds with update/complete action
  ↓
Frontend polls or receives signaling update
```

**Analysis:**
- Adaptive Cards: **Simpler data flow**, fewer roundtrips
- Proposed: **More server-side control** but higher latency
- Adaptive Cards: **Client-driven** (host handles rendering and submission)
- Proposed: **Server-driven** (explicit interaction state management)

---

## Advantages of Each Approach

### Adaptive Cards Advantages

1. **Industry Standard**
   - Well-documented, mature specification
   - Large ecosystem of tools (designer, samples, SDKs)
   - Familiar to developers from Teams/Outlook bots
   - Cross-platform compatibility (could integrate with external bots)

2. **Rich Feature Set**
   - Complex layouts (columns, containers, tables)
   - Advanced inputs (date/time pickers, toggles)
   - Data binding and templating
   - Conditional rendering

3. **Extensibility**
   - Host can add custom actions and elements
   - Versioning with graceful fallback
   - Schema-driven validation

4. **Developer Experience**
   - Visual designer tool (https://adaptivecards.io/designer/)
   - JSON schema validation in IDEs
   - Extensive documentation and samples
   - Community support

5. **Performance**
   - Card data embedded in message (no extra API calls)
   - Client-side rendering (reduces server load)
   - Static content (cacheable)

### Proposed Design Advantages

1. **Nextcloud-Native**
   - Tightly integrated with Talk's existing architecture
   - Uses familiar patterns (polls, system messages)
   - Consistent with other Talk features

2. **Server-Side Control**
   - Bot can dynamically update interactions
   - Explicit state management
   - Better analytics (server tracks all responses)

3. **Real-Time Updates**
   - WebSocket broadcasting of state changes
   - All participants see updates immediately
   - Collaborative interactions possible

4. **Security**
   - Centralized validation on server
   - Explicit permission checks
   - Audit trail in database

5. **Simplicity (for simple cases)**
   - Pre-defined interaction types reduce boilerplate
   - Guided API (type-specific endpoints)
   - Less complex for basic button/form scenarios

---

## Key Gaps in Proposed Design

Comparing to Adaptive Cards reveals missing capabilities:

### 1. Layout Control
- ❌ No multi-column layouts
- ❌ No image embedding
- ❌ No rich text formatting
- ❌ No factsets or tables

### 2. Input Types
- ❌ No date/time pickers
- ❌ No number inputs with validation
- ❌ No toggle switches
- ❌ No file upload (neither has this)

### 3. Advanced Features
- ❌ No data binding/templating
- ❌ No conditional rendering
- ❌ No input validation rules (regex, ranges)
- ❌ No nested cards
- ❌ No visibility toggling

### 4. Accessibility
- ❌ No explicit ARIA labels
- ❌ No fallback text for unsupported clients
- ❌ No alt text for images

### 5. Developer Experience
- ❌ No visual designer
- ❌ No schema validation
- ❌ No sample library
- ❌ Proprietary format (vendor lock-in)

---

## Recommended Approach: Adaptive Cards with Nextcloud Extensions

### Core Recommendation

**Adopt Adaptive Cards 1.5+ as the base schema**, with Nextcloud-specific extensions for:
- Authentication (Talk bot signatures)
- Real-time updates (WebSocket integration)
- Conversation context (room tokens, participant info)

### Benefits

1. **Leverage existing ecosystem**
   - Use Microsoft's designer tool
   - Refer to comprehensive documentation
   - Compatible with external bot frameworks

2. **Future-proof**
   - Versioned schema with backwards compatibility
   - Ongoing development by Microsoft and community
   - Adoption by other platforms (potential federation)

3. **Developer-friendly**
   - Familiar to bot developers from other platforms
   - Rich tooling and validation
   - Extensive examples

4. **Feature-complete**
   - All the missing capabilities addressed
   - Extensible for future needs
   - Proven at scale

### Implementation Strategy

#### Phase 1: Adaptive Cards Rendering

**Goal:** Render Adaptive Cards sent by bots

1. Add `adaptivecards` npm package to Spreed frontend
2. Create `AdaptiveCardRenderer.vue` component
3. Integrate into `MessageBody.vue` for bot messages
4. Support basic elements: TextBlock, Image, Input.*, Action.Submit

**Bot Message Format:**
```json
{
    "message": "Here's a quick survey",
    "parameters": {
        "object": {
            "type": "adaptivecard",
            "id": "card-123",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [ /* card elements */ ],
                "actions": [ /* card actions */ ]
            }
        }
    }
}
```

#### Phase 2: Action Handling

**Goal:** Handle Action.Submit and send to bot webhook

1. Intercept Action.Submit in renderer
2. Collect input values from card
3. Send webhook to bot with payload:
   ```json
   {
       "type": "adaptivecard_submit",
       "actor": { /* user info */ },
       "target": { /* room info */ },
       "card": {
           "id": "card-123",
           "values": { /* collected inputs */ },
           "data": { /* from Action.Submit data property */ }
       }
   }
   ```

#### Phase 3: Card Updates (Nextcloud Extension)

**Goal:** Allow bots to update cards after submission

1. Add `cardId` to message parameters
2. Store card state in database (optional, for persistence)
3. Bot can send update via:
   ```
   POST /ocs/v2.php/apps/spreed/api/v1/bot/{token}/card/{cardId}
   {
       "card": { /* updated Adaptive Card JSON */ }
   }
   ```
4. Broadcast update via WebSocket
5. Frontend re-renders updated card

#### Phase 4: Nextcloud-Specific Actions (Extension)

**Goal:** Add Talk-specific actions

Define custom actions in `x-nextcloud` namespace:
```json
{
    "type": "Action.Execute",
    "verb": "x-nextcloud.startCall",
    "title": "Start Video Call",
    "data": {
        "callType": "video"
    }
}
```

Supported custom verbs:
- `x-nextcloud.startCall` - Initiate video/audio call
- `x-nextcloud.shareFile` - Share file picker
- `x-nextcloud.mention` - Mention user
- `x-nextcloud.createPoll` - Launch poll creator

#### Phase 5: Real-Time Collaboration (Extension)

**Goal:** Show live responses to all participants

Add Nextcloud-specific card attribute:
```json
{
    "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
    "type": "AdaptiveCard",
    "version": "1.5",
    "x-nextcloud": {
        "collaborative": true,
        "showResponses": true,
        "allowMultipleResponses": false
    },
    "body": [ /* card content */ ]
}
```

When `collaborative: true`:
- Responses visible to all participants (like polls)
- Real-time updates via WebSocket
- Response count shown on card

---

## Migration Path from Proposed Design

If already implementing the proposed design:

### Conversion Layer

Create a translation layer that converts proposed types to Adaptive Cards:

**Button Group → Adaptive Card:**
```javascript
function buttonGroupToAdaptiveCard(content) {
    return {
        type: "AdaptiveCard",
        version: "1.5",
        body: [
            {
                type: "TextBlock",
                text: content.text,
                wrap: true
            }
        ],
        actions: content.buttons.map(btn => ({
            type: "Action.Submit",
            title: btn.label,
            style: btn.style === 'primary' ? 'positive' : 'default',
            data: {
                buttonId: btn.id
            }
        }))
    };
}
```

**Form → Adaptive Card:**
```javascript
function formToAdaptiveCard(content) {
    return {
        type: "AdaptiveCard",
        version: "1.5",
        body: [
            {
                type: "TextBlock",
                text: content.title,
                size: "Medium",
                weight: "Bolder"
            },
            content.description ? {
                type: "TextBlock",
                text: content.description,
                wrap: true,
                isSubtle: true
            } : null,
            ...content.fields.map(field => fieldToAdaptiveInput(field))
        ].filter(Boolean),
        actions: [
            {
                type: "Action.Submit",
                title: content.submitLabel || "Submit"
            }
        ]
    };
}

function fieldToAdaptiveInput(field) {
    const typeMap = {
        'text': 'Input.Text',
        'textarea': 'Input.Text',
        'select': 'Input.ChoiceSet',
        'multiselect': 'Input.ChoiceSet'
    };

    const input = {
        type: typeMap[field.type],
        id: field.id,
        label: field.label,
        isRequired: field.required
    };

    if (field.type === 'textarea') {
        input.isMultiline = true;
    }

    if (field.type === 'select' || field.type === 'multiselect') {
        input.choices = field.options.map((opt, idx) => ({
            title: opt,
            value: String(idx)
        }));
        input.isMultiSelect = field.type === 'multiselect';
    }

    if (field.maxLength) {
        input.maxLength = field.maxLength;
    }

    return input;
}
```

### Deprecation Timeline

1. **Month 1-3:** Implement Adaptive Cards support alongside proposed types
2. **Month 4-6:** Encourage bot developers to migrate to Adaptive Cards
3. **Month 7-9:** Mark proposed types as deprecated
4. **Month 10+:** Remove proposed types (with major version bump)

---

## Example: Meeting Scheduler with Adaptive Cards

### Bot Sends Initial Card

```json
{
    "type": "AdaptiveCard",
    "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
    "version": "1.5",
    "body": [
        {
            "type": "TextBlock",
            "text": "📅 Schedule Your Weekly 1:1",
            "size": "Large",
            "weight": "Bolder"
        },
        {
            "type": "TextBlock",
            "text": "Alice Smith has requested to schedule your weekly 1:1 meeting.",
            "wrap": true,
            "spacing": "Small"
        },
        {
            "type": "FactSet",
            "facts": [
                {
                    "title": "Duration:",
                    "value": "30 minutes"
                },
                {
                    "title": "Suggested time:",
                    "value": "Friday, 2:00 PM"
                }
            ]
        }
    ],
    "actions": [
        {
            "type": "Action.ShowCard",
            "title": "Accept & Schedule",
            "card": {
                "type": "AdaptiveCard",
                "body": [
                    {
                        "type": "Input.Date",
                        "id": "date",
                        "label": "Preferred date",
                        "value": "2025-12-15"
                    },
                    {
                        "type": "Input.Time",
                        "id": "time",
                        "label": "Preferred time",
                        "value": "14:00"
                    },
                    {
                        "type": "Input.Text",
                        "id": "agenda",
                        "label": "Meeting agenda (optional)",
                        "isMultiline": true,
                        "maxLength": 500,
                        "placeholder": "Topics to discuss..."
                    }
                ],
                "actions": [
                    {
                        "type": "Action.Submit",
                        "title": "Confirm",
                        "data": {
                            "action": "schedule_meeting",
                            "cardId": "meeting-abc123"
                        }
                    }
                ]
            }
        },
        {
            "type": "Action.Submit",
            "title": "Decline",
            "style": "destructive",
            "data": {
                "action": "decline_meeting",
                "cardId": "meeting-abc123"
            }
        }
    ]
}
```

### User Submits

Frontend collects all Input.* values and sends webhook:
```json
{
    "type": "adaptivecard_submit",
    "actor": {
        "type": "user",
        "id": "bob",
        "name": "Bob Jones"
    },
    "target": {
        "type": "room",
        "id": "token123",
        "name": "Bob & Alice"
    },
    "card": {
        "id": "meeting-abc123",
        "values": {
            "date": "2025-12-15",
            "time": "14:00",
            "agenda": "Discuss Q1 goals and performance review",
            "action": "schedule_meeting",
            "cardId": "meeting-abc123"
        }
    }
}
```

### Bot Updates Card

Bot sends updated card to replace original:
```json
{
    "type": "AdaptiveCard",
    "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
    "version": "1.5",
    "body": [
        {
            "type": "TextBlock",
            "text": "✅ Meeting Scheduled",
            "size": "Large",
            "weight": "Bolder",
            "color": "Good"
        },
        {
            "type": "FactSet",
            "facts": [
                {
                    "title": "Date:",
                    "value": "Friday, December 15, 2025"
                },
                {
                    "title": "Time:",
                    "value": "2:00 PM - 2:30 PM"
                },
                {
                    "title": "Participants:",
                    "value": "Alice Smith, Bob Jones"
                },
                {
                    "title": "Agenda:",
                    "value": "Discuss Q1 goals and performance review"
                }
            ]
        },
        {
            "type": "TextBlock",
            "text": "📧 Calendar invite sent to both participants",
            "isSubtle": true,
            "spacing": "Small"
        }
    ]
}
```

---

## Open Questions

1. **Should Talk support the full Adaptive Cards 1.5 spec or a subset?**
   - Recommendation: Start with subset (common elements), expand based on demand
   - Use feature detection to gracefully degrade unsupported elements

2. **How to handle Action.OpenUrl security?**
   - Recommendation: Whitelist domains or show confirmation dialog
   - Allow admins to configure allowed URL patterns

3. **Should cards be persistent across sessions?**
   - Recommendation: Yes, store in database with message reference
   - Expire after configurable period (default: 30 days)

4. **How to handle custom host config (styling)?**
   - Recommendation: Define Talk-specific host config in settings
   - Allow theming (light/dark mode) integration

5. **Federation compatibility?**
   - Recommendation: Federated instances exchange card JSON directly
   - Graceful fallback for instances without Adaptive Cards support

---

## Conclusion

**Adaptive Cards is the superior approach** for interactive bot elements in Nextcloud Talk:

✅ **Proven standard** with industry adoption
✅ **Rich feature set** covering all proposed scenarios and more
✅ **Extensible** for Nextcloud-specific needs
✅ **Better developer experience** with tooling and documentation
✅ **Future-proof** with ongoing development

The proposed design, while simpler initially, lacks critical features and creates vendor lock-in. Adopting Adaptive Cards provides immediate value and positions Talk as compatible with the broader bot ecosystem.

**Recommended Next Steps:**
1. Prototype Adaptive Cards renderer in Talk frontend
2. Test with sample cards from designer tool
3. Define Nextcloud-specific extensions (x-nextcloud namespace)
4. Update bot API documentation with Adaptive Cards examples
5. Create migration guide for existing poll-like interactions
6. Gather community feedback via GitHub issue
