<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Services\Carriers\FedEx\Validation\FedExGlobalShipCaseCatalog;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use Illuminate\Support\Facades\Http;

class Phase6FedExCanadaShipValidationTest extends Phase6FedExShipValidationTest
{
    public function test_validation_workspace_shows_canada_global_section(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Canada UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Global Territories — Canada')
            ->assertSeeText('IntegratorCA01')
            ->assertSeeText('IntegratorCA05')
            ->assertDontSeeText('IntegratorCA06');
    }

    public function test_run_global_canada_ship_case_records_regional_event(): void
    {
        config([
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => true,
        ]);

        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Canada Ship Store');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/ship/v1/shipments') && ! str_contains($url, 'validate')) {
                return Http::response([
                    'transactionId' => 'fedex-ca-ship-1',
                    'output' => [
                        'transactionShipments' => [[
                            'masterTrackingNumber' => '794610001001',
                            'serviceType' => 'FEDEX_EXPRESS_SAVER',
                            'pieceResponses' => [[
                                'trackingNumber' => '794610001001',
                                'packageSequenceNumber' => 1,
                                'packageDocuments' => [[
                                    'encodedLabel' => base64_encode('%PDF-1.4 canada'),
                                    'docType' => 'PDF',
                                    'imageType' => 'PDF',
                                ]],
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL', 'code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.global-ship', [
                'carrierAccount' => $account,
                'region' => 'CA',
                'caseKey' => 'IntegratorCA01',
            ]))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account));

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('test_case_key', 'IntegratorCA01')
            ->where('validation_region', FedExGlobalShipCaseCatalog::REGION_CA)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('ship_ca01_pdf', $event->scenario_key);
        $this->assertSame('614365501', data_get($event->request_body_encrypted, 'accountNumber.value'));
    }

    public function test_ca06_is_rejected_from_global_ship_route(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Canada Rate Reject Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.global-ship', [
                'carrierAccount' => $account,
                'region' => 'CA',
                'caseKey' => 'IntegratorCA06',
            ]))
            ->assertStatus(422);
    }

    public function test_us_locked_cases_remain_isolated_from_canada_region_filter(): void
    {
        $resolver = app(FedExShipFixtureResolver::class);

        $this->assertSame('US', $resolver->regionForCase('IntegratorUS02'));
        $this->assertSame(FedExGlobalShipCaseCatalog::REGION_CA, $resolver->regionForCase('IntegratorCA01'));
    }
}
