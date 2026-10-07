@props([
    "eyebrow",
    "title",
    "description" => null,
])
<header class="page-header">
    <div>
        <div class="page-header__eyebrow">{{ $eyebrow }}</div>
        <h1 class="page-header__title">{{ $title }}</h1>
        @if ($description)
            <p class="page-header__description">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="page-header__actions">{{ $actions }}</div>
    @endisset
</header>
