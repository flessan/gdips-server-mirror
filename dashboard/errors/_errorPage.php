<?php
/* --------------------------------------------------------------------------
   Shared renderer for standalone HTTP error pages.

   These pages must work even when the database is down, so they never touch
   printNavbar()/printSong(). Set $e (e.g. 404) before including this file.
   The localized strings come straight from the locale files.
   -------------------------------------------------------------------------- */
if(!isset($e)) exit('No error code given');

$base = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);
$base = str_replace('/errors/'.$e.'/index.php', '', $base);
require $_SERVER['DOCUMENT_ROOT'].$base.'/incl/dashboardLib.php';
$dl = new dashboardLib();
$message = $dl->getLocalizedString($e);
$hint = $dl->getLocalizedString($e.'!');
$cssV = time(); /* error pages may be cached aggressively by the web server */
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="dark">
	<meta name="theme-color" content="#100f13">
	<title><?= (int)$e ?> | GDIPS</title>
	<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/incl/ui/tokens.css?v=<?= $cssV ?>">
	<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/incl/ui/base.css?v=<?= $cssV ?>">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Chakra+Petch:wght@500;600;700&display=swap">
	<style>
		body {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px;
			position: relative;
			overflow: hidden;
		}
		body::before {
			content: "";
			position: absolute;
			inset: -20%;
			background-image: var(--kawung-svg);
			background-size: var(--kawung-size);
			opacity: 0.05;
			pointer-events: none;
		}
		.err-card {
			position: relative;
			width: min(440px, 100%);
			text-align: center;
			background: var(--bg-2);
			border: 1px solid var(--ink-line-strong);
			border-radius: var(--r-lg);
			padding: 48px 32px 36px;
			box-shadow: var(--sh-3);
			overflow: hidden;
		}
		.err-card::before {
			content: "";
			position: absolute;
			top: 0; left: 0; right: 0;
			height: 3px;
			background: linear-gradient(90deg, var(--merah), var(--merah) 55%, var(--kuning) 55%, var(--kuning));
		}
		.err-code {
			font-family: var(--font-display);
			font-size: 72px;
			font-weight: 700;
			line-height: 1;
			color: var(--merah-strong);
			margin: 0 0 6px;
		}
		.err-msg { font-size: 18px; font-weight: 600; margin: 0 0 6px; }
		.err-hint { color: var(--tx-3); font-size: 13.5px; margin: 0 0 28px; overflow-wrap: anywhere; }
		.err-brand {
			margin-top: 28px;
			padding-top: 18px;
			border-top: 1px solid var(--ink-line);
			font-size: 12px;
			color: var(--tx-3);
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
		}
		.err-brand b { font-family: var(--font-display); color: var(--tx-2); letter-spacing: 0.06em; }
		.err-brand i { color: var(--merah); font-size: 10px; }
	</style>
</head>
<body>
	<main class="err-card">
		<p class="err-code"><?= (int)$e ?></p>
		<p class="err-msg"><?= htmlspecialchars($message) ?></p>
		<p class="err-hint"><?= htmlspecialchars($hint) ?></p>
		<a class="gd-btn gd-btn--primary" href="<?= htmlspecialchars($base) ?>/"><?= htmlspecialchars($base !== '' ? 'GDIPS' : 'Home') ?></a>
		<div class="err-brand"><b>GDIPS</b><i class="fa-solid fa-heart"></i>Built in Indonesia. Open to everyone.</div>
	</main>
</body>
</html>
