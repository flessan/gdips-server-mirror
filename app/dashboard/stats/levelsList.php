<?php
session_start();

require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";

$dl = new dashboardLib();

require_once "../".$dbPath."incl/lib/mainLib.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";

$gs = new mainLib();

$dl->title($dl->getLocalizedString("levels"));

/* ------------------------------------------------------------------ *
 *  Pagination
 * ------------------------------------------------------------------ */
if (
    isset($_GET["page"]) &&
    is_numeric($_GET["page"]) &&
    (int)$_GET["page"] > 0
) {
    $actualpage = (int)$_GET["page"];
    $page = ($actualpage - 1) * 10;
} else {
    $actualpage = 1;
    $page = 0;
}

/* ------------------------------------------------------------------ *
 *  Query parameters
 * ------------------------------------------------------------------ */
$search = isset($_GET["search"]) ? (string)$_GET["search"] : "";
$sort = isset($_GET["sort"]) ? (string)$_GET["sort"] : "";

$searchValue = trim(
    ExploitPatch::rucharclean($search)
);

/* ------------------------------------------------------------------ *
 *  Page / route helpers
 * ------------------------------------------------------------------ */
$pagelol = explode("/", $_SERVER["REQUEST_URI"]);
$pagelol = $pagelol[count($pagelol) - 2] . "/" . $pagelol[count($pagelol) - 1];
$pagelol = explode("?", $pagelol, 2)[0];

/* ------------------------------------------------------------------ *
 *  Permissions
 * ------------------------------------------------------------------ */
$modcheck = $gs->checkPermission(
    $_SESSION["accountID"],
    "dashboardModTools"
);

/* ------------------------------------------------------------------ *
 *  Sorting whitelist
 * ------------------------------------------------------------------ */
switch ($sort) {
    case "downloads":
        $orderBy = "downloads DESC";
        break;

    case "likes":
        $orderBy = "likes DESC";
        break;

    case "featured":
        $orderBy = "starEpic DESC, starFeatured DESC, uploadDate DESC";
        break;

    default:
        $sort = "";
        $orderBy = "uploadDate DESC";
        break;
}

/* ------------------------------------------------------------------ *
 *  Query conditions
 * ------------------------------------------------------------------ */
$where = "unlisted = 0";
$params = [];

if ($searchValue !== "") {
    if (is_numeric($searchValue)) {
        $where .= " AND levelID LIKE :search";
    } else {
        $where .= " AND levelName LIKE :search";
    }

    $params[":search"] = "%" . $searchValue . "%";
}

/* ------------------------------------------------------------------ *
 *  Fetch levels
 * ------------------------------------------------------------------ */
$query = $db->prepare(
    "SELECT *
     FROM levels
     WHERE $where
     ORDER BY $orderBy
     LIMIT 10 OFFSET $page"
);

$query->execute($params);

$result = $query->fetchAll();

/* ------------------------------------------------------------------ *
 *  Search bar
 * ------------------------------------------------------------------ */
$searchEscaped = htmlspecialchars(
    $searchValue,
    ENT_QUOTES,
    "UTF-8"
);

$searchLabel = htmlspecialchars(
    $dl->getLocalizedString("search"),
    ENT_QUOTES,
    "UTF-8"
);

$searchbar = '
<form
    name="searchform"
    class="gd-searchbar"
    onsubmit="a(\''.$pagelol.'\', true, true, \'GET\', 69); return false;"
>
    <input
        type="text"
        name="search"
        value="'.$searchEscaped.'"
        placeholder="'.$searchLabel.'"
        aria-label="'.$searchLabel.'"
    >

    <button
        type="submit"
        class="gd-btn gd-btn--secondary"
        title="'.$searchLabel.'"
        aria-label="'.$searchLabel.'"
    >
        <i class="fa-solid fa-magnifying-glass"></i>
    </button>';

if ($searchValue !== "") {
    $searchCancel = htmlspecialchars(
        $dl->getLocalizedString("searchCancel"),
        ENT_QUOTES,
        "UTF-8"
    );

    $searchbar .= '
    <button
        type="button"
        class="gd-btn gd-btn--ghost"
        title="'.$searchCancel.'"
        aria-label="'.$searchCancel.'"
        onclick="a(\''.$pagelol.'\', true, true, \'GET\')"
    >
        <i class="fa-solid fa-xmark"></i>
    </button>';
}

$searchbar .= '
</form>';

/* ------------------------------------------------------------------ *
 *  Sort filter chips
 * ------------------------------------------------------------------ */
$currentSort = $sort;

$chip = function ($sortKey, $label, $icon) use (
    $pagelol,
    $searchValue,
    $currentSort
) {
    $queryParams = [];

    if ($searchValue !== "") {
        $queryParams["search"] = $searchValue;
    }

    if ($sortKey !== "") {
        $queryParams["sort"] = $sortKey;
    }

    $qs = http_build_query($queryParams);

    $href = $pagelol . ($qs !== "" ? "?" . $qs : "");

    $on = (
        $currentSort === $sortKey
        ? " is-on"
        : ""
    );

    $safeHref = htmlspecialchars(
        $href,
        ENT_QUOTES,
        "UTF-8"
    );

    $safeLabel = htmlspecialchars(
        $label,
        ENT_QUOTES,
        "UTF-8"
    );

    $safeIcon = htmlspecialchars(
        $icon,
        ENT_QUOTES,
        "UTF-8"
    );

    return '
    <a
        class="gd-filter'.$on.'"
        href="'.$safeHref.'"
        onclick="a(\''.htmlspecialchars($href, ENT_QUOTES, "UTF-8").'\', true, true); return false;"
    >
        <i class="fa-solid '.$safeIcon.'"></i>
        '.$safeLabel.'
    </a>';
};

$filters =
      $chip(
          "",
          $dl->getLocalizedString("sortNewest"),
          "fa-clock"
      )
    . $chip(
          "downloads",
          $dl->getLocalizedString("sortDownloads"),
          "fa-download"
      )
    . $chip(
          "likes",
          $dl->getLocalizedString("sortLikes"),
          "fa-thumbs-up"
      )
    . $chip(
          "featured",
          $dl->getLocalizedString("featuredOnly"),
          "fa-star"
      );

/* ------------------------------------------------------------------ *
 *  Generate level cards
 * ------------------------------------------------------------------ */
$levels = "";

foreach ($result as $action) {
    $levels .= $dl->generateLevelsCard(
        $action,
        $modcheck
    );
}

/* ------------------------------------------------------------------ *
 *  Total count
 * ------------------------------------------------------------------ */
$query = $db->prepare(
    "SELECT COUNT(*)
     FROM levels
     WHERE $where"
);

$query->execute($params);

$packcount = (int)$query->fetchColumn();

$pagecount = (int)ceil(
    $packcount / 10
);

/* ------------------------------------------------------------------ *
 *  Page header
 * ------------------------------------------------------------------ */
$levelLabel = htmlspecialchars(
    $dl->getLocalizedString("levels"),
    ENT_QUOTES,
    "UTF-8"
);

$pagel = '
<div class="gd-pagehead">
    <p class="gd-eyebrow">GDIPS</p>

    <div class="gd-pagehead-row">
        <div>
            <h1 class="gd-display">
                '.$levelLabel.'
            </h1>

            <p class="gd-pagehead-sub">
                '.number_format($packcount).' '.$levelLabel.'
            </p>
        </div>
    </div>
</div>

<div class="gd-toolbar">
    '.$searchbar.'

    <div class="gd-toolbar-spacer"></div>

    <div class="gd-inlineform">
        '.$filters.'
    </div>
</div>

<div class="gd-list">';

/* ------------------------------------------------------------------ *
 *  Empty state / results
 * ------------------------------------------------------------------ */
if (empty($result)) {
    $emptyMessage = $dl->getLocalizedString(
        $searchValue === ""
            ? "emptyPage"
            : "noResults"
    );

    $pagel .= '
    <div class="gd-empty">
        <i class="fa-solid fa-magnifying-glass"></i>

        <p>
            '.htmlspecialchars(
                $emptyMessage,
                ENT_QUOTES,
                "UTF-8"
            ).'
        </p>';

    if ($searchValue !== "") {
        $searchCancel = htmlspecialchars(
            $dl->getLocalizedString("searchCancel"),
            ENT_QUOTES,
            "UTF-8"
        );

        $pagel .= '
        <button
            type="button"
            class="gd-btn gd-btn--secondary"
            onclick="a(\''.$pagelol.'\', true, true, \'GET\')"
        >
            <i class="fa-solid fa-xmark"></i>
            '.$searchCancel.'
        </button>';
    }

    $pagel .= '
    </div>';
} else {
    $pagel .= $levels;
}

$pagel .= '
</div>';

/* ------------------------------------------------------------------ *
 *  Pagination
 * ------------------------------------------------------------------ */
$bottomrow = $dl->generateBottomRow(
    $pagecount,
    $actualpage
);

/* ------------------------------------------------------------------ *
 *  Render page
 * ------------------------------------------------------------------ */
$dl->printPage(
    $pagel . $bottomrow,
    true,
    "levels"
);

/*
 * IMPORTANT:
 * Footer must be printed AFTER printPage().
 * Otherwise it can appear before the page content.
 */
$dl->printFooter("../");

?>