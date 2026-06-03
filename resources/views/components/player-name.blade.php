@props(['emoji' => null, 'nickname' => ''])

<span {{ $attributes }}>@if($emoji)<span class="qz-name-emoji">{{ $emoji }}</span> @endif{{ $nickname }}</span>
