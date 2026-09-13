<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registrationInput(array $changes = []): array
    {
        return array_merge([
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $changes);
    }

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    public function test_guest_can_view_registration_page(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertViewIs('auth.register');
    }

    public function test_registration_creates_user_and_logs_them_in(): void
    {
        $this->post('/register', $this->registrationInput())
            ->assertRedirect('/admin')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('users', 1);

        $user = User::where('email', 'yamada@example.com')->firstOrFail();

        $this->assertSame('山田太郎', $user->name);
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_missing_required_fields(): void
    {
        $this->from('/register')
            ->post('/register', [])
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'name' => 'お名前を入力してください',
                'email' => 'メールアドレスを入力してください',
                'password' => 'パスワードを入力してください',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_invalid_email(): void
    {
        $this->from('/register')
            ->post('/register', $this->registrationInput([
                'email' => 'invalid-email',
            ]))
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスはメール形式で入力してください',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'yamada@example.com']);

        $this->from('/register')
            ->post('/register', $this->registrationInput())
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'email' => 'そのメールアドレスは既に使用されています',
            ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_registration_rejects_short_password(): void
    {
        $this->from('/register')
            ->post('/register', $this->registrationInput([
                'password' => 'abc1234',
                'password_confirmation' => 'abc1234',
            ]))
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'password' => 'パスワードは8文字以上で入力してください',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_password_confirmation_mismatch(): void
    {
        $this->from('/register')
            ->post('/register', $this->registrationInput([
                'password_confirmation' => 'different-password',
            ]))
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'password' => 'パスワードと一致しません',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_user_can_log_in_with_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])
            ->assertRedirect('/admin')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'wrong-password@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'wrong-password@example.com',
                'password' => 'incorrect-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_rejects_missing_credentials(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_from_auth_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/admin');
        $this->get('/register')->assertRedirect('/admin');
    }

    public function test_logout_ends_authentication_and_blocks_admin_access(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();

        $this->get('/admin')->assertRedirect('/login');
    }
}
