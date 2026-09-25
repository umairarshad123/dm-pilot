<x-layouts.app title="Settings">
    <x-slot:subnav>
        <x-ui.tabs :tabs="\App\Http\Controllers\Admin\SettingsController::tabs($tab)" class="-mb-px border-b-0" />
    </x-slot:subnav>

    @include('admin.settings.tabs.'.$tab)
</x-layouts.app>
