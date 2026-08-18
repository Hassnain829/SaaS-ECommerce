<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

if (! defined('ECO_PORTAL_CONNECTOR_TESTING')) {
    define('ECO_PORTAL_CONNECTOR_TESTING', true);
}

require_once dirname(__DIR__, 2).'/dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-checkout-attempt.php';

final class WordPressCheckoutAttemptTest extends TestCase
{
    public function test_two_concurrent_initial_submissions_reuse_the_rendered_attempt_key(): void
    {
        $keyFactoryCalls = 0;
        $renderedState = \Eco_Portal_Checkout_Attempt::ensure(
            [],
            static function () use (&$keyFactoryCalls): string {
                $keyFactoryCalls++;

                return 'wp_checkout_attempt_a';
            },
            static fn (): string => 'rendered-form-token-a',
            1_776_508_800
        );

        // Both requests were submitted from the same rendered form before
        // either WordPress response reached the browser.
        $first = \Eco_Portal_Checkout_Attempt::begin($renderedState, 'rendered-form-token-a', 'same-payload');
        $second = \Eco_Portal_Checkout_Attempt::begin($renderedState, 'rendered-form-token-a', 'same-payload');

        $createdByKey = [];
        $createCheckout = static function (array $submission) use (&$createdByKey): array {
            $key = \Eco_Portal_Checkout_Attempt::idempotency_key($submission);

            return $createdByKey[$key] ??= ['checkout_id' => count($createdByKey) + 9001];
        };

        $this->assertSame(1, $keyFactoryCalls);
        $this->assertSame('wp_checkout_attempt_a', \Eco_Portal_Checkout_Attempt::idempotency_key($first));
        $this->assertSame('wp_checkout_attempt_a', \Eco_Portal_Checkout_Attempt::idempotency_key($second));
        $this->assertSame($createCheckout($first), $createCheckout($second));
        $this->assertCount(1, $createdByKey);
    }

    public function test_lost_initial_wordpress_response_replays_the_same_saas_checkout(): void
    {
        $renderedState = \Eco_Portal_Checkout_Attempt::ensure(
            [],
            static fn (): string => 'wp_checkout_lost_response',
            static fn (): string => 'rendered-form-token-b',
            1_776_508_801
        );

        // WordPress persists this transition before calling the SaaS. The
        // first response is then deliberately discarded by this simulation.
        $persistedState = \Eco_Portal_Checkout_Attempt::begin(
            $renderedState,
            'rendered-form-token-b',
            'same-payload'
        );
        $createdByKey = [];
        $createCheckout = static function (array $submission) use (&$createdByKey): array {
            $key = \Eco_Portal_Checkout_Attempt::idempotency_key($submission);

            return $createdByKey[$key] ??= ['checkout_id' => 9100];
        };

        $discardedResponse = $createCheckout($persistedState);
        $retriedState = \Eco_Portal_Checkout_Attempt::begin(
            $persistedState,
            'rendered-form-token-b',
            'same-payload'
        );
        $retryResponse = $createCheckout($retriedState);

        $this->assertSame($discardedResponse, $retryResponse);
        $this->assertSame('wp_checkout_lost_response', \Eco_Portal_Checkout_Attempt::idempotency_key($retriedState));
        $this->assertCount(1, $createdByKey);
    }

    public function test_initial_post_without_the_preallocated_browser_attempt_fails_closed(): void
    {
        try {
            \Eco_Portal_Checkout_Attempt::begin([], 'unbound-form-token', 'payload');
            $this->fail('A POST without preallocated server state must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame(\Eco_Portal_Checkout_Attempt::ERROR_EXPIRED, $exception->getCode());
        }
    }

    public function test_key_changes_only_after_explicit_reset_starts_a_new_attempt(): void
    {
        $firstAttempt = \Eco_Portal_Checkout_Attempt::ensure(
            [],
            static fn (): string => 'wp_checkout_attempt_one',
            static fn (): string => 'rendered-form-token-c',
            1_776_508_802
        );
        $submitted = \Eco_Portal_Checkout_Attempt::begin(
            $firstAttempt,
            'rendered-form-token-c',
            'original-payload'
        );

        try {
            \Eco_Portal_Checkout_Attempt::begin($submitted, 'rendered-form-token-c', 'changed-payload');
            $this->fail('Changed details must require an explicit new attempt.');
        } catch (RuntimeException $exception) {
            $this->assertSame(\Eco_Portal_Checkout_Attempt::ERROR_CHANGED, $exception->getCode());
        }

        $this->assertSame('wp_checkout_attempt_one', \Eco_Portal_Checkout_Attempt::idempotency_key($submitted));

        // Clearing the cookie-bound state is the explicit Start over action.
        $secondAttempt = \Eco_Portal_Checkout_Attempt::ensure(
            [],
            static fn (): string => 'wp_checkout_attempt_two',
            static fn (): string => 'rendered-form-token-d',
            1_776_508_803
        );

        $this->assertSame('wp_checkout_attempt_two', \Eco_Portal_Checkout_Attempt::idempotency_key($secondAttempt));
        $this->assertNotSame(
            \Eco_Portal_Checkout_Attempt::idempotency_key($submitted),
            \Eco_Portal_Checkout_Attempt::idempotency_key($secondAttempt)
        );
    }
}
