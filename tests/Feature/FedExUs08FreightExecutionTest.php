<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Operations\FedExFreightLtlPayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExFreightLtlResponseParser;
use App\Services\Carriers\FedEx\Operations\FedExShipResponseParser;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FedExShipTestEvidenceFactory;

class FedExUs08FreightExecutionTest extends Phase6FedExShipValidationTest
{
    private const FREIGHT_ACCOUNT = '631234540';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => true,
            'carriers.fedex.validation_us08_enabled' => true,
            'carriers.fedex.freight_ltl_api_enabled' => true,
            'carriers.fedex.validation_us08_freight_account' => self::FREIGHT_ACCOUNT,
            'carriers.fedex.freight_ltl_ship_path' => '/ship/v1/freight/shipments',
        ]);
    }

    public function test_workspace_uses_freight_route_not_parcel_route_for_us08(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Run IntegratorUS08 Freight LTL')
            ->assertSeeText('I understand this creates one sandbox Freight shipment when local preflight passes.')
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), false)
            ->assertDontSee(
                route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => 'IntegratorUS08']),
                false,
            )
            ->assertSee(
                route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => 'IntegratorUS02']),
                false,
            );
    }

    public function test_workspace_shows_excluded_notice_when_freight_ltl_api_off(): void
    {
        config(['carriers.fedex.freight_ltl_api_enabled' => false]);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Disabled Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('IntegratorUS08 Freight LTL is excluded because Freight LTL is not a supported capability of this application')
            ->assertSeeText('IntegratorUS07')
            ->assertDontSee('Run IntegratorUS08 Freight LTL', false);
    }

    public function test_freight_route_blocked_when_freight_ltl_api_disabled(): void
    {
        config(['carriers.fedex.freight_ltl_api_enabled' => false]);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Disabled Run Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_freight_us08_confirmation_checkbox_is_required(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Confirm Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHasErrors('confirm_freight_creation');

        Http::assertNothingSent();
    }

    public function test_freight_us08_controller_persists_label_and_bol_without_real_network(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Create Store');
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();

        $zpl = FedExShipTestEvidenceFactory::validZplBinary();
        $bolPdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $ciPdf = "%PDF-1.4\nci\n%%EOF";
        $capturedUrl = null;
        $capturedPayload = null;
        $freightCalls = 0;

        Http::fake(function ($request) use (&$capturedUrl, &$capturedPayload, &$freightCalls, $zpl, $bolPdf, $ciPdf) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/ship/v1/freight/shipments')) {
                $freightCalls++;
                $capturedUrl = $url;
                $capturedPayload = $request->data();

                return Http::response($this->freightSuccessResponse($zpl, $bolPdf, $ciPdf), 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND', 'message' => 'Unexpected']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        $this->assertSame(1, $freightCalls);
        $this->assertNotNull($capturedUrl);
        $this->assertStringContainsString('/ship/v1/freight/shipments', (string) $capturedUrl);
        $this->assertStringNotContainsString('/ship/v1/shipments/packages/validate', (string) $capturedUrl);
        $this->assertSame('QCONFIG', data_get($capturedPayload, 'freightRequestedShipment.shipper.contact.personName'));
        $this->assertSame('F-413404', data_get($capturedPayload, 'freightRequestedShipment.recipient.contact.personName'));
        $this->assertSame('10', data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.id'));
        $this->assertSame(1, data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.handlingUnits'));
        $this->assertSame(10, data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.pieces'));
        $this->assertSame('54321', data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.purchaseOrderNumber'));
        $this->assertArrayNotHasKey('nmfcCode', (array) data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0', []));
        $this->assertSame(['LIST', 'PREFERRED'], data_get($capturedPayload, 'freightRequestedShipment.rateRequestType'));
        $this->assertTrue((bool) data_get($capturedPayload, 'freightRequestedShipment.shippingDocumentSpecification.commercialInvoiceDetail.provideInstructions'));
        $this->assertSame('RETURNED', data_get($capturedPayload, 'freightRequestedShipment.shippingDocumentSpecification.freightBillOfLadingDetail.format.dispositions.0.dispositionType'));
        $this->assertSame(self::FREIGHT_ACCOUNT, data_get($capturedPayload, 'accountNumber.value'));
        $this->assertSame(self::FREIGHT_ACCOUNT, data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.fedExFreightAccountNumber.value'));
        $this->assertSame('10', data_get($capturedPayload, 'freightRequestedShipment.requestedPackageLineItems.0.associatedFreightLineItems.0.id'));
        $this->assertSame(0.0, (float) data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail.clientDiscountPercent'));
        $this->assertArrayNotHasKey('carrierCode', (array) data_get($capturedPayload, 'freightRequestedShipment.freightShipmentDetail', []));

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('test_case_key', 'IntegratorUS08')
            ->where('scenario_key', 'ship_us08_zplii')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertSame('FEDEX_FREIGHT_PRIORITY', data_get($event->response_summary, 'response_service_type'));
        $this->assertTrue((bool) data_get($event->response_summary, 'bol_present'));
        $this->assertSame(1, (int) data_get($event->response_summary, 'generated_label_count'));

        $label = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_GENERATED_LABEL)
            ->where('label_format', 'ZPLII')
            ->where('package_sequence', 1)
            ->first();
        $this->assertNotNull($label);
        $this->assertSame('IntegratorUS08', $label->test_case_key);
        $this->assertSame('ship_us08_zplii', $label->scenario_key);
        $this->assertFileExists((string) $label->absolutePath());
        $this->assertSame($zpl, file_get_contents((string) $label->absolutePath()));

        $bol = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_type', 'freight_bill_of_lading')
            ->first();
        $this->assertNotNull($bol);
        $this->assertSame(FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT, $bol->artifact_role);
        $this->assertFileExists((string) $bol->absolutePath());
        $this->assertStringStartsWith('%PDF', (string) file_get_contents((string) $bol->absolutePath()));

        $ci = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_type', 'freight_commercial_invoice')
            ->first();
        $this->assertNotNull($ci);

        $canonical = app(FedExValidationEvidenceQueryService::class)
            ->canonicalShipRun($store, $account, 'IntegratorUS08');
        $this->assertNotNull($canonical);
        $this->assertSame($event->id, $canonical['event']->id);
        $this->assertCount(1, $canonical['generated_labels']);
        $this->assertCount(0, $canonical['printed_scans']);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);
        $scanCheck = collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_scan_1');
        $this->assertSame('incomplete', $scanCheck['status'] ?? null);
    }

    public function test_parcel_ship_route_rejects_us08(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Parcel Reject Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [
                'carrierAccount' => $account,
                'testCaseKey' => 'IntegratorUS08',
            ]))
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_empty_label_fails_response_validation_and_missing_bol_blocks_canonical(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Corrupt Store');
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                return Http::response([
                    'transactionId' => 'freight-bad',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'FEDEX_FREIGHT_PRIORITY',
                            'masterTrackingNumber' => '794699999999',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794699999999',
                                'packageDocuments' => [[
                                    'contentType' => 'LABEL',
                                    'docType' => 'ZPLII',
                                    'imageType' => 'ZPLII',
                                    'encodedLabel' => base64_encode(''),
                                ]],
                            ]],
                            'shipmentDocuments' => [],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionMissing('success')
            ->assertSessionHas('warning');

        $event = CarrierApiEvent::query()
            ->where('test_case_key', 'IntegratorUS08')
            ->where('carrier_account_id', $account->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertFalse((bool) data_get($event->response_summary, 'response_validation_passed'));
        $this->assertContains('us08_label_empty_or_corrupt', data_get($event->response_summary, 'response_validation_reasons', []));
        $this->assertContains('us08_bol_missing', data_get($event->response_summary, 'response_validation_reasons', []));
        $this->assertSame(0, FedExValidationArtifact::query()->where('carrier_api_event_id', $event->id)->count());

        $this->assertNull(
            app(FedExValidationEvidenceQueryService::class)->canonicalShipRun($store, $account, 'IntegratorUS08')
        );

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);
        $eventCheck = collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_event');
        $bolCheck = collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_bol');
        $this->assertSame('incomplete', $eventCheck['status'] ?? null);
        $this->assertSame('incomplete', $bolCheck['status'] ?? null);
    }

    public function test_us08_response_uses_freight_parser_not_parcel_parser(): void
    {
        $zpl = FedExShipTestEvidenceFactory::validZplBinary();
        $bol = FedExShipTestEvidenceFactory::validPdfBinary();
        $body = $this->freightSuccessResponse($zpl, $bol);

        $freight = app(FedExFreightLtlResponseParser::class)->parse($body);
        $parcel = app(FedExShipResponseParser::class)->parse($body);

        $this->assertSame('FEDEX_FREIGHT_PRIORITY', $freight['service_type']);
        $this->assertTrue($freight['bol_present']);
        $this->assertArrayHasKey(1, $freight['labels']);
        $this->assertSame('ZPLII', $freight['labels'][1]['image_type']);
        $this->assertSame(
            'output.transactionShipments.0.shipmentDocuments.0',
            collect($freight['documents'])->firstWhere('is_bol', true)['response_path'] ?? null
        );

        $this->assertArrayNotHasKey('bol_present', $parcel);
        $this->assertArrayHasKey('documents', $parcel);
        $this->assertNotEmpty($parcel['documents']);
        // Parcel parser may surface shipmentDocuments for shared response shapes; Freight evidence still uses the freight parser.

        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Parser Rules Store');
        $event = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => \App\Models\CarrierAccount::PROVIDER_FEDEX,
            'environment' => 'sandbox',
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'http_method' => 'POST',
            'endpoint' => '/ship/v1/freight/shipments',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'http_status' => 200,
            'scenario_key' => 'ship_us08_zplii',
            'test_case_key' => 'IntegratorUS08',
            'label_format' => 'ZPLII',
            'package_count' => 1,
            'validation_region' => 'US',
            'request_body_encrypted' => ['freightRequestedShipment' => ['serviceType' => 'FEDEX_FREIGHT_PRIORITY']],
            'response_body_encrypted' => $body,
            'request_summary' => ['endpoint' => '/ship/v1/freight/shipments'],
            'response_summary' => [],
        ]);

        $validation = app(FedExShipEvidenceRules::class)->validateResponse($event, 'IntegratorUS08');
        $this->assertTrue($validation['valid'], implode(',', $validation['reasons']));
        $this->assertTrue((bool) data_get($validation, 'parsed.bol_present'));
    }

    public function test_freight_403_preserves_exact_fedex_error_without_generic_ship_entitlement_claim(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Exact 403 Store');
        $account->forceFill(['provider_account_number' => '700257037'])->save();
        $freightCalls = 0;

        Http::fake(function ($request) use (&$freightCalls) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                $freightCalls++;

                return Http::response([
                    'transactionId' => 'tx-us08-403',
                    'errors' => [[
                        'code' => 'FORBIDDEN.ERROR',
                        'message' => 'We could not authorize your credentials. Please check your permissions and try again.',
                    ]],
                ], 403);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('error');

        $flash = (string) session('error');
        $this->assertStringContainsString('FORBIDDEN.ERROR', $flash);
        $this->assertStringContainsString('We could not authorize your credentials', $flash);
        $this->assertStringContainsString('HTTP 403', $flash);
        $this->assertStringNotContainsString('not entitled for Ship API', $flash);
        $this->assertSame(1, $freightCalls);

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('test_case_key', 'IntegratorUS08')
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(403, (int) $event->http_status);
        $this->assertSame('FORBIDDEN.ERROR', $event->error_code);
        $this->assertNull(data_get($event->response_summary, 'ship_retry_max_attempts'));
        $this->assertStringNotContainsString('not entitled for Ship API', (string) $event->error_message);
    }

    public function test_missing_freight_account_blocks_before_network(): void
    {
        config(['carriers.fedex.validation_us08_freight_account' => '']);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Missing Freight Account');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_billing_address_mismatch_blocks_before_network(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Address Mismatch Store');
        Http::fake();

        $this->app->bind(FedExFreightLtlPayloadFactory::class, function () {
            return new class extends FedExFreightLtlPayloadFactory
            {
                public function buildShipmentPayload(
                    $account,
                    array $fixture,
                    ?string $labelFormat = null,
                    array $overrides = [],
                ): array {
                    $payload = (new FedExFreightLtlPayloadFactory)->buildShipmentPayload(
                        $account,
                        $fixture,
                        $labelFormat,
                        $overrides,
                    );
                    data_set(
                        $payload,
                        'freightRequestedShipment.freightShipmentDetail.fedExFreightBillingContactAndAddress.address.city',
                        'Memphis',
                    );

                    return $payload;
                }
            };
        });

        try {
            app(\App\Services\Carriers\FedEx\Operations\FedExFreightLtlService::class)->createShipment(
                store: $store,
                account: $account,
                testCaseKey: 'IntegratorUS08',
                actor: $owner,
            );
            $this->fail('Expected local billing address preflight to block before network I/O.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('billing/mailing address', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_http_200_with_missing_bol_does_not_show_normal_success(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Missing BOL Flash Store');
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();
        $zpl = FedExShipTestEvidenceFactory::validZplBinary();

        Http::fake(function ($request) use ($zpl) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                return Http::response($this->freightSuccessResponse($zpl, FedExShipTestEvidenceFactory::validPdfBinary(), null, includeBol: false), 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionMissing('success')
            ->assertSessionHas('warning')
            ->assertSessionHas('warning', fn (string $message): bool => str_contains($message, 'required validation evidence was incomplete')
                && str_contains($message, 'Do not retry blindly')
                && str_contains(strtolower($message), 'evidence event #'));

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('test_case_key', 'IntegratorUS08')
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertSame(200, (int) $event->http_status);
    }

    public function test_http_200_with_missing_label_does_not_show_normal_success(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Missing Label Flash Store');
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                return Http::response([
                    'transactionId' => 'freight-no-label',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'FEDEX_FREIGHT_PRIORITY',
                            'masterTrackingNumber' => '794612345678',
                            'pieceResponses' => [],
                            'shipmentDocuments' => [[
                                'contentType' => FedExFreightLtlResponseParser::CONTENT_TYPE_BOL,
                                'docType' => 'PDF',
                                'imageType' => 'PDF',
                                'encodedLabel' => base64_encode(FedExShipTestEvidenceFactory::validPdfBinary()),
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionMissing('success')
            ->assertSessionHas('warning');
    }

    public function test_valid_label_and_bol_shows_success(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Success Flash Store');
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                return Http::response($this->freightSuccessResponse(
                    FedExShipTestEvidenceFactory::validZplBinary(),
                    FedExShipTestEvidenceFactory::validPdfBinary(),
                ), 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');
    }

    public function test_us08_result_summary_uses_freight_service_and_endpoint(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US08 Summary Store');
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                return Http::response($this->freightSuccessResponse(
                    FedExShipTestEvidenceFactory::validZplBinary(),
                    FedExShipTestEvidenceFactory::validPdfBinary(),
                    "%PDF-1.4\nci\n%%EOF",
                ), 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08', $account), [
                'confirm_freight_creation' => '1',
            ])
            ->assertSessionHas('success');

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('test_case_key', 'IntegratorUS08')
            ->latest('id')
            ->firstOrFail();

        $summary = app(\App\Services\Carriers\FedEx\Validation\FedExShipEvidenceService::class)
            ->exportResultSummary($event, 'IntegratorUS08');

        $this->assertSame('FEDEX_FREIGHT_PRIORITY', $summary['request_service_type']);
        $this->assertSame('FEDEX_FREIGHT_PRIORITY', $summary['response_service_type']);
        $this->assertSame('/ship/v1/freight/shipments', $summary['endpoint']);
        $this->assertTrue($summary['freight_bol_present']);
        $this->assertTrue($summary['freight_commercial_invoice_present']);
        $this->assertGreaterThanOrEqual(1, $summary['generated_document_count']);

        $missing = app(\App\Services\Carriers\FedEx\Validation\FedExShipEvidenceService::class)
            ->exportResultSummary(null, 'IntegratorUS08');
        $this->assertSame('/ship/v1/freight/shipments', $missing['endpoint']);

        foreach (['IntegratorUS01', 'IntegratorUS02', 'IntegratorUS07'] as $case) {
            $parcelMissing = app(\App\Services\Carriers\FedEx\Validation\FedExShipEvidenceService::class)
                ->exportResultSummary(null, $case);
            $this->assertSame('/ship/v1/shipments', $parcelMissing['endpoint'], $case);
            $this->assertArrayNotHasKey('freight_bol_present', $parcelMissing);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function freightSuccessResponse(
        string $zpl,
        string $bolPdf,
        ?string $ciPdf = null,
        bool $includeBol = true,
    ): array {
        $shipmentDocuments = [];

        if ($includeBol) {
            $shipmentDocuments[] = [
                'contentType' => FedExFreightLtlResponseParser::CONTENT_TYPE_BOL,
                'docType' => 'PDF',
                'imageType' => 'PDF',
                'encodedLabel' => base64_encode($bolPdf),
            ];
        }

        if ($ciPdf !== null) {
            $shipmentDocuments[] = [
                'contentType' => FedExFreightLtlResponseParser::CONTENT_TYPE_COMMERCIAL_INVOICE,
                'docType' => 'PDF',
                'imageType' => 'PDF',
                'encodedLabel' => base64_encode($ciPdf),
            ];
        }

        return [
            'transactionId' => 'fedex-freight-us08',
            'output' => [
                'transactionShipments' => [[
                    'serviceType' => 'FEDEX_FREIGHT_PRIORITY',
                    'masterTrackingNumber' => '794612345678',
                    'pieceResponses' => [[
                        'packageSequenceNumber' => 1,
                        'trackingNumber' => '794612345678',
                        'packageDocuments' => [[
                            'contentType' => 'LABEL',
                            'docType' => 'ZPLII',
                            'imageType' => 'ZPLII',
                            'encodedLabel' => base64_encode($zpl),
                        ]],
                    ]],
                    'shipmentDocuments' => $shipmentDocuments,
                ]],
            ],
        ];
    }
}
