<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage who can access the app')">
        <div class="my-6 w-full space-y-6">
            {{-- Populated in later tasks --}}
        </div>
    </x-pages::settings.layout>
</section>
