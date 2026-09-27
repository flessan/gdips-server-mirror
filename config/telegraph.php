<?php
/*
 * Telegraph Cloud storage for large GDIPS assets.
 *
 * Keep credentials out of Git. Replace these values in the deployed
 * configuration, or load them from your platform's secret/config system.
 *
 * The level storage adapter uses raw PUT/GET/DELETE requests against the
 * Telegraph Cloud object API. Each level is split into bounded objects so
 * very large Geometry Dash levels do not have to fit inside one provider
 * object or inside MariaDB.
 */

$telegraphCloudEnabled = false;

// Example: https://telestorage.pages.dev
$telegraphCloudBaseUrl = '';

// Project ID from Telegraph Cloud.
$telegraphCloudProjectId = '';

// Developer API key with storage:read and storage:write scopes.
$telegraphCloudApiKey = '';

// Object bucket to use for GDIPS level payloads.
$telegraphCloudBucket = 'gdips';

// Keep this below Telegraph Cloud's default 10 MiB object limit.
$telegraphCloudChunkBytes = 8 * 1024 * 1024;

// Every new upload gets a unique immutable object prefix.
$telegraphCloudLevelPrefix = 'levels';
