<?php

test('the application root serves the home page', function () {
    $this->get('/')->assertOk();
});
