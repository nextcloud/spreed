# Conversation sections API

* API v4: Base endpoint `/ocs/v2.php/apps/spreed/api/v4`: since Nextcloud 33

Conversation sections allow users to organize their conversations into custom groups in the sidebar. Each user manages their own sections independently. Favorites are always shown at the top, unsectioned conversations appear in an "Other" group at the bottom.

## Capabilities

* `conversation-sections` - Whether the server supports custom conversation sections

## Section object

| field       | type   | Description                                         |
|-------------|--------|-----------------------------------------------------|
| `id`        | int    | Unique identifier for the section                   |
| `name`      | string | Display name of the section                         |
| `sortOrder` | int    | Position of the section in the list (0-based)       |
| `collapsed` | bool   | Whether the section is collapsed (stored on client) |

## Get all sections

* Method: `GET`
* Endpoint: `/sections`
* Response:
    - Status code:
        + `200 OK`

    - Data: Array of section objects, ordered by `sortOrder`

## Create a section

* Method: `POST`
* Endpoint: `/sections`
* Data:

| field  | type   | Description                  |
|--------|--------|------------------------------|
| `name` | string | Name of the new section      |

* Response:
    - Status code:
        + `201 Created`

    - Data: The created section object

## Update a section

* Method: `PUT`
* Endpoint: `/sections/{sectionId}`
* Data:

| field  | type   | Description                  |
|--------|--------|------------------------------|
| `name` | string | New name for the section     |

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` when the section does not exist or belongs to another user

    - Data: The updated section object

## Delete a section

* Method: `DELETE`
* Endpoint: `/sections/{sectionId}`
* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` when the section does not exist or belongs to another user

!!! note

    When a section is deleted, all conversations assigned to that section are automatically unassigned (moved to the "Other" group).

## Reorder sections

* Method: `PUT`
* Endpoint: `/sections/reorder`
* Data:

| field        | type       | Description                                      |
|--------------|------------|--------------------------------------------------|
| `orderedIds` | list\<int> | Ordered list of all section IDs in desired order  |

* Response:
    - Status code:
        + `200 OK`

    - Data: Array of all section objects with updated `sortOrder` values

## Assign a conversation to a section

* Method: `POST`
* Endpoint: `/room/{token}/section`
* Data:

| field       | type | Description                                            |
|-------------|------|--------------------------------------------------------|
| `sectionId` | int  | ID of the section to assign, or `0` to unassign        |

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` when the conversation or section does not exist

!!! note

    Favorite and archived conversations cannot be assigned to a section. The `sectionId` is returned as part of the conversation object in the [conversation API](conversation.md).

## Sort modes

Conversations within each section can be sorted using a client-side sort mode stored in browser storage:

| Mode             | Behavior                                                                |
|------------------|-------------------------------------------------------------------------|
| `activity`       | Sort by most recent activity (default)                                  |
| `alphabetical`   | Sort alphabetically by display name                                     |
| `type-first`     | Group conversations first, then one-to-one, each sub-group by activity  |

The sort mode applies within each section independently. Section order is determined by the `sortOrder` field and can be changed via the reorder endpoint.
