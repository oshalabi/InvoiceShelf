<?php

/*
 * Creates the per-tool agent docs as symlinks to the canonical AGENTS.md.
 * These symlinks are gitignored; this runs from composer's post-autoload-dump
 * (and can be run manually via `composer run ai-docs`).
 */

chdir(__DIR__.'/..');

$links = [
    'CLAUDE.md' => 'AGENTS.md',
    'GEMINI.md' => 'AGENTS.md',
    '.github/copilot-instructions.md' => '../AGENTS.md',
];

@mkdir('.github');

foreach ($links as $link => $target) {
    if (is_link($link)) {
        continue;
    }
    if (file_exists($link)) {
        @unlink($link); // replace a stale regular file (e.g. the old committed CLAUDE.md)
    }
    if (@symlink($target, $link)) {
        echo "ai-docs: linked {$link} -> {$target}\n";
    } else {
        fwrite(STDERR, "ai-docs: could not symlink {$link} (symlinks may require privileges on Windows)\n");
    }
}
