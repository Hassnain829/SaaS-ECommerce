@extends('layouts.user.user-sidebar')

@section('title', 'FedEx EULA | BaaS Core')

@section('content')
    <div
        class="mx-auto max-w-[900px] space-y-6"
        x-data="{
            accepted: false,
            scrolledToBottom: false,
            contentRequiresScroll: true,
            init() {
                this.$nextTick(() => {
                    const el = this.$refs.eulaScroll;
                    if (! el) {
                        return;
                    }

                    this.contentRequiresScroll = el.scrollHeight > el.clientHeight + 8;

                    if (! this.contentRequiresScroll) {
                        this.scrolledToBottom = true;
                    }
                });
            },
            handleScroll() {
                const el = this.$refs.eulaScroll;
                if (! el) {
                    return;
                }

                this.scrolledToBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 8;
            },
            canSubmit() {
                return this.accepted && (this.scrolledToBottom || ! this.contentRequiresScroll);
            },
        }"
    >
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-[#0F172A]">Step 2 — FedEx End User License Agreement</h2>
            @unless ($eulaAvailable)
                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">FedEx EULA file is missing. A platform administrator must add the legal document before merchants can continue.</p>
            @else
                <div
                    x-ref="eulaScroll"
                    class="mt-4 max-h-[420px] overflow-y-auto rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-sm leading-6 text-[#334155]"
                    @scroll="handleScroll()"
                >
                    {!! $eulaHtml !!}
                </div>
                <p x-show="contentRequiresScroll && ! scrolledToBottom" class="mt-2 text-xs text-[#64748B]">Scroll to the bottom of the agreement to continue.</p>
                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.eula.accept', $session) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="flex items-start gap-2 text-sm text-[#475569]">
                        <input
                            type="checkbox"
                            name="accept_eula"
                            value="1"
                            required
                            class="mt-1 rounded border-[#CBD5E1]"
                            x-model="accepted"
                        >
                        <span>I am authorized to connect this FedEx account and accept the FedEx End User License Agreement (version {{ $eulaVersion }}).</span>
                    </label>
                    <button
                        type="submit"
                        class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="! canSubmit()"
                    >
                        Accept FedEx EULA and continue
                    </button>
                </form>
            @endunless
            <form method="POST" action="{{ route('settings.shipping.fedex-integrator.cancel', $session) }}" class="mt-3">@csrf<button class="text-sm font-semibold text-[#64748B]">Cancel setup</button></form>
        </section>
    </div>
@endsection
