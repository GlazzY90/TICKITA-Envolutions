<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sanctum.stateful' => [
                '127.0.0.1:8000',
            ],
        ]);

        $this->withHeader(
            'Origin',
            'http://127.0.0.1:8000'
        );
    }

    public function test_client_can_log_in(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create([
                'email' => 'client@example.com',
            ]);

        $response = $this->postJson('/api/login', [
            'email' => 'client@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.email', 'client@example.com')
            ->assertJsonPath('data.role', 'client')
            ->assertJsonPath(
                'data.organization_id',
                $organization->id
            )
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->assertAuthenticatedAs($client);
    }

    public function test_support_agent_can_log_in(): void
    {
        $agent = User::factory()
            ->supportAgent()
            ->create([
                'email' => 'agent@example.com',
            ]);

        $response = $this->postJson('/api/login', [
            'email' => 'agent@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $agent->id)
            ->assertJsonPath('data.role', 'support_agent')
            ->assertJsonPath('data.organization_id', null);

        $this->assertAuthenticatedAs($agent);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'client@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => 'client@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_email_and_password_are_required(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create([
                'email' => 'client@example.com',
            ]);

        $this->postJson('/api/login', [
            'email' => 'client@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.email', 'client@example.com')
            ->assertJsonPath('data.role', 'client')
            ->assertJsonPath(
                'data.organization_id',
                $organization->id
            )
            ->assertJsonMissingPath('data.password');
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => 'client@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        Auth::forgetGuards();

        $this->getJson('/api/me')
            ->assertUnauthorized();
    }
}
