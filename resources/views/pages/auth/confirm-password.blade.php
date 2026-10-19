<x-layouts::auth :title="__('Confirm password')">
    <x-auth-header :title="__('Confirm your password')" :description="__('Enter your password to continue securely.')" />
    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-6">
        @csrf
        <flux:input name="password" type="password" :label="__('Password')" required autocomplete="current-password" autofocus viewable />
        <flux:button type="submit" variant="primary" class="w-full">Confirm password</flux:button>
    </form>
</x-layouts::auth>
