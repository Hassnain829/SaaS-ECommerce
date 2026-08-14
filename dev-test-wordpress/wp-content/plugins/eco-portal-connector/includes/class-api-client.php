<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Server-side client for the Eco Commerce portal APIs.
 * The connection key never leaves WordPress PHP and is never printed into storefront HTML or JavaScript.
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
    public function get_health(): array
    {
        return $this->request('GET', '/api/v1/site/health');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function report_diagnostics(array $payload): array
    {
        return $this->request('POST', '/api/v1/site/health', $payload);
    }

    /**
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_product(int $product_id, bool $force = false): array
    {
        if (! $force) {
            $cached = Eco_Portal_Catalog_Cache::get('product', (string) $product_id);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->request('GET', '/api/v1/catalog/products/'.$product_id);
        if ($result['ok']) {
            Eco_Portal_Catalog_Cache::put('product', (string) $product_id, $result);
            $version = is_array($result['data']['meta'] ?? null) ? (string) ($result['data']['meta']['catalog_version'] ?? '') : '';
            Eco_Portal_Catalog_Cache::remember_version($version);
        }

        return $result;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_products(array $query = [], bool $force = false): array
    {
        $cache_key = wp_json_encode($query) ?: 'default';
        if (! $force) {
            $cached = Eco_Portal_Catalog_Cache::get('catalog', $cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->request('GET', '/api/v1/catalog/products', null, [], $query);
        if ($result['ok']) {
            Eco_Portal_Catalog_Cache::put('catalog', $cache_key, $result);
            $version = is_array($result['data']['meta'] ?? null) ? (string) ($result['data']['meta']['catalog_version'] ?? '') : '';
            Eco_Portal_Catalog_Cache::remember_version($version);
        }

        return $result;
    }

    /**
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_categories(bool $force = false): array
    {
        if (! $force) {
            $cached = Eco_Portal_Catalog_Cache::get('categories', 'all');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->request('GET', '/api/v1/catalog/categories');
        if ($result['ok']) {
            Eco_Portal_Catalog_Cache::put('categories', 'all', $result);
            $version = is_array($result['data']['meta'] ?? null) ? (string) ($result['data']['meta']['catalog_version'] ?? '') : '';
            Eco_Portal_Catalog_Cache::remember_version($version);
        }

        return $result;
    }

    /**
     * Published catalog plus store identity from the versioned commerce API.
     *
     * @param  array<string, scalar>  $query
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_catalog(array $query = [], bool $force = false): array
    {
        $products = $this->get_products($query, $force);
        if (! $products['ok'] || ! is_array($products['data'])) {
            return $products;
        }

        $health = $this->get_health();
        $categories = $this->get_categories($force);
        $payload = $products['data'];
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $store = is_array($meta['store'] ?? null) ? $meta['store'] : [];
        if ($store === [] && is_array($health['data']['store'] ?? null)) {
            $store = $health['data']['store'];
        }
        if (! isset($store['platform_checkout']) && is_array($health['data']['readiness'] ?? null)) {
            $store['platform_checkout'] = [
                'ready' => ! empty($health['data']['readiness']['stripe']),
            ];
        }

        return [
            'ok' => true,
            'status' => (int) $products['status'],
            'data' => [
                'store' => $store,
                'products' => $rows,
                'categories' => is_array($categories['data']['data'] ?? null) ? $categories['data']['data'] : [],
                'meta' => $meta,
            ],
            'message' => '',
            'raw' => (string) $products['raw'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function create_checkout(array $payload): array
    {
        $key = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));

        return $this->request('POST', '/api/v1/checkout', $payload, [
            'Idempotency-Key' => $key,
        ]);
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
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_order_confirmation(string $token): array
    {
        return $this->request('GET', '/api/v1/orders/confirmation/'.rawurlencode($token));
    }

    /**
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_event_config(): array
    {
        return $this->request('GET', '/api/v1/site/events/config');
    }

    /**
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function get_catalog_events(string $after = ''): array
    {
        $query = [];
        if ($after !== '') {
            $query['after'] = $after;
        }

        return $this->request('GET', '/api/v1/catalog/events', null, [], $query);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $extra_headers
     * @param  array<string, scalar>  $query
     * @return array{ok:bool,status:int,data:mixed,message:string,raw:string}
     */
    public function request(string $method, string $path, ?array $body = null, array $extra_headers = [], array $query = []): array
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
        $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);
        if ($query !== []) {
            $url .= (str_contains($path, '?') ? '&' : '?').http_build_query($query);
        }
        $headers = array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token(),
            'X-Eco-Site-Url' => home_url(),
            'X-Eco-Plugin-Version' => defined('ECO_PORTAL_CONNECTOR_VERSION') ? ECO_PORTAL_CONNECTOR_VERSION : '1.3.0',
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
