<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoints_and_auth_flow_work(): void
    {
        $this->seed();

        $this->getJson('/api/platforms')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta', 'links']);

        $registerPayload = [
            'username' => 'test_user_1',
            'email' => 'test1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'region' => 'DZ',
        ];

        $register = $this->postJson('/api/auth/register', $registerPayload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'token', 'token_type']);

        $token = $register->json('token');

        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_business_rules_block_duplicates(): void
    {
        $this->seed();

        $u1 = $this->postJson('/api/auth/register', [
            'username' => 'u1',
            'email' => 'u1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'region' => 'DZ',
        ])->json();

        $u2 = $this->postJson('/api/auth/register', [
            'username' => 'u2',
            'email' => 'u2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'gender' => 'female',
            'birth_date' => '2001-01-01',
            'region' => 'DZ',
        ])->json();

        $token1 = $u1['token'];
        $user2Id = $u2['data']['id'];

        $this->withToken($token1)
            ->postJson('/api/followers', ['following_id' => $user2Id])
            ->assertCreated();

        $this->withToken($token1)
            ->postJson('/api/followers', ['following_id' => $user2Id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withToken($token1)
            ->postJson('/api/friendships', [
                'user_two_id' => $user2Id,
                'status' => 'pending',
            ])->assertCreated();

        $this->withToken($token1)
            ->postJson('/api/friendships', [
                'user_two_id' => $user2Id,
                'status' => 'pending',
            ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

