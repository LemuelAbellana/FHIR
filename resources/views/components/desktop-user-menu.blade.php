<div {{ $attributes }}>
    <flux:dropdown position="bottom" align="start">
        <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()" icon:trailing="chevrons-up-down" data-test="sidebar-menu-button" />
        <flux:menu>
            <div class="px-2 py-2"><flux:heading>{{ auth()->user()->name }}</flux:heading><flux:text>{{ auth()->user()->email }}</flux:text></div>
            <flux:menu.separator />
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">Log out</flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</div>
