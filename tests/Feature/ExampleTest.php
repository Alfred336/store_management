<?php

test('the home page redirects visitors to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Log in to your account');
});
