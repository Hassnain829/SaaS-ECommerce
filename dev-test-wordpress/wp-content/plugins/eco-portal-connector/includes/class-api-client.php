<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Server-side client for the Eco Commerce portal APIs.
 * The developer storefront token never leaves WordPress PHP.
 */
final class Eco_Portal_Api_Client
{
    public function portal_base_url(): string
    {
        return rtrim(trim((string) get_option('eco_portal_base_url', '')), '/');
    }

    public function token(): string
    {
        return trim((string) get_option('eco_portal_token', ''));
    }

    public function is_configured(): bool
    {
        return $this->portal_base_url() !== '' && $this->token() !== '';
    }

    /**
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_catalog(): array
    {
        return $this->request('GET', '/api/developer-storefront/catalog');
    }

    /**
     * Sync an order paid on this WordPress site into the portal.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function sync_external_order(array $payload, ?string $idempotency_key = null): array
    {
        $headers = [];
        if ($idempotency_key !== null && $idempotency_key !== '') {
            $headers['Idempotency-Key'] = $idempotency_key;
        }

        return $this->request('POST', '/api/v1/external/orders', $payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function create_checkout(array $payload): array
    {
        return $this->request('POST', '/api/v1/checkout', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function delivery_options(int $checkout_id, array $payload): array
    {
        return $this->request('POST', '/api/v1/checkout/'.$checkout_id.'/delivery-options', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function select_shipping_method(int $checkout_id, array $payload): array
    {
        return $this->request('POST', '/api/v1/checkout/'.$checkout_id.'/shipping-method', $payload);
    }

    /**
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function confirm_checkout(int $checkout_id): array
    {
        return $this->request('POST', '/api/v1/checkout/'.$checkout_id.'/confirm');
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $extra_headers
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function request(string $method, string $path, ?array $body = null, array $extra_headers = []): array
    {
        if (! $this->is_configured()) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'message' => 'Portal URL and API token are not configured.',
                'raw' => '',
            ];
        }

        $url = $this->portal_base_url().$path;
        $headers = array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token(),
        ], $extra_headers);

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => $headers,
            'redirection' => 0,
        ];

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'message' => $response->get_error_message(),
                'raw' => '',
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $data = null;
        }

        $message = '';
        if (is_array($data)) {
            if (isset($data['message']) && is_string($data['message'])) {
                $message = $data['message'];
            } elseif (isset($data['errors']) && is_array($data['errors'])) {
                $message = wp_json_encode($data['errors']);
            }
        }

        if ($message === '' && $status >= 400) {
            $message = $raw !== '' && str_starts_with(ltrim($raw), '<')
                ? "HTTP {$status}: portal returned HTML (check URL and CORS/firewall)."
                : "HTTP {$status}";
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => $data,
            'message' => $message,
            'raw' => $raw,
        ];
    }
}
