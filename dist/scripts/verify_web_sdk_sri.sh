#!/bin/bash
#
# Verifies that the Subresource Integrity hashes in src/classes/Utility/WebSdk.php match the
# Adyen Web SDK files served by the Adyen CDN, and that README.md states the same SDK version.
#
# Run from the repository root.
# Exit codes: 0 ok, 1 hash or README mismatch, 2 constants unreadable or download failed.

set -euo pipefail

src="./src/classes/Utility/WebSdk.php"

read_const() {
    sed -nE "s/.*const $1 = '([^']+)';.*/\1/p" "$src"
}

version=$(read_const VERSION)
base=$(read_const BASE_URL)
js_expected=$(read_const JS_INTEGRITY)
css_expected=$(read_const CSS_INTEGRITY)

if [ -z "$version" ] || [ -z "$base" ] || [ -z "$js_expected" ] || [ -z "$css_expected" ]; then
    echo "ERROR: could not read Web SDK constants from $src" >&2
    exit 2
fi

check() {
    local url="${base}${version}/$1"
    local expected="$2"
    local content actual

    if ! content=$(curl -fsSL --retry 3 "$url" | openssl dgst -sha384 -binary | openssl base64 -A); then
        echo "ERROR: download failed: $url" >&2
        exit 2
    fi
    actual="sha384-${content}"

    if [ "$actual" != "$expected" ]; then
        echo "ERROR: SRI mismatch for $url" >&2
        echo "  expected: $expected" >&2
        echo "  actual:   $actual" >&2
        exit 1
    fi

    echo "OK $url"
}

check adyen.js "$js_expected"
check adyen.css "$css_expected"

if ! grep -q "Checkout Web Component version:\*\* ${version}\s*$" README.md; then
    echo "ERROR: README.md does not state Checkout Web Component version ${version}" >&2
    exit 1
fi

echo "OK README.md states Web SDK ${version}"
