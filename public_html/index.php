<?php
// Define variables for easy maintenance
 $pageTitle = "Geometry Dash Indonesia Private Server";
 $pageDescription = "Geometry Dash Indonesia Private Server is a private server for Geometry Dash players in Indonesia.";
// Using the base URL for SEO meta tags to avoid crawler auth-key expiration issues
 $baseUrl = "https://z-cdn-media.chatglm.cn/files/b8c6cefc-cd48-4318-9a31-26849bffd07e.jpg";
 $GDID = "./dashboard";
 $fullImageUrl = "https://z-cdn-media.chatglm.cn/files/b8c6cefc-cd48-4318-9a31-26849bffd07e.jpg?auth_key=1890160126-e78e468fd02543758ac773525999b74b-0-fdd24c536ccc32cba9f928a2466ca0bb";
 $artCreditUrl = "https://gamejolt.com/p/pfp-icon-geometry-dash-galactic-bg-comission-do-you-need-a-comissio-8frzdxpg";
 $imageAlt = "Purple retro monitor with yellow emoji face, radial orange and purple cosmic rays, and a blue grid floor.";
 $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./favicon.svg" type="image/svg+xml">
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($baseUrl); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($baseUrl); ?>">
    <meta property="og:image:width" content="1500">
    <meta property="og:image:height" content="500">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($imageAlt); ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($baseUrl); ?>">

    <!-- Structured Data for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "VisualArtwork",
      "name": "Geometry Dash Galactic Background",
      "image": "<?php echo htmlspecialchars($baseUrl); ?>",
      "description": "<?php echo htmlspecialchars($pageDescription); ?>",
      "artMedium": "Digital",
      "artworkSurface": "Digital Canvas",
      "width": {
        "@type": "QuantitativeValue",
        "value": "1500",
        "unitCode": "E37"
      },
      "height": {
        "@type": "QuantitativeValue",
        "value": "500",
        "unitCode": "E37"
      },
      "author": {
        "@type": "Person",
        "name": "GameJolt Artist",
        "url": "<?php echo htmlspecialchars($artCreditUrl); ?>"
      }
    }
    </script>

    <!-- Inline CSS: Zero external requests, uses system fonts, flat colors, no gradients, no neon -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            background: #ffffff;
            color: #111111;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        main {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            text-align: center;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }
        .image-wrapper {
            width: 100%;
            max-width: 1100px;
            margin-bottom: 2rem;
        }
        /* Native image sizing prevents Cumulative Layout Shift (CLS) = 0 */
        img {
            width: 100%;
            height: auto;
            display: block;
            border: 1px solid #dddddd;
        }
        .credit-container {
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .credit-link {
            color: #111111;
            text-decoration: none;
            border-bottom: 1px solid #111111;
            padding-bottom: 1px;
            transition: border-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .credit-link:hover {
            color: #555555;
            border-color: #555555;
        }
        .status-card {
            width: 100%;
            max-width: 1100px;
            margin: 0 0 1.5rem;
            padding: 1.25rem;
            text-align: left;
            border: 1px solid #dddddd;
            background: #fafafa;
        }
        .status-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.75rem;
        }
        .status-dot {
            width: 0.65rem;
            height: 0.65rem;
            border-radius: 50%;
            background: #2e9d57;
            flex: 0 0 auto;
        }
        .status-label {
            margin-left: auto;
            font-size: 0.85rem;
            color: #666666;
        }
        .server-url {
            display: block;
            width: 100%;
            overflow: auto;
            padding: 0.75rem;
            border: 1px solid #e3e3e3;
            background: #ffffff;
            font-size: 0.9rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-top: 0.85rem;
        }
        .action {
            appearance: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.5rem;
            padding: 0.65rem 0.9rem;
            border: 1px solid #cccccc;
            background: #ffffff;
            color: #111111;
            text-decoration: none;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .action.primary {
            border-color: #d9232e;
            background: #d9232e;
            color: #ffffff;
        }
        .action:hover {
            filter: brightness(0.97);
        }
        .copy-note {
            display: block;
            min-height: 1.25em;
            margin-top: 0.5rem;
            color: #666666;
            font-size: 0.8rem;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            h1 {
                font-size: 1.25rem;
            }
            main {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <main>
        <h1>Geometry Dash Indonesia Private Server</h1>
        <div class="image-wrapper">
            <a href="<?php echo htmlspecialchars($GDID); ?>" target="_blank" rel="noopener noreferrer">
            <img 
                src="<?php echo htmlspecialchars($fullImageUrl); ?>" 
                alt="<?php echo htmlspecialchars($imageAlt); ?>" 
                width="1500" 
                height="500"
                fetchpriority="high"
                decoding="async"
            >
        </a></div>

        <section class="status-card" aria-label="GDIPS server status">
            <div class="status-row">
                <span class="status-dot" aria-hidden="true"></span>
                <strong>GDIPS server is online</strong>
                <span class="status-label">PHP is responding</span>
            </div>
            <code class="server-url" id="server-url"><?php echo htmlspecialchars($serverUrl); ?></code>
            <div class="actions">
                <a class="action primary" href="./dashboard/">Open Dashboard</a>
                <a class="action" href="./health.php" target="_blank" rel="noopener noreferrer">Health JSON</a>
                <button class="action" type="button" id="copy-server-url">Copy server URL</button>
            </div>
            <span class="copy-note" id="copy-note" aria-live="polite"></span>
        </section>

        <div class="credit-container">
            Art by: <a href="<?php echo htmlspecialchars($artCreditUrl); ?>" target="_blank" rel="noopener noreferrer" class="credit-link">GameJolt Artist</a>
        </div>
    </main>

    <script>
        const copyButton = document.getElementById('copy-server-url');
        const serverUrl = document.getElementById('server-url');
        const copyNote = document.getElementById('copy-note');

        copyButton?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(serverUrl.textContent.trim());
                copyNote.textContent = 'Server URL copied to clipboard.';
            } catch {
                copyNote.textContent = 'Copy failed. Select the URL above manually.';
            }
        });
    </script>
</body>
</html>