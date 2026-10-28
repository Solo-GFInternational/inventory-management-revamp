<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every inventory row created writes a barcode label to the local
        // disk; keep test runs out of the real storage/app/private.
        Storage::fake('local');
    }
}
