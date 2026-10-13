<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_serves_the_single_page_application(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewIs('app');
    }
}
