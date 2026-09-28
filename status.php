<?php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$services = [
    ['name' => 'Web server', 'detail' => 'PHP request handling', 'url' => './health.php'],
    ['name' => 'Dashboard', 'detail' => 'GDIPS administration', 'url' => './dashboard/'],
    ['name' => 'Game API', 'detail' => 'Geometry Dash endpoints', 'url' => './getGJLevels21.php'],
];

$endpoints = [
    ['method' => 'POST', 'path' => '/getGJLevels21.php', 'label' => 'Level search'],
    ['method' => 'POST', 'path' => '/getGJUsers20.php', 'label' => 'User lookup'],
    ['method' => 'POST', 'path' => '/getGJUserInfo20.php', 'label' => 'User information'],
    ['method' => 'POST', 'path' => '/uploadGJLevel.php', 'label' => 'Level upload'],
    ['method' => 'POST', 'path' => '/downloadGJLevel22.php', 'label' => 'Level download'],
];

$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="index,follow">
<link rel="icon" href="./favicon.svg" type="image/svg+xml">
<title>GDIPS Status & API</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;background:#f6f6f3;color:#171717;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
body{padding:28px}
.wrap{max-width:1000px;margin:auto}
header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}
.brand{font-weight:900;font-size:1.7rem;letter-spacing:-.04em}
.brand span{color:#d9232e}
.muted{color:#686868}
.hero,.card{background:#fff;border:1px solid #ddd;padding:22px}
.hero{margin-bottom:16px}
.hero h1{margin:.2rem 0 .55rem;font-size:clamp(2rem,5vw,4rem);line-height:.98;letter-spacing:-.055em}
.pill{display:inline-flex;align-items:center;gap:8px;border:1px solid #d7d7d7;padding:7px 10px;font-size:.85rem;font-weight:700;background:#fafafa}
.dot{width:8px;height:8px;border-radius:50%;background:#2e9d57}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.card h2{margin:0 0 14px;font-size:1rem}
.service{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid #eee}
.service:first-of-type{border-top:0}
.service strong{display:block}
a{color:inherit}
.button{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #cfcfcf;background:#fff;padding:9px 12px;font-weight:700}
.button.primary{background:#d9232e;border-color:#d9232e;color:#fff}
.code{padding:13px;background:#171717;color:#f5f5f5;overflow:auto;font:13px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace}
.endpoint{display:grid;grid-template-columns:64px 1fr auto;align-items:center;gap:10px;padding:11px 0;border-top:1px solid #eee}
.endpoint:first-of-type{border-top:0}
.method{font-size:.72rem;font-weight:900;letter-spacing:.06em;color:#8a5a00}
.path{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}
footer{margin-top:18px;font-size:.85rem;color:#737373}
@media(max-width:720px){body{padding:16px}.grid{grid-template-columns:1fr}header{align-items:flex-start;flex-direction:column}.endpoint{grid-template-columns:58px 1fr}}
</style>
</head>
<body>
<div class="wrap">
<header>
    <div>
        <div class="brand"><span>GD</span>IPS</div>
        <div class="muted">Geometry Dash Indonesia Private Server</div>
    </div>
    <a class="button" href="./">← Home</a>
</header>

<section class="hero">
    <div class="pill"><span class="dot"></span> Service responding</div>
    <h1>Server status, API map, and quick connect.</h1>
    <p class="muted">A small developer-facing page for checking the deployment without touching the admin panel.</p>
    <div class="code" id="origin"><?php echo $h($origin); ?></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <a class="button primary" href="./dashboard/">Open dashboard</a>
        <a class="button" href="./health.php" target="_blank" rel="noopener">Health JSON</a>
        <button class="button" type="button" id="copy">Copy base URL</button>
    </div>
    <small class="muted" id="note" style="display:block;margin-top:8px"></small>
</section>

<div class="grid">
<section class="card">
    <h2>Core services</h2>
    <?php foreach($services as $service): ?>
        <div class="service">
            <div>
                <strong><?php echo $h($service['name']); ?></strong>
                <span class="muted"><?php echo $h($service['detail']); ?></span>
            </div>
            <a class="button" href="<?php echo $h($service['url']); ?>">Open</a>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Client base URL</h2>
    <p class="muted">Use this as the server origin when configuring a compatible client.</p>
    <div class="code"><?php echo $h($origin); ?></div>
    <p class="muted" style="margin-bottom:0">No trailing slash required.</p>
</section>
</div>

<section class="card" style="margin-top:16px">
    <h2>Common Geometry Dash endpoints</h2>
    <?php foreach($endpoints as $endpoint): ?>
        <div class="endpoint">
            <span class="method"><?php echo $h($endpoint['method']); ?></span>
            <span class="path"><?php echo $h($endpoint['path']); ?></span>
            <span class="muted"><?php echo $h($endpoint['label']); ?></span>
        </div>
    <?php endforeach; ?>
</section>

<footer>
    GDIPS service page · generated by the server at request time.
</footer>
</div>
<script>
const origin = document.getElementById('origin');
const note = document.getElementById('note');
document.getElementById('copy')?.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(origin.textContent.trim());
        note.textContent = 'Base URL copied.';
    } catch {
        note.textContent = 'Copy failed. Select the URL manually.';
    }
});
</script>
</body>
</html>
