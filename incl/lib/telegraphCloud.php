<?php
/**
 * Telegraph Cloud level object storage.
 *
 * New uploads can keep their large Geometry Dash payload outside MariaDB.
 * The object API is chunked so a single huge level never has to fit in one
 * Telegraph Cloud object.
 */
class gdTelegraphCloud {
    private static function config() {
        require __DIR__ . "/../../config/telegraph.php";
        if (empty($telegraphCloudEnabled)) return null;

        $base = rtrim((string)$telegraphCloudBaseUrl, "/");
        $project = (string)$telegraphCloudProjectId;
        $token = (string)$telegraphCloudApiKey;
        $bucket = (string)$telegraphCloudBucket;
        $chunk = (int)$telegraphCloudChunkBytes;

        if ($base === "" || $project === "" || $token === "" || $bucket === "") {
            throw new RuntimeException("Telegraph Cloud storage is enabled but not configured.");
        }
        $scheme = strtolower((string)parse_url($base, PHP_URL_SCHEME));
        if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array($scheme, ["http", "https"], true)) {
            throw new RuntimeException("Telegraph Cloud base URL is invalid.");
        }
        if ($chunk < 1 || $chunk > 10 * 1024 * 1024) $chunk = 8 * 1024 * 1024;

        return [$base, $project, $token, $bucket, $chunk];
    }

    public static function enabled() {
        require __DIR__ . "/../../config/telegraph.php";
        return !empty($telegraphCloudEnabled);
    }

    private static function objectUrl($base, $bucket, $key) {
        $parts = array_merge([$bucket], explode("/", trim($key, "/")));
        return $base . "/api/storage/" . implode("/", array_map("rawurlencode", $parts));
    }

    private static function request($method, $url, $token, $body = null, $contentType = null, $idempotencyKey = null) {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException("Telegraph Cloud request initialization failed.");

        $headers = [
            "Authorization: Bearer " . $token,
            "Accept: application/json",
        ];
        if ($contentType !== null) $headers[] = "Content-Type: " . $contentType;
        if ($idempotencyKey !== null) $headers[] = "Idempotency-Key: " . $idempotencyKey;

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            // Wasmer's outbound cURL can keep a HTTP/2 response open after
            // Cloudflare Pages has delivered the object headers. HTTP/1.1 keeps
            // the object transport deterministic for binary level payloads.
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING => "",
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            throw new RuntimeException("Telegraph Cloud network request failed.");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Telegraph Cloud returned HTTP " . $status . ".");
        }
        return $response;
    }

    private static function encode($value) {
        return rtrim(strtr(base64_encode($value), "+/", "-_"), "=");
    }

    private static function decode($value) {
        $value = strtr($value, "-_", "+/");
        $pad = strlen($value) % 4;
        if ($pad) $value .= str_repeat("=", 4 - $pad);
        $result = base64_decode($value, true);
        return $result === false ? null : $result;
    }

    public static function parseManifest($storedValue) {
        if (!is_string($storedValue) || strpos($storedValue, "TGCP1:") !== 0) return null;

        $decoded = self::decode(substr($storedValue, 6));
        if ($decoded === null) return null;

        $manifest = json_decode($decoded, true);
        if (
            !is_array($manifest) ||
            ($manifest["v"] ?? null) !== 1 ||
            ($manifest["provider"] ?? null) !== "telegraph-cloud" ||
            !is_string($manifest["project"] ?? null) ||
            !is_string($manifest["bucket"] ?? null) ||
            !is_int($manifest["size"] ?? null) ||
            !is_string($manifest["sha256"] ?? null) ||
            empty($manifest["chunks"]) ||
            !is_array($manifest["chunks"])
        ) return null;

        return $manifest;
    }

    public static function storeLevel($levelString) {
        [$base, $project, $token, $bucket, $chunkSize] = self::config();

        $length = strlen($levelString);
        if ($length < 1) throw new RuntimeException("Cannot store an empty level.");

        $sha256 = hash("sha256", $levelString);
        $prefix = "levels/" . bin2hex(random_bytes(12));
        $chunks = [];

        for ($offset = 0, $part = 0; $offset < $length; $part++) {
            $chunk = substr($levelString, $offset, $chunkSize);
            if ($chunk === "") break;

            $key = $prefix . "/part-" . str_pad((string)$part, 6, "0", STR_PAD_LEFT);
            $idempotencyKey = "gdips-level-" . substr($sha256, 0, 16) . "-" . $part;

            self::request(
                "PUT",
                self::objectUrl($base, $bucket, $key),
                $token,
                $chunk,
                "application/octet-stream",
                $idempotencyKey
            );

            $chunks[] = [
                "key" => $key,
                "size" => strlen($chunk),
            ];
            $offset += strlen($chunk);
        }

        $manifest = [
            "v" => 1,
            "provider" => "telegraph-cloud",
            "project" => $project,
            "bucket" => $bucket,
            "size" => $length,
            "sha256" => $sha256,
            "chunks" => $chunks,
        ];

        return "TGCP1:" . self::encode(json_encode($manifest, JSON_UNESCAPED_SLASHES));
    }

    private static function normalizeLevelPayload($payload) {
        // Do not normalize the wire payload here.
        //
        // Geometry Dash's download endpoint expects the exact levelString
        // representation produced by the game. For 2.2 this is commonly a
        // URL-safe base64 encoded gzip payload (for example, H4sIA...).
        // Older GDIPS objects may contain the same representation around a
        // kS payload. Decoding it to plain kS text changes the wire format and
        // causes the stock client to reject the download.
        return $payload;
    }

    public static function readLevel($storedValue) {
        $manifest = self::parseManifest($storedValue);
        if ($manifest === null) return $storedValue;

        [$base, $project, $token, $bucket] = self::config();
        if ($manifest["project"] !== $project || $manifest["bucket"] !== $bucket) {
            throw new RuntimeException("Telegraph Cloud level storage project mismatch.");
        }

        $result = "";
        $size = 0;

        foreach ($manifest["chunks"] as $chunk) {
            if (
                !is_array($chunk) ||
                !is_string($chunk["key"] ?? null) ||
                !preg_match('#^levels/[A-Za-z0-9_-]+/part-[0-9]{6}$#', $chunk["key"]) ||
                !is_int($chunk["size"] ?? null) ||
                $chunk["size"] < 1
            ) {
                throw new RuntimeException("Telegraph Cloud level manifest is invalid.");
            }

            $body = self::request(
                "GET",
                self::objectUrl($base, $bucket, $chunk["key"]),
                $token
            );
            $result .= $body;
            $size += strlen($body);

            if (strlen($body) !== $chunk["size"]) {
                throw new RuntimeException("Telegraph Cloud level chunk size mismatch.");
            }
        }

        if (
            $size !== $manifest["size"] ||
            !hash_equals($manifest["sha256"], hash("sha256", $result))
        ) {
            throw new RuntimeException("Telegraph Cloud level integrity check failed.");
        }

        return self::normalizeLevelPayload($result);
    }

    public static function isManifest($storedValue) {
        return self::parseManifest($storedValue) !== null;
    }

    public static function deleteLevel($storedValue) {
        $manifest = self::parseManifest($storedValue);
        if ($manifest === null) return;

        try {
            [$base, $project, $token, $bucket] = self::config();
            if ($manifest["project"] !== $project || $manifest["bucket"] !== $bucket) return;

            foreach ($manifest["chunks"] as $chunk) {
                if (!is_array($chunk) || empty($chunk["key"])) continue;
                try {
                    self::request("DELETE", self::objectUrl($base, $bucket, $chunk["key"]), $token);
                } catch (Throwable $e) {
                    error_log("GDIPS Telegraph Cloud object cleanup failed.");
                }
            }
        } catch (Throwable $e) {
            error_log("GDIPS Telegraph Cloud cleanup configuration failed.");
        }
    }
}
?>