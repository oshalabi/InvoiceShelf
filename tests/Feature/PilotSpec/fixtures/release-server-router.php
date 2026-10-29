<?php

// Stub release server for the pilot behavioural suite. Serves a canned
// update-check response and a generated release archive.
$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#^/releases/update-check/#', $uri)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'release' => [
            'version' => '9.9.9-test',
            'min_php_version' => '8.0',
            'extensions' => ['curl', 'pilot_missing_ext'],
        ],
    ]);

    return true;
}
if (preg_match('#^/releases/download/9\.9\.9-test\.zip#', $uri)) {
    $tmp = tempnam(sys_get_temp_dir(), 'relzip');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('InvoiceShelf/pilot-release-marker.txt', 'ok');
    $zip->close();
    header('Content-Type: application/zip');
    readfile($tmp);
    unlink($tmp);

    return true;
}
http_response_code(404);
