<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Cache::forget('fedex.integrator.test_case_fixtures');

$fixture = app(App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService::class)->swedenMfaPassthroughAccount();
echo "FIXTURE:\n".json_encode($fixture, JSON_PRETTY_PRINT)."\n\n";

$events = App\Models\CarrierApiEvent::query()
    ->where('scenario_key', 'like', '%sweden%')
    ->orderByDesc('id')
    ->limit(5)
    ->get();

foreach ($events as $e) {
    echo "=== EVENT {$e->id} at {$e->created_at} http={$e->http_status} error=".($e->error_code ?? '-')." ===\n";
    echo "REQUEST:\n".json_encode($e->request_body_encrypted, JSON_PRETTY_PRINT)."\n";
    echo "RESPONSE:\n".json_encode($e->response_body_encrypted, JSON_PRETTY_PRINT)."\n\n";
}

$parent = App\Models\CarrierApiEvent::query()
    ->where('request_summary->validation_case', 'sweden_mfa_passthrough')
    ->orderByDesc('id')
    ->limit(3)
    ->get(['id','action','http_status','scenario_key','created_at']);
echo "Recent sweden run events:\n";
foreach ($parent as $p) {
    echo "  #{$p->id} {$p->action} {$p->scenario_key} http={$p->http_status} at {$p->created_at}\n";
}
