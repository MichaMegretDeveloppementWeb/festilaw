<?php

test('the application root redirects to a locale', function () {
    $this->get('/')->assertRedirect();
});
