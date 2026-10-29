<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use JMac\Testing\Traits\AdditionalAssertions;

abstract class TestCase extends BaseTestCase
{
    use AdditionalAssertions;

    protected function setUp(): void
    {
        parent::setUp();

        // CI skips the frontend build, so a few routes that render the SPA shell
        // (resources/views/app.blade.php → @vite) would throw ViteManifestNotFoundException.
        // Stub Vite so those views render without a built manifest.
        $this->withoutVite();
    }
}
