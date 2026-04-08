#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SAIL_BIN="${SAIL_BIN:-$ROOT_DIR/vendor/bin/sail}"

if [[ ! -x "$SAIL_BIN" ]]; then
  printf "[rustfs-bucket] Sail binary not found at %s\n" "$SAIL_BIN" >&2
  exit 1
fi

wait_for_artisan() {
  local attempts=0

  until "$SAIL_BIN" artisan about >/dev/null 2>&1; do
    attempts=$((attempts + 1))

    if (( attempts >= 30 )); then
      printf "[rustfs-bucket] Laravel container did not become ready in time.\n" >&2
      exit 1
    fi

    sleep 2
  done
}

wait_for_artisan

"$SAIL_BIN" artisan tinker --execute=' 
$config = config("filesystems.disks.s3");
$endpoint = (string) ($config["endpoint"] ?? "");
$bucket = trim((string) ($config["bucket"] ?? ""));

if ($bucket === "") {
    throw new RuntimeException("S3 bucket is not configured.");
}

if ($endpoint === "" || ! str_contains($endpoint, "rustfs")) {
    fwrite(STDOUT, "[rustfs-bucket] Skipping bucket bootstrap because the s3 disk is not using RustFS.\n");

    return;
}

$client = new Aws\S3\S3Client([
    "version" => "latest",
    "region" => (string) ($config["region"] ?? "us-east-1"),
    "credentials" => [
        "key" => (string) ($config["key"] ?? ""),
        "secret" => (string) ($config["secret"] ?? ""),
    ],
    "endpoint" => $endpoint,
    "use_path_style_endpoint" => (bool) ($config["use_path_style_endpoint"] ?? false),
]);

$exists = true;

try {
    $client->headBucket(["Bucket" => $bucket]);
} catch (Throwable) {
    $exists = false;
}

if (! $exists) {
    $client->createBucket(["Bucket" => $bucket]);
    $client->waitUntil("BucketExists", ["Bucket" => $bucket]);
    fwrite(STDOUT, "[rustfs-bucket] Created bucket [{$bucket}].\n");

    return;
}

fwrite(STDOUT, "[rustfs-bucket] Bucket [{$bucket}] already exists.\n");
'
