<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="main-content" class="min-w-0 !p-5 sm:!p-8 lg:!p-10">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
