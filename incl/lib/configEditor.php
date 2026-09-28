<?php

class gdConfigEditor {
    private $root;

    private static $definitions = [
        'dashboard.php' => [
            'gdps' => ['label' => 'GDPS name', 'type' => 'string'],
            'lrEnabled' => ['label' => 'Level reupload', 'type' => 'bool'],
            'msgEnabled' => ['label' => 'Messenger', 'type' => 'bool'],
            'clansEnabled' => ['label' => 'Clans', 'type' => 'bool'],
            'songEnabled' => ['label' => 'Song features', 'type' => 'int', 'min' => 0, 'max' => 12],
            'sfxEnabled' => ['label' => 'SFX upload', 'type' => 'bool'],
            'convertEnabled' => ['label' => 'SFX conversion', 'type' => 'bool'],
            'songSize' => ['label' => 'Maximum song size (MB)', 'type' => 'float', 'min' => 0, 'max' => 1024],
            'sfxSize' => ['label' => 'Maximum SFX size (MB)', 'type' => 'float', 'min' => 0, 'max' => 1024],
            'timeType' => ['label' => 'Time display type', 'type' => 'int', 'min' => 0, 'max' => 2],
            'dashboardIcon' => ['label' => 'Dashboard icon URL', 'type' => 'string'],
            'dashboardFavicon' => ['label' => 'Dashboard favicon URL', 'type' => 'string'],
            'preenableSongs' => ['label' => 'Pre-enable songs', 'type' => 'bool'],
            'preenableSFXs' => ['label' => 'Pre-enable SFX', 'type' => 'bool'],
            'pc' => ['label' => 'Windows download URL', 'type' => 'string'],
            'mac' => ['label' => 'macOS download URL', 'type' => 'string'],
            'android' => ['label' => 'Android download URL', 'type' => 'string'],
            'ios' => ['label' => 'iOS download URL', 'type' => 'string'],
            'pcLauncher' => ['label' => 'Windows launcher filename', 'type' => 'string'],
            'macLauncher' => ['label' => 'macOS launcher filename', 'type' => 'string'],
            'androidLauncher' => ['label' => 'Android launcher filename', 'type' => 'string'],
            'iosLauncher' => ['label' => 'iOS launcher filename', 'type' => 'string'],
            'vk' => ['label' => 'VK URL', 'type' => 'string'],
            'discord' => ['label' => 'Discord URL', 'type' => 'string'],
            'twitter' => ['label' => 'X/Twitter URL', 'type' => 'string'],
            'youtube' => ['label' => 'YouTube URL', 'type' => 'string'],
            'twitch' => ['label' => 'Twitch URL', 'type' => 'string'],
            'requireAccountForReuploading' => ['label' => 'Require account for level reupload', 'type' => 'bool'],
            'disallowReuploadingNotUserLevels' => ['label' => 'Only allow reuploading own levels', 'type' => 'bool'],
            'useCobalt' => ['label' => 'Use Cobalt for song links', 'type' => 'bool'],
            'iconsRendererServer' => ['label' => 'Icons renderer server', 'type' => 'string'],
        ],
        'security.php' => [
            'unregisteredSubmissions' => ['label' => 'Allow unregistered submissions', 'type' => 'bool'],
            'preactivateAccounts' => ['label' => 'Pre-activate accounts', 'type' => 'bool'],
            'filterUsernames' => ['label' => 'Username filter mode', 'type' => 'int', 'min' => 0, 'max' => 2],
            'filterClanNames' => ['label' => 'Clan name filter mode', 'type' => 'int', 'min' => 0, 'max' => 2],
            'filterClanTags' => ['label' => 'Clan tag filter mode', 'type' => 'int', 'min' => 0, 'max' => 2],
            'enableCaptcha' => ['label' => 'Enable CAPTCHA', 'type' => 'bool'],
            'captchaType' => ['label' => 'CAPTCHA type', 'type' => 'int', 'min' => 1, 'max' => 3],
            'blockFreeProxies' => ['label' => 'Block free proxies', 'type' => 'bool'],
            'blockCommonVPNs' => ['label' => 'Block common VPNs', 'type' => 'bool'],
            'warningsPeriod' => ['label' => 'Automod warning period (seconds)', 'type' => 'int', 'min' => 0, 'max' => 31536000],
            'levelsCountModifier' => ['label' => 'Level warning modifier', 'type' => 'float', 'min' => 0.1, 'max' => 100],
            'levelsCheckPeriod' => ['label' => 'Level warning period (seconds)', 'type' => 'int', 'min' => 0, 'max' => 31536000],
            'accountsCountModifier' => ['label' => 'Account warning modifier', 'type' => 'float', 'min' => 0.1, 'max' => 100],
            'accountsCheckPeriod' => ['label' => 'Account warning period (seconds)', 'type' => 'int', 'min' => 0, 'max' => 31536000],
            'commentsCheckPeriod' => ['label' => 'Comment spam period (seconds)', 'type' => 'int', 'min' => 0, 'max' => 31536000],
        ],
        'misc.php' => [
            'orderMapPacksByStars' => ['label' => 'Order map packs by stars', 'type' => 'bool'],
            'sakujes' => ['label' => 'Enable SAKUJES joke', 'type' => 'bool'],
            'unlistedCreatorPoints' => ['label' => 'Count unlisted levels for creator points', 'type' => 'bool'],
            'enableCommentLengthLimiter' => ['label' => 'Limit comment length', 'type' => 'bool'],
            'maxCommentLength' => ['label' => 'Maximum level comment length', 'type' => 'int', 'min' => 1, 'max' => 10000],
            'maxAccountCommentLength' => ['label' => 'Maximum profile comment length', 'type' => 'int', 'min' => 1, 'max' => 10000],
            'oldDailyWeekly' => ['label' => 'Keep expired daily/weekly levels visible', 'type' => 'bool'],
            'minGameVersion' => ['label' => 'Minimum game version', 'type' => 'int', 'min' => 0, 'max' => 999],
            'maxGameVersion' => ['label' => 'Maximum game version', 'type' => 'int', 'min' => 0, 'max' => 999],
            'minBinaryVersion' => ['label' => 'Minimum binary version', 'type' => 'int', 'min' => 0, 'max' => 999],
            'maxBinaryVersion' => ['label' => 'Maximum binary version', 'type' => 'int', 'min' => 0, 'max' => 999],
            'showAllLevels' => ['label' => 'Show levels from newer versions', 'type' => 'bool'],
            'leaderboardMinStars' => ['label' => 'Minimum leaderboard stars', 'type' => 'int', 'min' => 0, 'max' => 100000],
            'ratedLevelsUpdates' => ['label' => 'Allow rated level updates', 'type' => 'bool'],
            'commentAutoLike' => ['label' => 'Multiply comment likes', 'type' => 'bool'],
            'unlistedLevelsForAdmins' => ['label' => 'Show unlisted levels to admins', 'type' => 'bool'],
            'ratedLevelsInSent' => ['label' => 'Show rated levels in sent tab', 'type' => 'bool'],
            'moderatorsListInGlobal' => ['label' => 'Use moderator list as global leaderboard', 'type' => 'bool'],
            'automaticCron' => ['label' => 'Run cron automatically', 'type' => 'bool'],
        ],
    ];

    public function __construct($root = null) {
        $root = $root ?: realpath(__DIR__ . '/../../');
        if($root === false || !is_dir($root)) {
            throw new RuntimeException('GDIPS root directory could not be resolved.');
        }
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    public function getDefinitions() {
        return self::$definitions;
    }

    public function getFileDefinitions($file) {
        if(!isset(self::$definitions[$file])) {
            throw new InvalidArgumentException('Unsupported configuration file.');
        }
        return self::$definitions[$file];
    }

    public function getValues($file) {
        $defs = $this->getFileDefinitions($file);
        $path = $this->path($file);

        $loader = function() use ($path, $defs) {
            include $path;
            $values = [];

            foreach($defs as $name => $definition) {
                $values[$name] = isset($$name)
                    ? $$name
                    : $this->defaultValue($definition['type']);
            }

            return $values;
        };

        return $loader();
    }

    public function save($file, $input) {
        $defs = $this->getFileDefinitions($file);
        $path = $this->path($file);

        if(!is_readable($path) || !is_writable($path)) {
            throw new RuntimeException('Configuration file is not writable by PHP.');
        }

        $source = file_get_contents($path);

        if($source === false) {
            throw new RuntimeException('Could not read configuration file.');
        }

        foreach($defs as $name => $definition) {
            $value = $this->normalize($name, $definition, $input);

            $pattern = '~^(\s*)\$'.preg_quote($name, '~').'\s*=\s*([^;]*);([^\r\n]*)$~m';
            $replacement = '$1$'.$name.' = '.var_export($value, true).';$3';

            $updated = preg_replace($pattern, $replacement, $source, 1, $count);

            if($updated === null || $count !== 1) {
                throw new RuntimeException('Could not update configuration variable: $'.$name);
            }

            $source = $updated;
        }

        $tmp = $path.'.tmp.'.bin2hex(random_bytes(4));

        if(file_put_contents($tmp, $source, LOCK_EX) === false) {
            throw new RuntimeException('Could not write temporary configuration file.');
        }

        if(!@rename($tmp, $path)) {
            if(!@copy($tmp, $path)) {
                @unlink($tmp);
                throw new RuntimeException('Could not replace configuration file.');
            }
            @unlink($tmp);
        }

        return true;
    }

    private function path($file) {
        return $this->root.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.$file;
    }

    private function defaultValue($type) {
        if($type === 'bool') return false;
        if($type === 'int' || $type === 'float') return 0;
        return '';
    }

    private function normalize($name, $definition, $input) {
        $type = $definition['type'];

        if($type === 'bool') {
            return isset($input['cfg_'.$name]) && (string)$input['cfg_'.$name] === '1';
        }

        $raw = isset($input['cfg_'.$name])
            ? trim((string)$input['cfg_'.$name])
            : '';

        if($type === 'int') {
            if($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
                throw new InvalidArgumentException('Invalid integer for $'.$name.'.');
            }
            $value = (int)$raw;
        } elseif($type === 'float') {
            if($raw === '' || !is_numeric($raw)) {
                throw new InvalidArgumentException('Invalid number for $'.$name.'.');
            }
            $value = (float)$raw;
        } else {
            if(strlen($raw) > 1000) {
                throw new InvalidArgumentException('Value for $'.$name.' is too long.');
            }
            $value = $raw;
        }

        if(isset($definition['min']) && $value < $definition['min']) {
            throw new InvalidArgumentException('$'.$name.' is below the allowed minimum.');
        }

        if(isset($definition['max']) && $value > $definition['max']) {
            throw new InvalidArgumentException('$'.$name.' is above the allowed maximum.');
        }

        return $value;
    }
}
