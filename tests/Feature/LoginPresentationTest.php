<?php

namespace Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class LoginPresentationTest extends TestCase
{
    public function test_login_keeps_accessible_native_authentication_form(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('class="rk-login"', false)
            ->assertSee('method="POST" action="'.route('login.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('for="email"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('for="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('type="submit"', false)
            ->assertSee(route('password.request'), false)
            ->assertSee('aria-controls="password" aria-pressed="false"', false)
            ->assertSee('ILUSTRASI ALUR LAYANAN')
            ->assertSee('Pesanan tercatat')
            ->assertSee('Keluhan tertangani')
            ->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_login_retains_old_email_and_associates_validation_errors(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'email' => ['Alamat email perlu diperiksa.'],
            'password' => ['Kata sandi wajib diisi.'],
        ]));

        $this->withSession([
            '_old_input' => ['email' => 'tim@example.com', 'remember' => 'on'],
            'errors' => $errors,
            'status' => 'Kata sandi berhasil diperbarui.',
        ])->get('/login')->assertOk()
            ->assertSee('value="tim@example.com"', false)
            ->assertSee('name="remember" checked', false)
            ->assertSee('aria-invalid="true" aria-describedby="email-error"', false)
            ->assertSee('id="email-error"', false)
            ->assertSee('aria-invalid="true" aria-describedby="password-error"', false)
            ->assertSee('id="password-error"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('role="status"', false)
            ->assertSee('Kata sandi berhasil diperbarui.');
    }
}
