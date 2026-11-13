<x-setup-layout wide :back="route('setup')">
    <div class="mt-8 overflow-hidden bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        @include('docs.dns-setup')
    </div>
</x-setup-layout>
