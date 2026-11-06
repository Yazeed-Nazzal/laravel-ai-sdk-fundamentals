<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('shows the login form to guests', function () {
    $this->get(route('login'))->assertOk()->assertViewIs('login')->assertSee('Email address')->assertSee('Password');
});

test('signs in with valid credentials and regenerates the session', function () {
    $customer = User::factory()->create();
    $this->withSession(['url.intended' => route('order-support.show')]);
    $oldSessionId = session()->getId();

    $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'password'])
        ->assertRedirect(route('order-support.show'))->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($customer);
    expect(session()->getId())->not->toBe($oldSessionId);
});

test('rejects invalid credentials without retaining the password', function () {
    $customer = User::factory()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $customer->email, 'password' => 'incorrect-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.'])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
});

test('validates login fields', function (array $payload, array $fields) {
    $this->post(route('login.store'), $payload)->assertSessionHasErrors($fields);

    $this->assertGuest();
})->with([
    'missing' => [[], ['email', 'password']],
    'invalid email' => [['email' => 'not-an-email', 'password' => 'password'], ['email']],
    'invalid password' => [['email' => 'alice@example.test', 'password' => []], ['password']],
]);

test('redirects signed in customers away from login', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('login'))->assertRedirect(route('order-support.show'));
});

test('signs out and invalidates the session', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer)->withSession(['private_value' => 'private']);

    $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('private_value');

    $this->assertGuest();
    $this->get(route('order-support.show'))->assertRedirect(route('login'));
});

test('rate limits repeated login attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'incorrect'])
        ->assertTooManyRequests();

    $this->assertGuest();
});
