<?php

test('all themes have required keys', function () {
    $themes = config('themes');
    expect($themes)->not->toBeEmpty();
    foreach ($themes as $key => $theme) {
        expect($theme)->toHaveKeys(['gradient', 'accent', 'icon', 'background_pattern']);
    }
});

test('default theme exists', function () {
    expect(config('themes.default'))->not->toBeNull();
});
