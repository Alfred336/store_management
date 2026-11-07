<?php

// Public registration is disabled. Users are created only by administrators.
// The /register route must return 404.

test('register route returns 404 when registration is disabled', function () {
    $this->get('/register')->assertStatus(404);
});

test('register post route returns 404 when registration is disabled', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(404);
});
