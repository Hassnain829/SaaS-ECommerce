@extends('layouts.user.user-sidebar')

@section('title', 'Order #' . strtoupper($order->order_number) . ' | BaaS Core')


@section('topbar')
  <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center gap-3">
    <button onclick="openSidebar()"
      class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center"
      aria-label="Open sidebar">
      <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor" />
      </svg>
    </button>

    <div
      class="hidden md:flex w-full max-w-[330px] h-11 items-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#94A3B8]">
      Search orders...
    </div>

    <div class="ml-auto flex items-center gap-2">
      <a href="{{ route('notifications') }}"
        class="h-10 w-10 rounded-lg text-[#64748B] grid place-items-center hover:bg-[#F1F5F9]" aria-label="Notifications">
        <svg width="18" height="18" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z"
            fill="currentColor" />
        </svg>
      </a>
      <a href="{{ route('generalSettings') }}"
        class="h-10 w-10 rounded-lg text-[#64748B] grid place-items-center hover:bg-[#F1F5F9]" aria-label="Settings">
        <svg width="18" height="18" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M7.3 20L6.9 16.8C6.68333 16.7167 6.47917 16.6167 6.2875 16.5C6.09583 16.3833 5.90833 16.2583 5.725 16.125L2.75 17.375L0 12.625L2.575 10.675C2.55833 10.5583 2.55 10.4458 2.55 10.3375C2.55 10.2292 2.55 10.1167 2.55 10C2.55 9.88333 2.55 9.77083 2.55 9.6625C2.55 9.55417 2.55833 9.44167 2.575 9.325L0 7.375L2.75 2.625L5.725 3.875C5.90833 3.74167 6.1 3.61667 6.3 3.5C6.5 3.38333 6.7 3.28333 6.9 3.2L7.3 0H12.8L13.2 3.2C13.4167 3.28333 13.6208 3.38333 13.8125 3.5C14.0042 3.61667 14.1917 3.74167 14.375 3.875L17.35 2.625L20.1 7.375L17.525 9.325C17.5417 9.44167 17.55 9.55417 17.55 9.6625C17.55 9.77083 17.55 9.88333 17.55 10C17.55 10.1167 17.55 10.2292 17.55 10.3375C17.55 10.4458 17.5333 10.5583 17.5 10.675L20.075 12.625L17.325 17.375L14.375 16.125C14.1917 16.2583 14 16.3833 13.8 16.5C13.6 16.6167 13.4 16.7167 13.2 16.8L12.8 20H7.3Z"
            fill="currentColor" />
        </svg>
      </a>
      
    </div>
  </header>
@endsection

@section('content')
  <div class="w-full space-y-5">
    <div class="flex flex-wrap items-center gap-2 text-sm text-[#64748B]">
      <a href="{{ route('dashboard') }}" class="hover:text-[#0F172A]">Dashboard</a>
      <span>&gt;</span>
      <a href="{{ route('orders') }}" class="hover:text-[#0F172A]">Orders</a>
      <span>&gt;</span>
      <span class="text-[#0F172A] font-inter font-medium">Order #{{ strtoupper($order->order_number) }}</span>
    </div>

    <section class="space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Order #{{ strtoupper($order->order_number) }}</h1>
            <span
              class="inline-flex items-center gap-2 rounded-full border border-[#DBEAFE] bg-[#EFF6FF] px-3 py-1 text-xs font-bold uppercase tracking-[.6px] text-[#1D4ED8]">
              <span class="h-2 w-2 rounded-full bg-[#3B82F6]"></span> {{ $order->status }}
            </span>
          </div>
          <p class="text-sm text-[#64748B] mt-1">Placed on
            {{ $order->placed_at ? $order->placed_at->format('F d, Y \a\t h:i A') : '-' }} • {{ $order->items->count() }}
            items</p>
        </div>
        <div
          class="inline-flex items-center gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC]/80 px-4 py-3 text-sm text-[#475569]">
          <svg width="18" height="15" viewBox="0 0 19 15" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M8.27083 9.20833L13 4.5L11.8125 3.3125L8.27083 6.85417L6.5 5.08333L5.33333 6.25L8.27083 9.20833ZM0 15V13.3333H18.3333V15H0ZM2.5 12.5C2.04167 12.5 1.64931 12.3368 1.32292 12.0104C0.996528 11.684 0.833333 11.2917 0.833333 10.8333V1.66667C0.833333 1.20833 0.996528 0.815972 1.32292 0.489583C1.64931 0.163194 2.04167 0 2.5 0H15.8333C16.2917 0 16.684 0.163194 17.0104 0.489583C17.3368 0.815972 17.5 1.20833 17.5 1.66667V10.8333C17.5 11.2917 17.3368 11.684 17.0104 12.0104C16.684 12.3368 16.2917 12.5 15.8333 12.5H2.5ZM2.5 10.8333H15.8333V1.66667H2.5V10.8333Z"
              fill="#0052CC" />
          </svg>
          Status updated automatically via integrated courier
        </div>
      </div>

      <div
        class="rounded-xl border border-[#E2E8F0] bg-white p-5 md:p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div class="space-y-4">
          <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-lg bg-[#EEF2FF] grid place-items-center text-[#4F46E5]">
              <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M11 15.95L9.45 12.5L6 10.95L9.45 9.375L11 5.95L12.575 9.375L16 10.95L12.575 12.5L11 15.95ZM11 21.95C9.2 21.95 7.5125 21.5375 5.9375 20.7125C4.3625 19.8875 3.05 18.7333 2 17.25V19.95H0V13.95H6V15.95H3.55C4.4 17.2 5.47917 18.1792 6.7875 18.8875C8.09583 19.5958 9.5 19.95 11 19.95C12.9167 19.95 14.6542 19.4 16.2125 18.3C17.7708 17.2 18.8667 15.7417 19.5 13.925L21.45 14.375C20.7 16.6417 19.3667 18.4708 17.45 19.8625C15.5333 21.2542 13.3833 21.95 11 21.95Z"
                  fill="currentColor" />
              </svg>
            </div>
            <div>
              <h2 class="text-xl leading-tight">Shipping Automation</h2>
              <p class="text-[#64748B] text-sm">Smart courier selection based on lowest cost and fastest delivery time.
              </p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="flex items-center gap-3">
              <span class="uppercase text-[11px] tracking-[1px] font-bold text-[#94A3B8]">Network:</span>
              <span class="font-semibold">UPS</span>
              <span class="font-semibold text-[#1E40AF]">FedEx</span>
              <span class="font-semibold text-[#DC2626]">DHL</span>
              <span class="font-semibold text-[#EF4444]">CANADA POST</span>
            </div>
            <span class="hidden sm:block h-6 w-px bg-[#E2E8F0]"></span>
            <p><span class="text-[#334155] font-semibold">Courier Selected:</span> <span
                class="text-[#0052CC] font-bold">DHL Express</span> <span class="text-xs text-[#64748B] italic">(via
                optimization algorithm)</span></p>
          </div>
        </div>

        <div
          class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] px-5 py-4 flex items-center justify-between gap-4 min-w-0 lg:min-w-[320px]">
          <p class="font-semibold text-[#334155]">Enable Automated fulfillment</p>
          <button class="relative h-7 w-14 rounded-full bg-[#0052CC]" aria-label="Enabled">
            <span
              class="absolute right-0.5 top-0.5 h-6 w-6 rounded-full bg-white grid place-items-center text-[#2563EB] text-xs">✓</span>
          </button>
        </div>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_324px] lg:items-start">
      <div class="space-y-4">
        <article class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white">
          <div class="h-16 px-5 md:px-6 border-b border-[#E2E8F0] flex items-center justify-between">
            <h3 class="text-2xl leading-tight">Order Items</h3>
            <button class="text-[#0052CC] font-semibold text-sm">Edit Items</button>
          </div>
          <div class="divide-y divide-[#F1F5F9]">
            @foreach($order->items as $item)
              <div class="p-5 md:p-6 flex items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                  @if($item->product && $item->product->images->count() > 0)
                    <img src="{{ Storage::url($item->product->images->first()->image_path) }}"
                      class="h-14 w-14 rounded-xl object-cover" alt="{{ $item->product_name }}">
                  @else
                    <div class="h-14 w-14 rounded-xl bg-[#F1F5F9] grid place-items-center text-xs text-[#94A3B8]">IMG</div>
                  @endif
                  <div>
                    <h4 class="text-xl leading-tight">{{ $item->product_name }}</h4>
                    <p class="text-[#64748B]">{{ $item->variant_label }}</p>
                  </div>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-2xl leading-tight font-semibold">
                    {{ $selectedStore->currency ?? '$' }}{{ number_format((float) $item->unit_price, 2) }}</p>
                  <p class="text-[#64748B]">Qty: {{ $item->quantity }}</p>
                </div>
              </div>
            @endforeach
          </div>
        </article>

        <article class="rounded-xl border border-[#E2E8F0] bg-white p-5 md:p-6">
          <h3 class="text-2xl leading-tight mb-5">Payment Summary</h3>
          <div class="space-y-3 text-[#334155]">
            <div class="flex justify-between">
              <span>Subtotal</span><span>{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Shipping</span><span>{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->shipping_total ?? 0, 2) }}</span>
            </div>
            <div class="flex justify-between"><span>Estimated
                Tax</span><span>{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->tax_total ?? 0, 2) }}</span>
            </div>
          </div>
          <div class="my-4 border-t border-[#E2E8F0]"></div>
          <div class="flex justify-between items-end">
            <span class="text-xl leading-tight">Total</span>
            <span
              class="text-4xl leading-tight font-bold text-[#0052CC]">{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->total, 2) }}</span>
          </div>
          <div class="mt-5 rounded-lg bg-[#F8FAFC] p-3 flex items-center justify-between">
            <p class="text-[#0F172A]">Payment Status</p>
            <span
              class="px-2 py-1 rounded {{ $order->payment_status === 'paid' ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-[#FEF9C3] text-[#A16207]' }} text-xs font-bold uppercase">{{ $order->payment_status }}</span>
          </div>
        </article>
      </div>

      <aside class="space-y-4">
        <article class="rounded-xl border border-[#E2E8F0] bg-white p-5 md:p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-2xl leading-tight">Shipping Label</h3>
            <span class="text-[10px] font-bold bg-[#F1F5F9] px-2 py-1 rounded">{{ $order->tracking_number ?? 'Not Assigned' }}</span>
          </div>
          <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST"
            class="mt-4 border-t border-[#E2E8F0] pt-4">
            @csrf
            @method('PATCH')
            <label for="status" class="block text-xs font-bold uppercase tracking-[1px] text-[#94A3B8] mb-2">Update
              Status</label>
            <div class="flex gap-2">
              <select name="status" id="status"
                class="flex-1 rounded-lg border border-[#E2E8F0] text-sm p-2 text-[#334155]">
                <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>Shipped</option>
                <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
              </select>
              <button type="submit"
                class="px-4 py-2 rounded-lg bg-[#0052CC] text-white font-semibold text-sm">Save</button>
            </div>
          </form>
          <p class="text-xs uppercase tracking-[1.2px] text-[#94A3B8] font-bold mt-4">Tracking Details</p>
          <p class="text-[#0052CC] font-bold mt-1 mb-4">{{ $order->tracking_url ?? 'Pending carrier generation' }}</p>
          <button class="w-full h-11 rounded-lg bg-[#0052CC] text-white font-semibold" {{ empty($order->tracking_number) ? 'disabled' : '' }}>Print Label</button>
        </article>

        <article class="rounded-xl border border-[#E2E8F0] bg-white p-5 md:p-6">
          <h3 class="text-2xl leading-tight mb-4">Customer Info</h3>
          <div class="flex items-center gap-3 mb-4">
            @php
              $initials = collect(explode(' ', $order->customer->full_name ?? ''))->map(fn($n) => substr($n, 0, 1))->take(2)->join('');
            @endphp
            <div class="h-11 w-11 rounded-full bg-[#EFF6FF] text-[#1D4ED8] grid place-items-center font-bold">
              {{ strtoupper($initials) }}</div>
            <div>
              <p class="font-bold text-[#0F172A]">{{ $order->customer->full_name ?? $order->customer_email }}</p>
              <p class="text-[#64748B]">{{ $order->customer_email }}</p>
            </div>
          </div>
          <div class="space-y-4 text-sm">
            @php
              $shipping = $order->addresses->firstWhere('type', 'shipping');
            @endphp
            @if($shipping)
              <div>
                <p class="text-xs uppercase tracking-[1px] text-[#94A3B8] font-bold">Shipping Address</p>
                <p class="text-[#334155] mt-1">{{ $shipping->address_line1 }}<br>{{ $shipping->city }},
                  {{ $shipping->state }} {{ $shipping->postal_code }}<br>{{ $shipping->country }}</p>
              </div>
            @endif
            <div>
              <p class="text-xs uppercase tracking-[1px] text-[#94A3B8] font-bold">Phone</p>
              <p class="text-[#334155] mt-1">{{ $order->customer_phone ?? 'N/A' }}</p>
            </div>
          </div>
          <a href="{{ route('customersProfile', $order->customer_id) }}"
            class="mt-5 inline-flex w-full h-10 items-center justify-center rounded-lg border border-[#E2E8F0] font-semibold">View
            Profile</a>
        </article>

        <article class="rounded-xl border border-[#E2E8F0] bg-white p-5 md:p-6">
          <h3 class="text-2xl leading-tight mb-5">Order Timeline</h3>
          <div class="relative">
            <div data-role="timeline-connector" class="absolute z-0 pointer-events-none"
              style="left: 11px; top: 34px; bottom: 40px; width: 2px; background: #94A3B8;">
            </div>
            <div class="space-y-5">
              @if($order->status !== 'pending' && $order->updated_at)
              <div class="relative z-10 flex items-start gap-3">
                <span
                  class="mt-1 h-6 w-6 shrink-0 rounded-full bg-[#0052CC] text-white text-xs grid place-items-center">⚡</span>
                <div>
                  <p class="font-semibold">Status updated to {{ ucfirst($order->status) }}</p>
                  <p class="text-xs text-[#64748B]">System • {{ $order->updated_at->format('M d, h:i A') }}</p>
                </div>
              </div>
              @endif
              @if($order->payment_status === 'paid')
              <div class="relative z-10 flex items-start gap-3">
                <span
                  class="mt-1 h-6 w-6 shrink-0 rounded-full bg-[#10B981] text-white text-xs grid place-items-center">✓</span>
                <div>
                  <p class="font-semibold">Payment Completed</p>
                  <p class="text-xs text-[#64748B]">System • {{ $order->placed_at ? $order->placed_at->format('M d, h:i A') : $order->created_at->format('M d, h:i A') }}</p>
                </div>
              </div>
              @endif
              <div class="relative z-10 flex items-start gap-3">
                <span
                  class="mt-1 h-6 w-6 shrink-0 rounded-full bg-[#F1F5F9] text-[#64748B] text-xs grid place-items-center">📦</span>
                <div>
                  <p class="font-semibold">Order Placed</p>
                  <p class="text-xs text-[#64748B]">by {{ $order->customer->full_name ?? 'Customer' }} • {{ $order->created_at->format('M d, h:i A') }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-6 border-t border-[#F1F5F9] pt-5 space-y-3">
            <textarea rows="3" class="w-full rounded-lg border border-[#E2E8F0] bg-white p-3 text-sm"
              placeholder="Add internal note..."></textarea>
            <button class="w-full h-10 rounded-lg bg-[#F1F5F9] font-semibold">Add Note</button>
          </div>
        </article>
      </aside>
    </section>
  </div>
@endsection