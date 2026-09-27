# GDIPS + Telegraph Cloud level storage

GDIPS can store newly uploaded level payloads in Telegraph Cloud instead of keeping the full Geometry Dash `levelString` in MariaDB.

## Why

`levels` still owns all searchable metadata (name, author, stars, downloads, dates, song IDs, etc.). The potentially large `levelString` becomes a small `TGCP1:` storage manifest when Telegraph Cloud storage is enabled.

The current Telegraph Cloud object engine has a bounded single-object limit, so GDIPS stores one level as multiple objects:

```
levels/<random-prefix>/part-000000
levels/<random-prefix>/part-000001
...
```

The manifest in `levels.levelString` records the bucket, project, total size, SHA-256 and chunk keys.

## Configure

Edit `config/telegraph.php` in the deployed GDIPS configuration:

- `$telegraphCloudEnabled = true`
- `$telegraphCloudBaseUrl`: the Telegraph Cloud deployment URL
- `$telegraphCloudProjectId`: the project that owns the GDIPS bucket
- `$telegraphCloudApiKey`: a developer API key with `storage:read` and `storage:write`
- `$telegraphCloudBucket`: for example `gdips`
- `$telegraphCloudChunkBytes`: keep it at or below the Telegraph Cloud object limit. The default 8 MiB is intentionally below the current 10 MiB default object limit.

Do not commit the real API key.

## Behavior

### New level

Geometry Dash sends the level to `uploadGJLevel.php`. GDIPS:

1. validates the account and level payload;
2. uploads the payload to Telegraph Cloud in chunks;
3. writes only the compact `TGCP1:` manifest to `levels.levelString`;
4. keeps the level ID and all normal metadata in MariaDB.

### Level update

The update is selected by the Geometry Dash level ID plus the authenticated owner's user ID. The level name is not used to decide which level to update.

GDIPS uploads the replacement objects first, updates MariaDB, then best-effort deletes the previous Telegraph Cloud objects.

### Level download

`downloadGJLevel22.php` accepts both:

- legacy/database-backed levels whose `levelString` is the actual payload;
- Telegraph Cloud-backed levels whose `levelString` starts with `TGCP1:`.

For a Telegraph Cloud manifest, GDIPS authenticates to the object API, downloads every chunk, verifies total length and SHA-256, and reconstructs the normal Geometry Dash response.

### Level deletion

The in-game creator delete endpoint and dashboard delete both delete the database row and best-effort delete any Telegraph Cloud chunks referenced by the old manifest.

Telegraph Cloud is the source of payload bytes for newly stored levels. The Wasmer filesystem is only a non-authoritative compatibility cache.

## Custom songs

The current GDIPS song model already stores a song download URL in `songs.download`; it does not put the MP3 bytes into MariaDB. Telegraph Cloud can therefore be added as an optional mirror for custom audio later without changing the level-storage design.

## Resetting the level catalog

For a clean test installation, reset the level-related MariaDB tables with the checked-in `_updates/reset-levels.sql` file, then deploy GDIPS with Telegraph Cloud enabled and upload a new level from Geometry Dash.

After the first upload:

```sql
SELECT levelID, levelName, userID, extID, LEFT(levelString, 6) AS storage
FROM levels;
```

A Telegraph Cloud-backed level should show `TGCP1:` in the `storage` column.
