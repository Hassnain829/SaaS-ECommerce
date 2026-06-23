<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Presenters\FedExCarrierTestResponsePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FedExCarrierTestResponsePresenterTest extends TestCase
{
    use RefreshDatabase;

    private FedExCarrierTestResponsePresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = app(FedExCarrierTestResponsePresenter::class);
    }

    public function test_resolve_result_kind_maps_authorization_blocked_and_service_availability(): void
    {
        $blocked = CarrierApiResult::failure('blocked', code: 'fedex_authorization_blocked');
        $this->assertSame('fedex_authorization_blocked', $this->presenter->resolveResultKind($blocked, 'rate_quote'));

        $apiFailure = CarrierApiResult::failure('FedEx rejected request');
        $this->assertSame('fedex_api', $this->presenter->resolveResultKind($apiFailure, 'service_availability'));
        $this->assertSame('failure', $this->presenter->resolveResultKind($apiFailure, 'rate_quote'));
    }

    public function test_address_validation_result_kind_distinguishes_warning_from_success(): void
    {
        $ok = CarrierApiResult::success();
        $this->assertSame('warning', $this->presenter->addressValidationResultKind($ok, ['resolved_addresses' => []]));
        $this->assertSame('success', $this->presenter->addressValidationResultKind($ok, [
            'resolved_addresses' => [['city' => 'MEMPHIS']],
        ]));

        $failed = CarrierApiResult::failure('bad address');
        $this->assertSame('failure', $this->presenter->addressValidationResultKind($failed, []));
    }

    public function test_redirect_with_fedex_test_result_preserves_flash_contract_for_success(): void
    {
        $this->startSession();

        $account = new CarrierAccount;
        $account->id = 42;
        $result = CarrierApiResult::success(
            requestSummary: ['endpoint' => '/address/v1/validate'],
            responseSummary: ['fedex_transaction_id' => 'txn-123'],
            durationMs: 88,
        );

        $response = $this->presenter->redirectWithFedExTestResult(
            account: $account,
            tool: 'address_validation',
            label: 'Address check',
            result: $result,
            presentation: ['resolved_addresses' => [['city' => 'AUSTIN']]],
            inputSummary: ['requested_country' => 'US'],
            resultKind: 'success',
        );

        $this->assertSame(route('shippingAutomation', ['tab' => 'carriers']), $response->getTargetUrl());
        $session = $response->getSession();
        $this->assertSame('FedEx validation tools', $session->get('success_title'));
        $this->assertStringContainsString('validation suggestion', (string) $session->get('success'));

        $payload = $session->get('fedex_test_result');
        $this->assertStringContainsString('country-matching', (string) $payload['message']);
        $this->assertSame(42, $payload['account_id']);
        $this->assertSame('success', $payload['result_kind']);
        $this->assertTrue($payload['success']);
        $this->assertSame('txn-123', $payload['fedex_transaction_id']);
        $this->assertSame(88, $payload['duration_ms']);
    }

    public function test_destination_input_summary_filters_empty_values(): void
    {
        $summary = $this->presenter->destinationInputSummary('Warehouse A', [
            'country_code' => 'US',
            'postal_code' => '38118',
            'state' => null,
            'city' => '',
        ]);

        $this->assertSame([
            'origin' => 'Warehouse A',
            'destination_country' => 'US',
            'destination_postal' => '38118',
        ], $summary);
    }
}
