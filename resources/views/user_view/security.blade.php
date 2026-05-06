@extends('layouts.user.user-sidebar')

@section('title', 'Security | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
  <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
    <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
  </button>
  <div>
    <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Security</h1>
    <p class="hidden md:block text-xs text-[#64748B]">Sessions and recent account activity</p>
  </div>
  <a href="{{ route('profileSettings') }}" class="ml-auto h-10 px-4 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] inline-flex items-center justify-center">Profile settings</a>
</header>
@endsection

@section('content')
@php
  $eventLabels = [
    'login' => 'Signed in',
    'logout' => 'Signed out',
    'failed_login' => 'Failed sign-in attempt',
    'login_throttled' => 'Sign-in temporarily limited',
    'password_changed' => 'Password changed',
    'profile_updated' => 'Profile updated',
    'account_registered' => 'Account registered',
    'account_deactivated' => 'Account deactivated',
    'store_switch' => 'Store switched',
    'team_member_invited' => 'Team member added',
    'team_member_removed' => 'Team member removed',
    'role_changed' => 'Team role changed',
    'api_key_created' => 'Developer token created',
    'api_key_revoked' => 'Developer token revoked',
    'order_status_changed' => 'Order status changed',
    'product_bulk_action' => 'Bulk catalog action',
    'import_confirmed' => 'Import confirmed',
    'product_created' => 'Product created',
    'product_updated' => 'Product updated',
    'product_deleted' => 'Product deleted',
    'user_session_revoked' => 'Session revoked',
    'store_settings_updated' => 'Store settings updated',
    'store_deleted' => 'Store removed',
  ];
@endphp

<div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-6">
  @include('user_view.partials.flash_success')

  @if ($errors->any())
    <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
      {{ $errors->first() }}
    </div>
  @endif

  <section>
    <h2 class="text-3xl font-poppins text-[#0F172A]">Account security</h2>
    <p class="text-[#64748B] text-sm md:text-base">Review signed-in devices and store-sensitive activity for {{ $selectedStore?->name ?? 'your account' }}.</p>
  </section>

  <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6">
    <div class="space-y-6">
      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between gap-4">
          <div>
            <h3 class="text-2xl font-poppins text-[#0F172A]">Active sessions</h3>
            <p class="text-sm text-[#64748B]">Sign out devices you do not recognize.</p>
          </div>
          <span class="rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-bold text-[#0052CC]">{{ $sessions->whereNull('revoked_at')->count() }} active</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[680px]">
            <thead class="bg-[#F8FAFC] text-[#64748B] text-xs uppercase tracking-[0.7px] font-bold">
              <tr>
                <th class="text-left px-6 py-4">Device</th>
                <th class="text-left px-4 py-4">IP address</th>
                <th class="text-left px-4 py-4">Last activity</th>
                <th class="text-right px-6 py-4">Action</th>
              </tr>
            </thead>
            <tbody class="text-sm">
              @forelse ($sessions as $session)
                @php
                  $isCurrent = (int) $session->id === (int) $currentSessionId && $session->revoked_at === null;
                @endphp
                <tr class="border-t border-[#E2E8F0]">
                  <td class="px-6 py-5">
                    <p class="font-semibold text-[#0F172A]">{{ $session->browser ?? 'Browser' }} on {{ $session->os ?? 'Unknown OS' }}</p>
                    <p class="text-[#64748B]">{{ $session->device_type ?? 'Device' }}{{ $isCurrent ? ' - this device' : '' }}</p>
                    @if ($session->location)
                      <p class="text-xs text-[#64748B]">{{ $session->location }}</p>
                    @endif
                  </td>
                  <td class="px-4 py-5 text-[#334155]">{{ $session->ip_address ?? 'Unknown' }}</td>
                  <td class="px-4 py-5 text-[#334155]">
                    @if ($session->ended_at)
                      Ended {{ $session->ended_at->diffForHumans() }}
                    @else
                      {{ $session->last_activity?->diffForHumans() ?? 'Not recorded yet' }}
                    @endif
                  </td>
                  <td class="px-6 py-5 text-right">
                    @if ($session->revoked_at)
                      <span class="inline-flex rounded-md bg-[#F1F5F9] px-2.5 py-1 text-[#64748B] font-semibold">Signed out</span>
                    @elseif ($isCurrent)
                      <span class="inline-flex rounded-md bg-[#DBEAFE] px-2.5 py-1 text-[#0052CC] font-semibold">Current session</span>
                    @else
                      <form method="POST" action="{{ route('security.sessions.destroy', $session) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[#BA1A1A] font-semibold hover:underline">Sign out</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="px-6 py-10 text-center text-[#64748B]">No sessions have been recorded for this account yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>

      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-[#E2E8F0]">
          <h3 class="text-2xl font-poppins text-[#0F172A]">Recent security activity</h3>
          <p class="text-sm text-[#64748B]">Audit records from your account and the active store.</p>
        </div>
        <div class="divide-y divide-[#E2E8F0]">
          @forelse ($securityLogs as $log)
            <article class="px-6 py-4 flex gap-4">
              <div class="mt-1 h-10 w-10 rounded-lg flex items-center justify-center {{ $log->severity === 'warning' ? 'bg-[#FEF3C7] text-[#B45309]' : ($log->severity === 'critical' ? 'bg-[#FEE2E2] text-[#BA1A1A]' : 'bg-[#DBEAFE] text-[#0052CC]') }}">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 18C5.6 18 2 14.4 2 10C2 5.6 5.6 2 10 2C14.4 2 18 5.6 18 10C18 14.4 14.4 18 10 18ZM9 14H11V9H9V14ZM9 7H11V5H9V7Z" fill="currentColor"/></svg>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1">
                  <p class="font-semibold text-[#0F172A]">{{ $eventLabels[$log->event_type] ?? \Illuminate\Support\Str::headline($log->event_type) }}</p>
                  <time class="text-xs text-[#64748B]">{{ $log->created_at?->format('M j, Y g:i A') }}</time>
                </div>
                <p class="mt-1 text-sm text-[#64748B]">
                  {{ $log->user?->name ?? 'System' }}
                  @if ($log->targetUser)
                    changed {{ $log->targetUser->name }}
                  @endif
                  @if ($log->store)
                    in {{ $log->store->name }}
                  @endif
                </p>
                @if (is_array($log->metadata) && $log->metadata !== [])
                  <p class="mt-2 text-xs text-[#64748B]">
                    @foreach (array_slice($log->metadata, 0, 3, true) as $key => $value)
                      <span class="inline-flex rounded-full bg-[#F8FAFC] border border-[#E2E8F0] px-2 py-1 mr-1 mb-1">{{ \Illuminate\Support\Str::headline($key) }}: {{ is_scalar($value) ? $value : 'saved' }}</span>
                    @endforeach
                  </p>
                @endif
              </div>
            </article>
          @empty
            <div class="px-6 py-10 text-center text-[#64748B]">Security activity will appear here after sensitive actions are performed.</div>
          @endforelse
        </div>
      </section>
    </div>

    <aside class="space-y-6">
      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-[#E2E8F0]">
          <h3 class="text-xl font-poppins text-[#0F172A]">Account protection</h3>
        </div>
        <div class="p-6 space-y-5 text-sm">
          <div>
            <p class="font-semibold text-[#0F172A]">Email</p>
            <p class="text-[#64748B]">{{ auth()->user()->email }}</p>
            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold {{ auth()->user()->email_verified_at ? 'bg-[#D1FAE5] text-[#047857]' : 'bg-[#FEF3C7] text-[#B45309]' }}">
              {{ auth()->user()->email_verified_at ? 'Verified' : 'Needs verification' }}
            </span>
          </div>
          <hr class="border-[#E2E8F0]">
          <div>
            <p class="font-semibold text-[#0F172A]">Password</p>
            <p class="text-[#64748B]">Change it from your profile settings when a teammate leaves or you suspect exposure.</p>
            <a href="{{ route('profileSettings') }}#password" class="mt-3 inline-flex h-10 px-4 rounded-lg bg-[#0052CC] text-white font-semibold items-center justify-center">Change password</a>
          </div>
          <hr class="border-[#E2E8F0]">
          <div>
            <p class="font-semibold text-[#0F172A]">Two-step verification</p>
            <p class="text-[#64748B]">Not configured in this build. This stays off until a real setup flow exists.</p>
          </div>
        </div>
      </section>

      <section class="bg-white border border-[#FECDD3] rounded-xl overflow-hidden">
        <div class="px-6 py-4 bg-[#FFF1F2] border-b border-[#FFE4E6]">
          <h3 class="text-xl font-poppins text-[#BE123C]">Account access</h3>
        </div>
        <form method="POST" action="{{ route('profile.deactivate') }}" class="p-6 space-y-4">
          @csrf
          @method('PATCH')
          <p class="text-sm leading-6 text-[#64748B]">Deactivate your user account only after another owner can manage every store you own.</p>
          <label class="block space-y-2">
            <span class="text-xs uppercase tracking-[0.7px] font-bold text-[#64748B]">Type deactivate to confirm</span>
            <input name="confirm_deactivation" class="w-full h-10 rounded-lg border border-[#FECDD3] px-3 text-sm" autocomplete="off">
          </label>
          <button type="submit" class="w-full h-10 rounded-lg border border-[#FECDD3] text-[#BA1A1A] font-semibold bg-white hover:bg-[#FFF1F2]">Deactivate account</button>
        </form>
      </section>
    </aside>
  </div>
</div>
@endsection
