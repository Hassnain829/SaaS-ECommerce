@extends('layouts.user.user-sidebar')

@section('title', 'Profile Settings | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
  <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
    <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
  </button>
  <div>
    <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Profile settings</h1>
    <p class="hidden md:block text-xs text-[#64748B]">Account identity and password</p>
  </div>
  <button type="submit" form="profileForm" class="ml-auto h-10 px-5 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Save profile</button>
</header>
@endsection

@section('content')
@php
  $initial = \Illuminate\Support\Str::of($profileUser->name)->trim()->substr(0, 1)->upper();
@endphp

<div class="max-w-[1180px] mx-auto space-y-6">
  @include('user_view.partials.flash_success')

  @if ($errors->any())
    <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
      {{ $errors->first() }}
    </div>
  @endif

  <section class="bg-white border border-[#CBD5E1] rounded-xl p-6">
    <div class="flex flex-col lg:flex-row lg:items-center gap-6">
      <div class="h-28 w-28 rounded-full bg-[#E2E8F0] border-4 border-white shadow-md overflow-hidden flex items-center justify-center text-3xl font-bold text-[#475569]">
        @if ($profileUser->avatar)
          <img src="{{ asset('storage/'.$profileUser->avatar) }}" alt="{{ $profileUser->name }}" class="h-full w-full object-cover">
        @else
          {{ $initial }}
        @endif
      </div>

      <div class="flex-1 min-w-0">
        <h2 class="text-3xl md:text-4xl font-poppins text-[#0F172A]">{{ $profileUser->name }}</h2>
        <p class="text-[#64748B] text-base">{{ $profileUser->email }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
          <span class="inline-flex items-center gap-2 rounded-full bg-[#D1FAE5] px-3 py-1 text-sm font-semibold text-[#047857]"><span class="h-2 w-2 rounded-full bg-[#10B981]"></span>{{ $profileUser->is_active === false ? 'Deactivated' : 'Active account' }}</span>
          <span class="inline-flex rounded-full bg-[#F8FAFC] border border-[#E2E8F0] px-3 py-1 text-sm font-semibold text-[#475569]">{{ $profileUser->role?->name === 'admin' ? 'Platform admin' : 'Merchant user' }}</span>
        </div>
      </div>

      <a href="{{ route('logout') }}" class="text-[#BA1A1A] font-semibold self-start lg:self-center">Logout</a>
    </div>
  </section>

  <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6">
    <div class="space-y-6">
      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0]">
          <h3 class="text-2xl font-poppins text-[#0F172A]">Personal information</h3>
          <p class="text-sm text-[#64748B]">Keep your merchant account contact details current.</p>
        </div>
        <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          @csrf
          @method('PATCH')
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">Full name</span>
            <input type="text" name="name" value="{{ old('name', $profileUser->name) }}" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]">
          </label>
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">Email address</span>
            <input type="email" name="email" value="{{ old('email', $profileUser->email) }}" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]">
          </label>
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">Phone number</span>
            <input type="text" name="phone" value="{{ old('phone', $profileUser->phone) }}" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]">
          </label>
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">Profile photo</span>
            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-[#475569] file:mr-4 file:h-11 file:rounded-lg file:border-0 file:bg-[#EFF6FF] file:px-4 file:font-semibold file:text-[#0052CC]">
          </label>
          <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Save profile</button>
          </div>
        </form>
      </section>

      <section id="password" class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0]">
          <h3 class="text-2xl font-poppins text-[#0F172A]">Password</h3>
          <p class="text-sm text-[#64748B]">Use a strong password that is not shared with supplier portals or marketplaces.</p>
        </div>
        <form method="POST" action="{{ route('profile.password.update') }}" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          @csrf
          @method('PUT')
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">Current password</span>
            <input type="password" name="current_password" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]" autocomplete="current-password">
          </label>
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">New password</span>
            <input type="password" name="password" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]" autocomplete="new-password">
          </label>
          <label class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-[0.7px] text-[#64748B]">Confirm password</span>
            <input type="password" name="password_confirmation" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]" autocomplete="new-password">
          </label>
          <div class="md:col-span-3 flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A]">Change password</button>
          </div>
        </form>
      </section>
    </div>

    <aside class="space-y-6">
      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0] bg-[#F8FAFC]">
          <h3 class="text-xl font-poppins text-[#0F172A]">Store access</h3>
        </div>
        <div class="p-6 space-y-3">
          @forelse ($memberStores as $store)
            <div class="rounded-lg border border-[#E2E8F0] bg-white p-3">
              <p class="font-semibold text-[#0F172A]">{{ $store->name }}</p>
              <p class="text-sm text-[#64748B]">{{ \Illuminate\Support\Str::headline($store->pivot?->role ?? 'member') }}</p>
            </div>
          @empty
            <p class="text-sm text-[#64748B]">You are not assigned to a store yet.</p>
          @endforelse
        </div>
      </section>

      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0]">
          <h3 class="text-xl font-poppins text-[#0F172A]">Account checks</h3>
        </div>
        <div class="p-6 space-y-4 text-sm">
          <div>
            <p class="font-semibold text-[#0F172A]">Email verification</p>
            <p class="text-[#64748B]">{{ $profileUser->email_verified_at ? 'Verified '.$profileUser->email_verified_at->diffForHumans() : 'Verification is pending.' }}</p>
          </div>
          <div>
            <p class="font-semibold text-[#0F172A]">Last sign-in</p>
            <p class="text-[#64748B]">{{ $profileUser->last_login_at?->diffForHumans() ?? 'Not recorded yet' }}</p>
          </div>
          <a href="{{ route('security') }}" class="inline-flex h-10 px-4 rounded-lg bg-[#0052CC] text-white font-semibold items-center justify-center">Open security</a>
        </div>
      </section>
    </aside>
  </div>
</div>
@endsection
