@php
    $roles = \TornadoEngine\Access\Models\Role::orderBy('name')->get();
    // Students until roles are chosen.
    $chosen = \App\Models\Setting::get('broker.pseudonym_roles') === null
        ? $roles->where('name', 'Student')->pluck('id')->all()
        : \TornadoEngine\Broker\Broker::pseudonymRoleIds();
    $chosen = array_map('intval', old('roles', $chosen));
@endphp

<form method="POST" action="{{ route('setup.broker') }}">
    @csrf
    <x-setup.header :title="__('Which roles get pseudonyms?')">
        {{ \TornadoEngine\Broker\Broker::about() }}
    </x-setup.header>

    <fieldset class="mt-6 px-6 sm:px-8 space-y-3">
        <legend class="sr-only">{{ __('Roles given a pseudonym') }}</legend>
        @foreach ($roles as $role)
            <label class="flex min-h-11 items-center gap-3 rounded-md border border-gray-200 dark:border-gray-700 px-3 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/40">
                <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked(in_array($role->id, $chosen, true))>
                <span class="text-gray-900 dark:text-gray-100">{{ $role->name }}</span>
            </label>
        @endforeach
        <x-input-error :messages="$errors->get('roles')" />
    </fieldset>

    <x-setup.footer class="justify-between">
        <x-setup.back :href="$back" />
        <x-primary-button>{{ __('Next') }}</x-primary-button>
    </x-setup.footer>
</form>
