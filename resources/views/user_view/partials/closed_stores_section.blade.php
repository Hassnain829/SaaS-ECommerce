@php
    $closedStores = $closedStores ?? collect();
@endphp

@if ($closedStores->isNotEmpty())
    <section id="closed-stores" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Closed stores</p>
            <h2 class="mt-1 text-xl font-bold text-slate-900">Closed stores</h2>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Stores you've closed are kept here until you restore or permanently delete them.
            </p>
        </div>

        <div class="space-y-3">
            @foreach ($closedStores as $closedStore)
                @php
                    $closedOn = optional($closedStore->deleted_at)->format('M j, Y');
                    $closedStorePayload = [
                        'id' => $closedStore->id,
                        'name' => $closedStore->name,
                        'delete_url' => route('store.permanent-destroy', ['storeId' => $closedStore->id]),
                    ];
                @endphp
                <article class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-slate-900">{{ $closedStore->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Closed on: {{ $closedOn ?: 'Unknown date' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('store.restore', ['storeId' => $closedStore->id]) }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Restore Store
                            </button>
                        </form>
                        <button
                            type="button"
                            class="js-open-permanent-delete-modal inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                            data-store='@json($closedStorePayload)'
                        >
                            Delete Permanently
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
