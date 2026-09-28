<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('registered_user_can_login_with_normalized_phone', function () {
    User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Ahmed',
        'phone' => '+201012345678',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'phone' => '01012345678',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.phone', '+201012345678')
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'user' => ['id', 'name', 'phone'],
        ]);
});

test('duplicate_registration_returns_conflict_response', function () {
    User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Ahmed',
        'phone' => '+201012345678',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ahmed',
        'phone' => '01012345678',
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('message', 'The phone has already been taken.');
});

test('authenticated_user_can_list_their_conversations', function () {
    $user = User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Alice',
        'phone' => '+201001112222',
        'password' => bcrypt('password123'),
    ]);

    $otherUser = User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Bob',
        'phone' => '+201003334444',
        'password' => bcrypt('password123'),
    ]);

    $conversation = Conversation::create([
        'sender_id' => $user->id,
        'receiver_id' => $otherUser->id,
    ]);

    Message::create([
        'sender_id' => $user->id,
        'receiver_id' => $otherUser->id,
        'conversation_id' => $conversation->id,
        'message' => 'hello world',
        'status' => 'send',
    ]);

    $token = $user->createToken('chat-test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/conversation')
        ->assertOk()
        ->assertJsonFragment(['conversation_name' => 'Bob']);
});

test('unauthenticated_user_cannot_logout', function () {
    $this->postJson('/api/v1/logout')
        ->assertUnauthorized();
});
