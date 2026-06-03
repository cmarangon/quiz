{{--
    Shared decoration resolver for question types that aren't theme-specific
    (ordering, geo_guesser). Renders the same background flourishes the themed
    option partials use. Science inlines its floating bubbles; the other themes
    expose a `_deco` partial. Unknown/default themes render nothing.
--}}
@php($__deco = 'themes.'.($themeKey ?? '').'._deco')
@if(($themeKey ?? null) === 'science')
    <span class="qz-bubble b1"></span><span class="qz-bubble b2"></span><span class="qz-bubble b3"></span>
@elseif(($themeKey ?? null) && view()->exists($__deco))
    @include($__deco)
@endif
