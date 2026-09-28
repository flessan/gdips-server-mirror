<?php
/*
 * Geometry Dash reserves low level IDs for built-in/main levels.
 *
 * Keep database IDs stable, but expose a client-safe ID for user levels
 * in the range 1..22. These IDs are occupied by Geometry Dash's built-in
 * main levels, so custom levels need a different public/client ID while
 * existing database relationships remain unchanged.
 */
const GD_CLIENT_LEVEL_ID_OFFSET = 100000;
const GD_CLIENT_LEVEL_ID_LOW_MAX = 22;

function gdClientLevelID($internalID) {
    $internalID = (int)$internalID;
    if($internalID >= 1 && $internalID <= GD_CLIENT_LEVEL_ID_LOW_MAX) {
        return GD_CLIENT_LEVEL_ID_OFFSET + $internalID;
    }
    return $internalID;
}

function gdInternalLevelID($clientID) {
    $clientID = (int)$clientID;
    $min = GD_CLIENT_LEVEL_ID_OFFSET + 2;
    $max = GD_CLIENT_LEVEL_ID_OFFSET + GD_CLIENT_LEVEL_ID_LOW_MAX;
    if($clientID >= $min && $clientID <= $max) {
        return $clientID - GD_CLIENT_LEVEL_ID_OFFSET;
    }
    return $clientID;
}
?>
