<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head')</head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <main class="flex min-h-svh flex-col items-center justify-center gap-8 px-5 py-10">
            <x-app-logo :href="route('home')" wire:navigate />
            <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 sm:p-8">{{ $slot }}</div>
            <p class="max-w-sm text-center text-xs leading-5 text-slate-500">Local interoperability demonstration.<br>Synthetic / test data only. Never enter real patient information.</p>
        </main>
        @fluxScripts
    </body>
</html>
