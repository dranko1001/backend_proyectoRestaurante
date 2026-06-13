<?php

namespace Tests\Feature;

use App\Mail\MasterTwoFactorLoginMail;
use App\Models\Master\MasterUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

class MasterTwoFactorEmailTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['master'];

    private function masterWithTwoFactor(): MasterUser
    {
        config(['database.default' => 'master']);

        $user = MasterUser::query()->firstOrCreate(
            ['email' => 'master-2fa-email@local.test'],
            ['name' => 'Master 2FA Email', 'password' => 'password12345', 'activo' => true],
        );

        if (! $user->two_factor_secret) {
            app(EnableTwoFactorAuthentication::class)($user);
            $user->refresh();
        }

        if ($user->two_factor_confirmed_at === null) {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        return $user->fresh();
    }

    public function test_login_sends_two_factor_code_email(): void
    {
        Mail::fake();

        $user = $this->masterWithTwoFactor();

        $response = $this->postJson('/api/master/auth/login', [
            'email' => $user->email,
            'password' => 'password12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonPath('email_sent', true);

        Mail::assertSent(MasterTwoFactorLoginMail::class, function (MasterTwoFactorLoginMail $mail) use ($user) {
            return $mail->hasTo($user->email) && strlen($mail->code) === 6;
        });
    }

    public function test_can_resend_two_factor_email_with_challenge_token(): void
    {
        Mail::fake();

        $user = $this->masterWithTwoFactor();

        $login = $this->postJson('/api/master/auth/login', [
            'email' => $user->email,
            'password' => 'password12345',
        ]);

        $token = $login->json('challenge_token');
        $this->assertNotEmpty($token);

        Mail::assertSent(MasterTwoFactorLoginMail::class, 1);

        $resend = $this->postJson('/api/master/auth/two-factor-email', [
            'challenge_token' => $token,
        ]);

        $resend->assertOk()->assertJsonPath('email_sent', true);
        Mail::assertSent(MasterTwoFactorLoginMail::class, 2);
    }
}
