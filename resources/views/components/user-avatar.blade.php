@props([
    'user',
    'size' => 'sm',
])

{{-- The photo when there is one, the coloured initials when there is not. --}}
<flux:avatar
    :size="$size"
    :src="$user->avatarUrl()"
    :name="$user->name"
    :initials="$user->initials()"
    {{ $attributes }}
/>
