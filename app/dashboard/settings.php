<?php

session_start();

require "incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."incl/lib/configEditor.php";

$dl = new dashboardLib();
$editor = new gdConfigEditor();

$dl->title("Settings");

$adminQuery = $db->prepare("SELECT isAdmin FROM accounts WHERE accountID = :accountID");
$adminQuery->execute([':accountID' => $_SESSION['accountID'] ?? 0]);
$isAdmin = (int)$adminQuery->fetchColumn() === 1;

if(!$isAdmin) {
    $dl->printPage(
        '<div class="gd-card"><h1>Settings</h1><p>You need GDPS administrator access to use this page.</p></div>',
        true,
        "settings"
    );
    $dl->printFooter("../");
    exit;
}

if(!isset($_SESSION['gdips_settings_csrf'])) {
    $_SESSION['gdips_settings_csrf'] = bin2hex(random_bytes(32));
}

$definitions = $editor->getDefinitions();
$file = isset($_GET['file']) ? basename((string)$_GET['file']) : 'dashboard.php';

if(!isset($definitions[$file])) {
    $file = 'dashboard.php';
}

$notice = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedFile = isset($_POST['file']) ? basename((string)$_POST['file']) : $file;
    $csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';

    if(!hash_equals($_SESSION['gdips_settings_csrf'], $csrf)) {
        $error = 'Security token expired. Please reload the page.';
    } elseif(!isset($definitions[$postedFile])) {
        $error = 'Unsupported configuration file.';
    } else {
        try {
            $editor->save($postedFile, $_POST);
            $file = $postedFile;
            $notice = 'Settings saved successfully.';
        } catch(Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$defs = $editor->getFileDefinitions($file);
$values = $editor->getValues($file);

$h = static function($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$tabs = '';
foreach(array_keys($definitions) as $name) {
    $active = ($name === $file) ? ' is-on' : '';
    $href = 'settings.php?file='.rawurlencode($name);
    $tabs .= '<a class="gd-filter'.$active.'" href="'.$h($href).'" onclick="a(\''.$h($href).'\');return false;">'.$h(pathinfo($name, PATHINFO_FILENAME)).'</a>';
}

$content = '
<div class="gd-pagehead">
    <p class="gd-eyebrow">ADMIN</p>
    <div class="gd-pagehead-row">
        <div>
            <h1 class="gd-display">Settings</h1>
            <p class="gd-pagehead-sub">Edit supported GDIPS configuration values without opening PHP files.</p>
        </div>
    </div>
</div>

<div class="gd-toolbar">
    <div class="gd-inlineform">'.$tabs.'</div>
</div>';

if($notice !== '') {
    $content .= '<div class="gd-card" style="margin-bottom:var(--sp-4);"><strong>'.$h($notice).'</strong></div>';
}

if($error !== '') {
    $content .= '<div class="gd-card" style="margin-bottom:var(--sp-4);"><strong>Save failed:</strong> '.$h($error).'</div>';
}

$content .= '
<form method="post" class="gd-card">
    <input type="hidden" name="csrf" value="'.$h($_SESSION['gdips_settings_csrf']).'">
    <input type="hidden" name="file" value="'.$h($file).'">

    <div class="gd-grid gd-grid--2">';

foreach($defs as $name => $definition) {
    $value = $values[$name] ?? '';

    $content .= '
        <div class="form-group">
            <label for="cfg_'.$h($name).'"><strong>'.$h($definition['label']).'</strong></label>';

    if($definition['type'] === 'bool') {
        $checked = !empty($value) ? ' checked' : '';
        $content .= '
            <label class="checkbox">
                <input id="cfg_'.$h($name).'" type="checkbox" name="cfg_'.$h($name).'" value="1"'.$checked.'>
                Enabled
            </label>';
    } elseif($definition['type'] === 'int' || $definition['type'] === 'float') {
        $step = $definition['type'] === 'float' ? ' step="0.1"' : ' step="1"';
        $min = isset($definition['min']) ? ' min="'.$h($definition['min']).'"' : '';
        $max = isset($definition['max']) ? ' max="'.$h($definition['max']).'"' : '';

        $content .= '
            <input
                class="form-control gd-input"
                id="cfg_'.$h($name).'"
                type="number"
                name="cfg_'.$h($name).'"
                value="'.$h($value).'"'.$step.$min.$max.'
            >';
    } else {
        $content .= '
            <input
                class="form-control gd-input"
                id="cfg_'.$h($name).'"
                type="text"
                name="cfg_'.$h($name).'"
                value="'.$h($value).'"
            >';
    }

    $content .= '
        </div>';
}

$content .= '
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:var(--sp-4);">
        <button class="gd-btn gd-btn--primary" type="submit">
            <i class="fa-solid fa-floppy-disk"></i>
            Save settings
        </button>
    </div>
</form>

<div class="gd-card" style="margin-top:var(--sp-4);">
    <strong>Note</strong>
    <p style="margin:var(--sp-2) 0 0;color:var(--tx-2);">
        Sensitive credentials such as database passwords, CAPTCHA secrets and Discord bot tokens are intentionally not exposed here.
    </p>
</div>';

$dl->printPage($content, true, "settings");
$dl->printFooter("../");
?>