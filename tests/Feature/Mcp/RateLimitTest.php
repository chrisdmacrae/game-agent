<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();

    // Shrink the limit so the test doesn't need 120 requests.
    RateLimiter::for('mcp', fn ($request) => Limit::perMinute(2)->by($request->ip()));
});

function mcpToolCall(array $server = []): TestResponse
{
    return test()->call('POST', '/mcp/poe2', server: $server, content: json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'get_meta_context', 'arguments' => []],
    ]), parameters: []);
}

test('the mcp endpoint throttles by ip', function () {
    mcpToolCall()->assertOk();
    mcpToolCall()->assertOk();
    mcpToolCall()->assertStatus(429);
});

test('spoofed forwarded headers do not bypass the throttle when proxies are untrusted', function () {
    // Same peer IP with different X-Forwarded-For values must share one bucket.
    mcpToolCall(['HTTP_X_FORWARDED_FOR' => '203.0.113.1'])->assertOk();
    mcpToolCall(['HTTP_X_FORWARDED_FOR' => '203.0.113.2'])->assertOk();
    mcpToolCall(['HTTP_X_FORWARDED_FOR' => '203.0.113.3'])->assertStatus(429);
});
