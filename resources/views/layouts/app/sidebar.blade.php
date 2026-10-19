<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head')</head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-white focus:p-3">Skip to main content</a>
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200 bg-white">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route('dashboard')" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>
            <div class="px-3 py-5"><span class="demo-badge">Synthetic test environment</span></div>
            <flux:sidebar.nav aria-label="Main navigation">
                <flux:sidebar.group heading="Workspace" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('patients.index')" :current="request()->routeIs('patients.*')" wire:navigate>Patients</flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('audit-logs.index')" :current="request()->routeIs('audit-logs.*')" wire:navigate>Audit Logs</flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
            <flux:spacer />
            <div class="px-3 py-5 text-xs leading-5 text-slate-500">FHIR R4 interoperability demo<br>Laravel · Livewire · HTTPS</div>
            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>
        <flux:header class="border-b border-slate-200 bg-white lg:hidden">
            <flux:sidebar.toggle icon="bars-2" inset="left" aria-label="Open navigation" />
            <flux:spacer />
            <x-desktop-user-menu />
        </flux:header>
        {{ $slot }}
        @fluxScripts
    </body>
</html>
