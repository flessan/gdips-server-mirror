<?php
/*
 * Telegraph Cloud storage for large GDIPS assets.
 *
 * All production values should come from Wasmer/environment secrets.
 * No Telegraph Cloud API key belongs in Git.
 */

$telegraphCloudEnabled = filter_var(
    getenv('GDIPS_TELEGRAPH_CLOUD_ENABLED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);

$telegraphCloudBaseUrl = (string)(getenv('GDIPS_TELEGRAPH_CLOUD_BASE_URL') ?: '');
$telegraphCloudProjectId = (string)(getenv('GDIPS_TELEGRAPH_CLOUD_PROJECT_ID') ?: '');
$telegraphCloudApiKey = (string)(getenv('GDIPS_TELEGRAPH_CLOUD_API_KEY') ?: '');
$telegraphCloudBucket = (string)(getenv('GDIPS_TELEGRAPH_CLOUD_BUCKET') ?: 'gdips');

/*
 * Keep this at or below the Telegraph Cloud object limit.
 * 8 MiB works with the current 10 MiB default.
 * The value can be increased after setting the matching
 * TELEGRAPH_CLOUD_MAX_OBJECT_BYTES on Telegraph Cloud.
 */
$telegraphCloudChunkBytes = (int)(
    getenv('GDIPS_TELEGRAPH_CLOUD_CHUNK_BYTES') ?: (8 * 1024 * 1024)
);
