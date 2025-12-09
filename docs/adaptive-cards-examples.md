# Adaptive Cards Examples for Nextcloud Talk Bots

This document provides practical examples of using Adaptive Cards in Nextcloud Talk bots.

## Prerequisites

- Nextcloud 29+ with Talk 19+
- Bot installed and enabled for your conversation
- `adaptivecards` capability available

## Example 1: Simple Feedback Form

A basic card with text input and choice set for collecting meeting feedback.

### Bot Message Payload

```json
{
    "message": "📝 Please provide your feedback on today's meeting",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "feedback-2025-01-15-001",
            "bot-name": "Meeting Assistant",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "TextBlock",
                        "text": "Meeting Feedback",
                        "size": "Large",
                        "weight": "Bolder"
                    },
                    {
                        "type": "TextBlock",
                        "text": "Help us improve future meetings",
                        "isSubtle": true,
                        "wrap": true
                    },
                    {
                        "type": "Input.ChoiceSet",
                        "id": "rating",
                        "label": "Overall rating",
                        "isRequired": true,
                        "style": "compact",
                        "choices": [
                            {"title": "⭐ Poor", "value": "1"},
                            {"title": "⭐⭐ Fair", "value": "2"},
                            {"title": "⭐⭐⭐ Good", "value": "3"},
                            {"title": "⭐⭐⭐⭐ Great", "value": "4"},
                            {"title": "⭐⭐⭐⭐⭐ Excellent", "value": "5"}
                        ]
                    },
                    {
                        "type": "Input.Text",
                        "id": "comments",
                        "label": "What went well?",
                        "isMultiline": true,
                        "maxLength": 500,
                        "placeholder": "Share what you appreciated..."
                    },
                    {
                        "type": "Input.Text",
                        "id": "improvements",
                        "label": "What could be improved?",
                        "isMultiline": true,
                        "maxLength": 500,
                        "placeholder": "Share your suggestions..."
                    }
                ],
                "actions": [
                    {
                        "type": "Action.Submit",
                        "title": "Submit Feedback",
                        "style": "positive"
                    }
                ]
            }
        }
    }
}
```

### Expected Webhook Payload

When user submits:

```json
{
    "type": "adaptivecard_submit",
    "actor": {
        "type": "Person",
        "id": "users/alice",
        "name": "Alice Smith"
    },
    "target": {
        "type": "Collection",
        "id": "abc123xyz",
        "name": "Team Standup"
    },
    "card": {
        "id": "feedback-2025-01-15-001",
        "values": {
            "rating": "4",
            "comments": "Great energy and participation",
            "improvements": "Could use better time management"
        }
    }
}
```

## Example 2: Meeting Scheduler

An advanced card with date/time pickers and multiple actions.

### Bot Message Payload

```json
{
    "message": "🗓️ Schedule your 1:1 meeting",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "schedule-one-on-one-456",
            "bot-name": "Calendar Bot",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "ColumnSet",
                        "columns": [
                            {
                                "type": "Column",
                                "width": "auto",
                                "items": [
                                    {
                                        "type": "Image",
                                        "url": "https://adaptivecards.io/content/cats/1.png",
                                        "size": "Small",
                                        "style": "Person"
                                    }
                                ]
                            },
                            {
                                "type": "Column",
                                "width": "stretch",
                                "items": [
                                    {
                                        "type": "TextBlock",
                                        "text": "Schedule 1:1 Meeting",
                                        "weight": "Bolder",
                                        "size": "Medium"
                                    },
                                    {
                                        "type": "TextBlock",
                                        "text": "Bob Jones has requested a 30-minute meeting",
                                        "isSubtle": true,
                                        "wrap": true
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "type": "FactSet",
                        "facts": [
                            {
                                "title": "Duration:",
                                "value": "30 minutes"
                            },
                            {
                                "title": "Suggested:",
                                "value": "Friday, 2:00 PM"
                            }
                        ]
                    },
                    {
                        "type": "Input.Date",
                        "id": "date",
                        "label": "Preferred date",
                        "value": "2025-01-17"
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
                        "placeholder": "Topics to discuss..."
                    }
                ],
                "actions": [
                    {
                        "type": "Action.Submit",
                        "title": "Confirm",
                        "style": "positive",
                        "data": {
                            "action": "confirm"
                        }
                    },
                    {
                        "type": "Action.Submit",
                        "title": "Suggest Different Time",
                        "data": {
                            "action": "suggest"
                        }
                    },
                    {
                        "type": "Action.Submit",
                        "title": "Decline",
                        "style": "destructive",
                        "data": {
                            "action": "decline"
                        }
                    }
                ]
            }
        }
    }
}
```

## Example 3: Quick Poll Alternative

Using buttons for simple yes/no questions.

### Bot Message Payload

```json
{
    "message": "❓ Quick question",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "quick-poll-789",
            "bot-name": "Team Bot",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "TextBlock",
                        "text": "Team Lunch this Friday?",
                        "size": "Large",
                        "weight": "Bolder"
                    },
                    {
                        "type": "TextBlock",
                        "text": "We're thinking of getting lunch together at 12:30 PM. Who's in?",
                        "wrap": true
                    }
                ],
                "actions": [
                    {
                        "type": "Action.Submit",
                        "title": "✅ Count me in!",
                        "style": "positive",
                        "data": {
                            "response": "yes"
                        }
                    },
                    {
                        "type": "Action.Submit",
                        "title": "❌ Can't make it",
                        "style": "destructive",
                        "data": {
                            "response": "no"
                        }
                    },
                    {
                        "type": "Action.Submit",
                        "title": "🤔 Maybe",
                        "data": {
                            "response": "maybe"
                        }
                    }
                ]
            }
        }
    }
}
```

## Example 4: Multi-Step Form with ShowCard

Using nested cards for progressive disclosure.

### Bot Message Payload

```json
{
    "message": "🎫 Create a support ticket",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "support-ticket-001",
            "bot-name": "Support Bot",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "TextBlock",
                        "text": "Create Support Ticket",
                        "size": "Large",
                        "weight": "Bolder"
                    },
                    {
                        "type": "Input.ChoiceSet",
                        "id": "category",
                        "label": "Issue category",
                        "isRequired": true,
                        "style": "compact",
                        "choices": [
                            {"title": "🐛 Bug Report", "value": "bug"},
                            {"title": "💡 Feature Request", "value": "feature"},
                            {"title": "❓ Question", "value": "question"},
                            {"title": "⚙️ Configuration Help", "value": "config"}
                        ]
                    }
                ],
                "actions": [
                    {
                        "type": "Action.ShowCard",
                        "title": "Continue",
                        "card": {
                            "type": "AdaptiveCard",
                            "body": [
                                {
                                    "type": "Input.Text",
                                    "id": "title",
                                    "label": "Ticket title",
                                    "isRequired": true,
                                    "placeholder": "Brief description of the issue"
                                },
                                {
                                    "type": "Input.Text",
                                    "id": "description",
                                    "label": "Detailed description",
                                    "isMultiline": true,
                                    "maxLength": 2000,
                                    "placeholder": "Please provide as much detail as possible..."
                                },
                                {
                                    "type": "Input.ChoiceSet",
                                    "id": "priority",
                                    "label": "Priority",
                                    "value": "medium",
                                    "choices": [
                                        {"title": "🔴 High", "value": "high"},
                                        {"title": "🟡 Medium", "value": "medium"},
                                        {"title": "🟢 Low", "value": "low"}
                                    ]
                                }
                            ],
                            "actions": [
                                {
                                    "type": "Action.Submit",
                                    "title": "Create Ticket",
                                    "style": "positive"
                                }
                            ]
                        }
                    }
                ]
            }
        }
    }
}
```

## Example 5: Information Display with Actions

Card that displays information and provides action buttons.

### Bot Message Payload

```json
{
    "message": "📊 Weekly Report",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "weekly-report-123",
            "bot-name": "Analytics Bot",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "Container",
                        "style": "emphasis",
                        "items": [
                            {
                                "type": "TextBlock",
                                "text": "Weekly Performance Report",
                                "size": "Large",
                                "weight": "Bolder"
                            },
                            {
                                "type": "TextBlock",
                                "text": "Week of January 8-14, 2025",
                                "isSubtle": true
                            }
                        ]
                    },
                    {
                        "type": "FactSet",
                        "facts": [
                            {
                                "title": "Tasks Completed:",
                                "value": "47"
                            },
                            {
                                "title": "Team Velocity:",
                                "value": "+12%"
                            },
                            {
                                "title": "On-Time Delivery:",
                                "value": "94%"
                            },
                            {
                                "title": "Customer Satisfaction:",
                                "value": "4.8/5.0"
                            }
                        ]
                    },
                    {
                        "type": "Container",
                        "style": "good",
                        "items": [
                            {
                                "type": "TextBlock",
                                "text": "✅ All sprint goals achieved!",
                                "weight": "Bolder",
                                "color": "Good"
                            }
                        ]
                    }
                ],
                "actions": [
                    {
                        "type": "Action.OpenUrl",
                        "title": "View Full Report",
                        "url": "https://example.com/reports/2025-W02"
                    },
                    {
                        "type": "Action.Submit",
                        "title": "Share with Leadership",
                        "data": {
                            "action": "share",
                            "report_id": "2025-W02"
                        }
                    }
                ]
            }
        }
    }
}
```

## Testing Your Adaptive Cards

### 1. Use the Official Designer

Test your card JSON at [https://adaptivecards.io/designer/](https://adaptivecards.io/designer/)

### 2. Send via Bot API

```bash
#!/bin/bash

NC_URL="https://nextcloud.example.com"
TOKEN="conversation-token"
SECRET="bot-shared-secret"

# Your Adaptive Card JSON (escaped)
CARD_JSON=$(cat <<'EOF'
{
    "message": "Test card",
    "parameters": {
        "adaptivecard": {
            "type": "adaptivecard",
            "id": "test-001",
            "bot-name": "Test Bot",
            "card": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.5",
                "body": [
                    {
                        "type": "TextBlock",
                        "text": "Hello World!",
                        "size": "Large"
                    }
                ],
                "actions": [
                    {
                        "type": "Action.Submit",
                        "title": "Click me"
                    }
                ]
            }
        }
    }
}
EOF
)

# Generate signature
RANDOM_HEADER=$(openssl rand -hex 32)
MESSAGE_TO_SIGN="${RANDOM_HEADER}${CARD_JSON}"
SIGNATURE=$(echo -n "${MESSAGE_TO_SIGN}" | openssl dgst -sha256 -hmac "${SECRET}" | cut -d' ' -f2)

# Send message
curl -X POST "${NC_URL}/ocs/v2.php/apps/spreed/api/v1/bot/${TOKEN}/message" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "OCS-APIRequest: true" \
  -H "X-Nextcloud-Talk-Bot-Random: ${RANDOM_HEADER}" \
  -H "X-Nextcloud-Talk-Bot-Signature: ${SIGNATURE}" \
  -d "${CARD_JSON}"
```

## Best Practices

1. **Keep IDs Unique**: Use UUIDs or timestamp-based IDs for cards
2. **Validate Required Fields**: Use `isRequired: true` for mandatory inputs
3. **Provide Placeholders**: Help users understand what to enter
4. **Use Appropriate Input Types**: Date pickers for dates, ChoiceSet for selections
5. **Limit maxLength**: Prevent excessive input (reasonable default: 500-2000 chars)
6. **Test Thoroughly**: Verify cards in the designer before deploying
7. **Handle Errors Gracefully**: Bots should validate submission data
8. **Provide Feedback**: Send confirmation message after processing submission

## Common Pitfalls

1. **Missing Card ID**: Always include unique `id` in the adaptivecard parameter
2. **Invalid JSON**: Validate JSON before sending (use linters)
3. **Unsupported Elements**: Stick to Adaptive Cards 1.5 schema
4. **Missing Actions**: Cards without actions aren't very useful
5. **No Input IDs**: All inputs must have unique `id` fields
6. **Incorrect Schema Version**: Use `"version": "1.5"` for best compatibility

## Resources

- [Adaptive Cards Designer](https://adaptivecards.io/designer/) - Visual card builder
- [Schema Explorer](https://adaptivecards.io/explorer/) - Browse all available elements
- [Samples Library](https://adaptivecards.io/samples/) - Example cards for inspiration
- [Talk Bot Documentation](bots.md) - Nextcloud Talk bot API reference
