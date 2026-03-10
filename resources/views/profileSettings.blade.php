@extends('layouts.sidebar')

@section('title', 'Profile Settings | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
  <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
    <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
  </button>
  <h1 class="text-2xl">Profile Settings</h1>
  <div class="flex items-center gap-3 ml-auto">
    <button class="h-10 px-4 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Save Changes</button>
    <div class="hidden md:block w-px h-6 bg-[#E2E8F0]"></div>
    <button class="hidden md:flex relative p-2 rounded-full text-[#64748B] hover:bg-gray-100" aria-label="Notifications">
      <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="currentColor"/></svg>
      <span class="absolute top-1.5 right-1.5 h-2 w-2 bg-[#EF4444] rounded-full border-2 border-white"></span>
    </button>
  </div>
</header>
@endsection

@section('content')
<div class="max-w-[1180px] mx-auto space-y-6">
  <section class="bg-white border border-[#CBD5E1] rounded-xl p-6">
    <div class="flex flex-col lg:flex-row lg:items-center gap-6">
      <div class="relative w-fit">
        <div class="h-32 w-32 rounded-full bg-[#F5D8BE] border-4 border-white shadow-md flex items-center justify-center text-[11px] text-[#64748B]">User Profile</div>
        <button class="absolute right-1 bottom-1 h-9 w-9 rounded-full bg-[#0052CC] text-white flex items-center justify-center border-2 border-white">
          <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.16667 14.6667C2.8 14.6667 2.48622 14.5362 2.22533 14.2753C1.96444 14.0144 1.83378 13.7004 1.83333 13.3333V4C1.83333 3.63333 1.964 3.31956 2.22533 3.05867C2.48667 2.79778 2.80044 2.66711 3.16667 2.66667H5.27333L6.5 1.33333H10.5L11.7267 2.66667H13.8333C14.2 2.66667 14.5138 2.79733 14.7747 3.05867C15.0356 3.32 15.1662 3.63378 15.1667 4V13.3333C15.1667 13.7 15.0362 14.0138 14.7753 14.2747C14.5144 14.5356 14.2004 14.6662 13.8333 14.6667H3.16667ZM8.5 12C9.61111 12 10.5558 11.6111 11.334 10.8333C12.1122 10.0556 12.5009 9.11089 12.5 8C12.5 6.88889 12.1111 5.94422 11.3333 5.166C10.5556 4.38778 9.61089 3.99911 8.5 4C7.38889 4 6.44422 4.38889 5.666 5.16667C4.88778 5.94444 4.49911 6.88911 4.5 8C4.5 9.11111 4.88889 10.0558 5.66667 10.834C6.44444 11.6122 7.38911 12.0009 8.5 12Z" fill="currentColor"/></svg>
        </button>
      </div>

      <div class="flex-1 min-w-0">
        <h2 class="text-4xl">Alex Rivers</h2>
        <p class="text-[#64748B] text-2xl">Enterprise Administrator</p>
        <div class="mt-4 flex flex-wrap gap-3">
          <span class="inline-flex items-center gap-2 rounded-full bg-[#D1FAE5] px-3 py-1 text-sm font-semibold text-[#059669]"><span class="h-2 w-2 rounded-full bg-[#10B981]"></span>Active Now</span>
          <span class="inline-flex items-center rounded-full bg-[#E2E8F0] px-3 py-1 text-sm font-semibold uppercase tracking-[0.5px] text-[#475569]">ALEX.RIVERS@COMPANY.COM</span>
        </div>
      </div>

      <button class="text-[#E11D48] font-semibold flex items-center gap-2 self-start lg:self-center">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.3333 14.6667V13.3333H13.3333V2.66667H11.3333V1.33333H13.3333C13.7 1.33333 14.0138 1.464 14.2747 1.72533C14.5356 1.98667 14.6662 2.30044 14.6667 2.66667V13.3333C14.6667 13.7 14.5362 14.0138 14.2753 14.2747C14.0144 14.5356 13.7004 14.6662 13.3333 14.6667H11.3333ZM9.33333 12L8.4 11.0333L10.7667 8.66667H1.33333V7.33333H10.7667L8.4 4.96667L9.33333 4L13.3333 8L9.33333 12Z" fill="currentColor"/></svg>
        <span>Logout Account</span>
      </button>
    </div>
  </section>

  <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-6">
    <div class="space-y-6">
      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0]"><h3 class="text-2xl">Personal Information</h3></div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="space-y-2"><span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Full Name</span><input type="text" value="Alex Rivers" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]" /></label>
          <label class="space-y-2"><span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Email Address</span><input type="email" value="alex.rivers@company.com" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]" /></label>
          <label class="space-y-2"><span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Phone Number</span><input type="text" value="+1 (555) 000-1234" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]" /></label>
          <label class="space-y-2"><span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Language Preference</span><select class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#1E293B]"><option>English (US)</option></select></label>
        </div>
      </section>

      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0]"><h3 class="text-2xl">Notification Preferences</h3></div>
        <div class="p-6 space-y-6">
          <div class="flex items-start justify-between gap-4"><div><p class="text-lg font-semibold">System-wide Alerts</p><p class="text-[#64748B]">Critical platform status and performance updates.</p></div><button class="h-8 w-14 rounded-full bg-[#0052CC] p-1 flex justify-end"><span class="h-6 w-6 rounded-full bg-white"></span></button></div>
          <div class="flex items-start justify-between gap-4"><div><p class="text-lg font-semibold">Marketing & Features</p><p class="text-[#64748B]">New product features and occasional newsletters.</p></div><button class="h-8 w-14 rounded-full bg-[#E2E8F0] p-1 flex justify-start"><span class="h-6 w-6 rounded-full bg-white border border-[#D1D5DB]"></span></button></div>
          <div class="flex items-start justify-between gap-4"><div><p class="text-lg font-semibold">Security Notifications</p><p class="text-[#64748B]">Alerts for new logins and password changes.</p></div><button class="h-8 w-14 rounded-full bg-[#0052CC] p-1 flex justify-end"><span class="h-6 w-6 rounded-full bg-white"></span></button></div>
        </div>
      </section>
    </div>

    <div class="space-y-6">
      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0] bg-[#F8FAFC]/50"><h3 class="text-xl">Role & Permissions</h3></div>
        <div class="p-6 space-y-6">
          <div class="flex items-center gap-3"><div class="h-9 w-9 rounded-lg bg-[#0052CC]/10 text-[#0052CC] flex items-center justify-center"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18C6.4 17.3333 4.25 15.8417 2.55 13.525C0.85 11.2083 0 8.63333 0 5.8V2.4L9 0L18 2.4V5.8C18 8.63333 17.15 11.2083 15.45 13.525C13.75 15.8417 11.6 17.3333 9 18ZM8 12.4L13.65 6.75L12.225 5.325L8 9.55L5.775 7.325L4.35 8.75L8 12.4Z" fill="currentColor"/></svg></div><div><p class="font-semibold">Enterprise Admin</p><p class="text-[#64748B] text-sm">Full platform access</p></div></div>
          <div class="space-y-2"><p class="text-[10px] font-bold uppercase tracking-[1px] text-[#94A3B8]">Managed Stores</p><div class="p-2 bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg flex items-center gap-2"><span class="h-6 w-6 rounded bg-[#DBEAFE] text-[#1D4ED8] text-[10px] font-bold flex items-center justify-center">US</span><span class="text-sm">Main Store US</span></div><div class="p-2 bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg flex items-center gap-2"><span class="h-6 w-6 rounded bg-[#F3E8FF] text-[#7E22CE] text-[10px] font-bold flex items-center justify-center">EU</span><span class="text-sm">Global Distribution EU</span></div><div class="p-2 bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg flex items-center gap-2"><span class="h-6 w-6 rounded bg-[#FEF3C7] text-[#B45309] text-[10px] font-bold flex items-center justify-center">B2</span><span class="text-sm">B2B Wholesale Portal</span></div></div>
        </div>
      </section>

      <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
        <div class="p-6 border-b border-[#E2E8F0]"><h3 class="text-xl">Connected Accounts</h3></div>
        <div class="p-6 space-y-4">
          <div class="p-3 rounded-lg border border-[#F1F5F9] flex items-center justify-between"><div class="flex items-center gap-3"><div class="h-8 w-8 bg-[#F8FAFC] rounded flex items-center justify-center text-sm">G</div><span class="font-medium">Google</span></div><span class="px-2 py-1 rounded bg-[#ECFDF5] text-[#059669] text-[10px] font-bold uppercase">Connected</span></div>
          <div class="p-3 rounded-lg border border-[#F1F5F9] flex items-center justify-between opacity-70"><div class="flex items-center gap-3"><div class="h-8 w-8 bg-[#F8FAFC] rounded flex items-center justify-center text-sm">M</div><span class="font-medium">Microsoft</span></div><button class="text-[#0052CC] text-[10px] font-bold uppercase">Link Account</button></div>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
