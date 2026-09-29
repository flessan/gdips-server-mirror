<?php
declare(strict_types=1);

/**
 * Lightsync server status / whitelist endpoint.
 *
 * Compatibility implementation for:
 *   /api/ask.php
 *
 * The official Lightsync client expects JSON with:
 *   version, downloadUrl, maintenance, message, mTimestamp, modlist
 *
 * Keep the nullable version/update fields compatible with the original Lightsync endpoint.
 */

header("Content-Type: text/html; charset=utf-8");

$response = [
    "version" => null,
    "downloadUrl" => null,
    "maintenance" => false,
    "message" => "Update maintenance",
    "mTimestamp" => "4:00 PM | GMT-6",
    "modlist" => [
        "geode.custom-keybinds" => ["allowed" => true],
        "capeling.startpos_switcher" => ["allowed" => true],
        "mat.run-info" => ["allowed" => true],
        "alphalaneous.projectedstars" => ["allowed" => true],
        "firee.goldenbest" => ["allowed" => true],
        "m336.levelinfo" => ["allowed" => true],
        "zilko.platformer_ghosts" => ["allowed" => true],
        "grian.art_importer" => ["allowed" => true],
        "alphalaneous.improved_group_view" => ["allowed" => true],
        "alphalaneous.awesome_modifier_icons" => ["allowed" => true],
        "cdc.level_thumbnails" => ["allowed" => true],
        "ninkaz.editor_utils" => ["allowed" => true],
        "razoom.object_groups" => ["allowed" => true],
        "elohmrow.death_tracker" => ["allowed" => true],
        "weebify.separate_dual_icons" => ["allowed" => true],
        "alk.allium" => ["allowed" => true],
        "alk.ime-input" => ["allowed" => true],
        "undefined0.controllable" => ["allowed" => true],
        "syzzi.click_between_frames" => ["allowed" => true],
        "jouca.badgesapi" => ["allowed" => true],
        "thesillydoggo.icon_kit_switcher" => [
            "allowed" => false,
            "reason" => "This mod allows you to copy icons from other users, so it can be an exploit to have exclusive or locked icons."
        ],
        "fleym.nongd" => [
            "allowed" => false,
            "reason" => "We already have the api of this mod in the song library, you can search songs from this mod there."
        ],
        "cvolton.betterinfo" => [
            "allowed" => false,
            "reason" => "This mod was not intended for private servers."
        ],
        "hiimjustin000.more_icons" => [
            "allowed" => false,
            "reason" => "This mod can cause crashes, and we also add custom icons to the game already."
        ],
        "alphalaneous.to_the_top" => ["allowed" => true],
        "mat.reference-image" => ["allowed" => true],
        "alphalaneous.alphas_reference_image" => ["allowed" => true],
        "pololak.legacy-obj-dl" => ["allowed" => true],
        "alphalaneous.old_color_triggers" => ["allowed" => true],
        "legowiifun.unlisted_objects_in_editor" => ["allowed" => true],
        "prevter.imageplus" => ["allowed" => true],
        "dankmeme.globed2" => ["allowed" => true],
        "cvolton.level-id-api" => ["allowed" => true],
        "cvolton.misc_bugfixes" => ["allowed" => true],
        "razoom.improved_transform_controls" => ["allowed" => true],
        "hjfod.betteredit" => ["allowed" => true],
        "razoom.save_level_data_api" => ["allowed" => true],
        "razoom.named_editor_layers" => ["allowed" => true],
        "prevter.smooth-scroll" => ["allowed" => true],
        "alphalaneous.editortab_api" => ["allowed" => true],
        "flozwer.paimbnails2" => ["allowed" => true],
        "smjs.gdintercept" => ["allowed" => true]
    ]
];

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
