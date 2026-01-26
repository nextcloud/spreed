# React Permission Integration Guide

This document describes the changes needed for frontend clients to support the new separate reaction permission introduced in Talk 24 (Nextcloud 34).

## Background

Previously, the CHAT permission (128) controlled both posting messages and adding reactions. This has been split into two separate permissions:

- **CHAT (128)**: Post messages and share items
- **REACT (256)**: Add/remove reactions

## New Capability

A new capability `react-permission` indicates server support for the separate reaction permission.

```
features: [..., "react-permission", ...]
```

## Permission Constants

```
PERMISSIONS_REACT = 256
PERMISSIONS_MAX_DEFAULT = 510  // was 254
PERMISSIONS_MAX_CUSTOM = 511   // was 255
```

## Implementation

### Checking if user can react

```javascript
const hasReactPermissionCapability = capabilities.features.includes('react-permission')

const permissionToCheck = hasReactPermissionCapability
    ? PERMISSIONS_REACT   // 256
    : PERMISSIONS_CHAT    // 128 (fallback for older servers)

const canReact = (participant.permissions & permissionToCheck) !== 0
```

### Why the fallback is needed

When federating with older Nextcloud servers or when the desktop client connects to an older server, the `react-permission` capability won't be present. In this case, clients should fall back to checking the CHAT permission, as that's what controlled reactions before this change.

## Migration

The backend migration automatically grants the REACT permission to all users who previously had the CHAT permission, ensuring backward compatibility.

## UI Changes

The permissions editor should show two separate checkboxes:
- "Can post messages" (CHAT - 128)
- "Can add reactions" (REACT - 256)

Instead of the previous combined "Can post messages and reactions" checkbox.
