<div class="wc-seg" role="tablist">
    <button type="button" class="wc-seg-btn is-active" data-wc-plat="wp">WordPress</button>
    <button type="button" class="wc-seg-btn" data-wc-plat="custom">Custom website</button>
</div>

<div class="wc-plat is-active mt-4" data-wc-plat-panel="wp">
    <div class="wc-howto">
        <div class="wc-howto-item">
            <span class="wc-howto-num" aria-hidden="true">1</span>
            <div>
                <h4>Install the plugin</h4>
                <p>In WordPress: Plugins → Add New → Upload Plugin → Activate.</p>
                <a href="{{ route('developer-storefront.plugin.download') }}" class="wc-btn wc-btn-secondary mt-2" data-turbo="false" download>Download plugin</a>
            </div>
        </div>
        <div class="wc-howto-item">
            <span class="wc-howto-num" aria-hidden="true">2</span>
            <div>
                <h4>Open Settings → Eco Portal</h4>
                <p>Paste this portal address into the first field.</p>
                <div class="wc-copy-row">
                    <code id="wc-portal" class="wc-code">{{ $portalAddress }}</code>
                    <button type="button" class="wc-btn wc-btn-ghost" data-copy-target="wc-portal">Copy</button>
                </div>
            </div>
        </div>
        <div class="wc-howto-item">
            <span class="wc-howto-num" aria-hidden="true">3</span>
            <div>
                <h4>Paste your key</h4>
                <p>Use the connection key from step 2, then save.</p>
            </div>
        </div>
        <div class="wc-howto-item">
            <span class="wc-howto-num" aria-hidden="true">4</span>
            <div>
                <h4>Click Test connection</h4>
                <p>It shows your store name and product count. Your shop, cart and checkout pages are created for you.</p>
            </div>
        </div>
    </div>
</div>

<div class="wc-plat mt-4" data-wc-plat-panel="custom">
    <div class="wc-howto">
        <div class="wc-howto-item">
            <span class="wc-howto-num" aria-hidden="true">1</span>
            <div>
                <h4>Send your developer this address</h4>
                <p>Their site reads your catalog from here using the key from step 2.</p>
                <div class="wc-copy-row">
                    <code id="wc-api" class="wc-code">{{ $catalogApiUrl }}</code>
                    <button type="button" class="wc-btn wc-btn-ghost" data-copy-target="wc-api">Copy</button>
                </div>
            </div>
        </div>
        <div class="wc-howto-item">
            <span class="wc-howto-num" aria-hidden="true">2</span>
            <div>
                <h4>They call it with your key</h4>
                <p>Sent as a Bearer token. Checkout and payment stay in this portal, so no card data touches your site.</p>
                <div class="wc-api-list">
                    <p><code>GET /catalog</code>products and variants</p>
                    <p><code>GET /api/v1/site/health</code>connection check</p>
                    <p><code>POST /api/v1/checkout</code>start a checkout</p>
                </div>
            </div>
        </div>
    </div>
</div>
