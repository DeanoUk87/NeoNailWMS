<x-layouts::app :title="'Edit User'">
    @livewire('users.user-form', ['userId' => $user->id])
</x-layouts::app>
