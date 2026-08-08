<section class="delivery-advanced" aria-label="Advanced delivery settings">
    <div class="delivery-advanced-intro">
        <h2 class="text-lg font-semibold text-[#0F172A]">Advanced delivery settings</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#64748B]">
            Use these tables when you need precise edits. Most merchants can stay on the Delivery home cards and guided setup.
        </p>
    </div>

    <div class="space-y-4">
        <details class="delivery-advanced-panel">
            <summary>
                <div>
                    <h3>Ship-from locations</h3>
                    <p>Places where inventory is stored and orders ship from.</p>
                </div>
                <span>Expand</span>
            </summary>
            <div class="delivery-advanced-body">
                <section data-advanced-section="ship-from" class="scroll-mt-24">
                    <div class="mb-4">
                        <a href="{{ route('settings.locations.index') }}" class="text-sm font-semibold text-[#1D4ED8]">Open full locations page</a>
                    </div>
                    @include('user_view.shipping.tabs.locations')
                </section>
            </div>
        </details>

        <details class="delivery-advanced-panel">
            <summary>
                <div>
                    <h3>Delivery areas</h3>
                    <p>Country, region, and postal coverage rules.</p>
                </div>
                <span>Expand</span>
            </summary>
            <div class="delivery-advanced-body">
                <section data-advanced-section="areas" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.zones')
                </section>
            </div>
        </details>

        <details class="delivery-advanced-panel">
            <summary>
                <div>
                    <h3>Delivery options</h3>
                    <p>Checkout labels, pricing, FedEx live rates, and visibility.</p>
                </div>
                <span>Expand</span>
            </summary>
            <div class="delivery-advanced-body">
                <section data-advanced-section="options" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.methods')
                </section>
            </div>
        </details>

        <details class="delivery-advanced-panel">
            <summary>
                <div>
                    <h3>Package sizes</h3>
                    <p>Default boxes used for carrier rates when products do not list dimensions.</p>
                </div>
                <span>Expand</span>
            </summary>
            <div class="delivery-advanced-body">
                <section data-advanced-section="packages" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.packages')
                </section>
            </div>
        </details>

        <details class="delivery-advanced-panel">
            <summary>
                <div>
                    <h3>Connected providers</h3>
                    <p>Carrier accounts for labels and rates. Manual delivery is a fallback, not a fake carrier.</p>
                </div>
                <span>Expand</span>
            </summary>
            <div class="delivery-advanced-body">
                <section data-advanced-section="providers" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.carriers')
                    @include('user_view.shipping.partials.fedex_certification_tools')
                </section>
            </div>
        </details>
    </div>
</section>
