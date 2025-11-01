<x-layouts.app.landingpage_sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts.app.landingpage_sidebar>
