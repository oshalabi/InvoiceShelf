<?php

/**
 * Print the CHANGELOG.md section for a released version.
 *
 * Usage: php .github/scripts/changelog-section.php <version> [changelog-path]
 *
 * Exits 1 when the version has no section, so a release whose notes were
 * forgotten stops the pipeline rather than registering an empty changelog on
 * the updater.
 *
 * Section boundaries are found by matching *version* headings, not any "## ",
 * because release notes routinely contain their own second-level headings. A
 * section therefore runs until the next heading that looks like a version.
 */
$version = $argv[1] ?? '';
$path = $argv[2] ?? dirname(__DIR__, 2).'/CHANGELOG.md';

if ($version === '') {
    fwrite(STDERR, "usage: changelog-section.php <version> [changelog-path]\n");
    exit(2);
}

if (! is_readable($path)) {
    fwrite(STDERR, "changelog not readable: {$path}\n");
    exit(2);
}

$lines = preg_split('/\R/', (string) file_get_contents($path));

// A leading "v" is tolerated on either side so v2.4.2 and 2.4.2 both resolve.
$isVersionHeading = static fn (string $line): bool => (bool) preg_match('/^##\s+v?\d+\.\d+\.\d+/i', $line);
$wanted = ltrim($version, 'vV');

$section = [];
$capturing = false;

foreach ($lines as $line) {
    if ($isVersionHeading($line)) {
        if ($capturing) {
            break;
        }

        // "## 2.4.2 — 2026-07-29" and "## 2.4.2" both match; a date or any other
        // trailing text is ignored, but 2.4.2 must not match 2.4.20.
        $capturing = (bool) preg_match(
            '/^##\s+v?'.preg_quote($wanted, '/').'(?![\w.-])/i',
            $line
        );

        continue;
    }

    if ($capturing) {
        $section[] = $line;
    }
}

$body = trim(implode("\n", $section));

if (! $capturing && $body === '') {
    fwrite(STDERR, "no CHANGELOG.md section found for {$version}\n");
    exit(1);
}

if ($body === '') {
    fwrite(STDERR, "CHANGELOG.md section for {$version} is empty\n");
    exit(1);
}

echo $body, "\n";
