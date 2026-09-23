<?php

class gdBadgeLib {
    public static function all() {
        $roleBadges = [];
        $path = __DIR__.'/../../config/badges.php';

        if(file_exists($path)) {
            require $path;
        }

        return is_array($roleBadges) ? $roleBadges : [];
    }

    public static function get($level) {
        $level = (int)$level;
        if($level < 1) return null;

        $badges = self::all();

        return isset($badges[$level]) && is_array($badges[$level])
            ? $badges[$level]
            : null;
    }

    public static function render($level, $class = '', $size = 24) {
        $badge = self::get($level);

        if(!$badge || empty($badge['image'])) {
            return '';
        }

        $image = htmlspecialchars((string)$badge['image'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string)($badge['name'] ?? 'Badge'), ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $size = max(16, min(96, (int)$size));

        return '<img src="'.$image.'" alt="'.$name.'" title="'.$name.'" class="'.$class.'" style="width:'.$size.'px;height:'.$size.'px;object-fit:contain;vertical-align:middle;">';
    }
}
