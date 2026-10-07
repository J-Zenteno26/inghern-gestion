@props([
    "icon" => "folder",
    "title",
    "description",
])
<div class="empty-state">
    <div>
        <div class="empty-state__icon">
            <x-ui.icon :name="$icon" size="24" />
        </div>
        <h3 class="empty-state__title">{{ $title }}</h3>
        <p class="empty-state__copy">{{ $description }}</p>
        @isset($action){{ $action }}@endisset
    </div>
</div>
