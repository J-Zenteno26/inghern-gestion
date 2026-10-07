@props(["flush" => false])
<section {{ $attributes->class("ui-panel") }}>
    @isset($title)
        <header class="ui-panel__header">
            <div>
                {{ $title }}
                @isset($subtitle)
                    <div class="ui-panel__subtitle">{{ $subtitle }}</div>
                @endisset
            </div>
            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </header>
    @endisset

    <div class="ui-panel__body{{ $flush ? " ui-panel__body--flush" : "" }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <footer class="ui-panel__footer">{{ $footer }}</footer>
    @endisset
</section>
