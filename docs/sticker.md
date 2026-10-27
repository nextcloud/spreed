# Sticker API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 36

Stickers are images in the subfolders of a folder the administration configures (app config
`stickers_folder`, e.g. a team folder `Stickers`): every subfolder is a sticker pack, images
directly in the folder form a pack of their own. A user sees the packs of that folder as it is in
their own files. A sticker is sent by sharing its file with the conversation, like any other file
(see [Share a file to the chat](chat.md#share-a-file-to-the-chat)).

## Get the sticker packs

* Required capability: `stickers`
* Method: `GET`
* Endpoint: `/stickers`

* Response:
    - Status code:
        + `200 OK`

    - Data:
        Array of sticker packs, empty when stickers are disabled or the user has no access to the
        folder. Only PNG, JPEG, GIF and WebP images the user may share are listed.

| field      | type   | Description                         |
|------------|--------|-------------------------------------|
| `id`       | int    | File id of the pack's folder        |
| `name`     | string | Name of the pack's folder           |
| `stickers` | array  | The pack's stickers, sorted by name |

Each sticker:

| field      | type   | Description                                                |
|------------|--------|------------------------------------------------------------|
| `id`       | int    | File id of the image                                       |
| `name`     | string | File name without extension                                |
| `path`     | string | Path of the file relative to the user's files, to share it |
| `mimetype` | string | Mime type of the image                                     |
| `etag`     | string | ETag of the file, to refresh cached previews               |
