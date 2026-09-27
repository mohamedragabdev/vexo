# Vexo

Vexo is a Laravel-based messaging API with user registration, login, conversation listing, and message sending. The current implementation exposes a versioned REST API under `/api/v1` and uses Sanctum personal access tokens for authenticated requests. The project also includes a Reverb chat channel configuration for real-time private messaging, though the live broadcast flow is only partially wired into the application.

# Current Status

This repository reflects the current implementation as it exists today. It is a working prototype that is intended to be extended in the future, rather than a complete production-ready chat platform.

# Technology Stack

- PHP: ^8.3
- Laravel: ^13.17
- Application database driver: MySQL (`DB_CONNECTION=mysql` in `.env`, database `vexo`)
- Test database driver: MySQL (`DB_CONNECTION=mysql` in `.env.testing` and `phpunit.xml`, database `vexo-test`)
- Authentication: Laravel Sanctum with personal access tokens
- Real-time layer: Laravel Reverb with private channel broadcasting
- Billing package: Laravel Cashier with Stripe support configured in the project but not used by the API routes in the current codebase
- Testing: Pest + PHPUnit
- Frontend: Vite asset pipeline with a simple chat view route under `/api/v1/chat`

# Architecture

The application follows a standard Laravel API layout. Route definitions live in `routes/api.php`, while the web-facing entry point is minimal and serves the default Laravel welcome page from `routes/web.php`.

The main application flow is:

1. A user registers through `RegisterController`.
2. A user logs in through `AuthenticatedController` using a phone number and password.
3. Authenticated requests are protected by the `auth:sanctum` middleware.
4. Conversation and message requests are handled by `ConversationController` and `MessageController`.
5. Messages trigger `MessageSent`, which broadcasts to a private channel named `chat.{receiver_id}`.

# Project Structure

- `app/Http/Controllers`: API controllers for authentication, conversations, and messages
- `app/Http/Requests`: form request validation for login, registration, and message updates
- `app/Http/Resources`: API serialization layer, specifically `ConversationResource`
- `app/Models`: Eloquent models for `User`, `Conversation`, and `Message`
- `app/Policies`: `UserPolicy` for simple user-only authorization checks
- `app/Events`: broadcast event for sent messages
- `app/Listeners`: placeholder listener for message events
- `app/Service`: `PhoneNormalizeService` for phone number normalization
- `database/migrations`: schema definition for users, conversations, messages, Sanctum tokens, and billing-related tables
- `routes`: API, web, and channel definitions
- `tests`: existing and newly added Pest tests

# Database

The database schema currently includes the following application tables and supporting tables:

## users

- Purpose: stores the application users.
- Important columns: `id` (UUID string primary key), `name`, `phone`, `password`, `remember_token`, timestamps.
- Primary key: `id`
- Unique constraints: `phone` is unique.
- Indexes: `phone` is indexed via `idx_phone`.
- Relationships: each user may be a sender or receiver in multiple conversations and messages.

## conversations

- Purpose: tracks a conversation between two users.
- Important columns: `id`, `sender_id`, `receiver_id`, timestamps.
- Primary key: `id`
- Foreign keys: `sender_id` and `receiver_id` both reference `users` with cascade-on-delete.
- Relationships: `belongsTo(User, 'sender_id')`, `belongsTo(User, 'receiver_id')`, `hasMany(Message)`.

## messages

- Purpose: stores individual chat messages within a conversation.
- Important columns: `id`, `sender_id`, `receiver_id`, `conversation_id`, `message`, `status`, timestamps.
- Primary key: `id`
- Foreign keys: `sender_id`, `receiver_id`, `conversation_id` all reference their parent records and cascade on delete.
- Constraints: `status` is an enum with values `send`, `received`, and `seen` and defaults to `send`.
- Relationships: `belongsTo(User, 'sender_id')`, `belongsTo(User, 'receiver_id')`, `belongsTo(Conversation)`.

## personal_access_tokens

- Purpose: stores Sanctum personal access tokens.
- Important columns: `id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, timestamps.
- Primary key: `id`

## subscriptions and subscription_items

- Purpose: billing tables added by Cashier/Stripe integration.
- Important columns: include `user_id`, `stripe_id`, `stripe_status`, `stripe_price`, and related pricing metadata.
- Relationships: not currently used by the API routes in this codebase.

# Models & Relationships

## User

- `hasMany(Conversation, 'sender_id')` via `senderConversations()`
- `hasMany(Conversation, 'receiver_id')` via `receiverConversations()`
- `hasMany(Message, 'sender_id')` via `senderMessages()`
- `hasMany(Message, 'receiver_id')` via `receiverMessages()`
- Uses `HasApiTokens` and `Authenticatable` from Laravel Sanctum.

## Conversation

- `belongsTo(User, 'sender_id')`
- `belongsTo(User, 'receiver_id')`
- `hasMany(Message)`

## Message

- `belongsTo(User, 'sender_id')`
- `belongsTo(User, 'receiver_id')`
- `belongsTo(Conversation)`

# Authentication

Authentication is implemented with Laravel Sanctum.

- Registration is public: `POST /api/v1/register`.
- Login is public: `POST /api/v1/login`.
- Validation normalizes the incoming phone number using `PhoneNormalizeService` before checking it against `users.phone`.
- A successful login deletes any existing personal tokens for the user and creates a new token named `auth_personal`.
- The response includes `access_token`, `token_type`, and the authenticated user record.
- Protected endpoints are under the `auth:sanctum` middleware group in `routes/api.php`.
- `logout` uses the current access token and deletes it.

# Authorization

The project contains a `UserPolicy` with basic same-user checks:

- `view(User $user, User $model)` allows access only when `$user->id === $model->id`
- `update(User $user, User $model)` allows access only when `$user->id === $model->id`
- `delete(User $user, User $model)` allows access only when `$user->id === $model->id`

The current API behavior is mixed:

- `ConversationController::index()` authorizes the authenticated user and then queries sender-owned conversations only.
- `ConversationController::show()` authorizes the authenticated user and then looks up the conversation within `$user->senderConversations()` before marking messages as seen.
- `ConversationController::destroy()` authorizes the authenticated user, but then deletes a conversation by ID without confirming that the conversation belongs to the current user.
- `MessageController::update()` and `MessageController::destroy()` call `authorize('update', $user)` and `authorize('delete', $user)` respectively, but then fetch the message by ID directly rather than enforcing ownership against the message record itself.

In other words, the policy exists, but the app does not consistently enforce resource ownership for conversations or messages before mutating them by ID.

# API

## POST /api/v1/register

- Authentication: none
- Purpose: create a user account if the phone number is not already registered
- Validation: `name` optional string, `phone` required unique phone matching `+20...` format, `password` required 8-20 chars
- Response: `201 Created` with a success message, the created user, and a generated OTP integer
- Intended controller behavior: the controller checks for an existing phone and returns `409 Conflict` with a “You are registered already, please login” message.
- Actual observed validation behavior: in the current project, the `unique:users,phone` validation rule fires first, so a duplicate registration currently returns `422 Unprocessable Entity` before the manual conflict branch is reached.

## POST /api/v1/login

- Authentication: none
- Purpose: log in an existing user using a normalized phone number and password
- Validation: phone required, string, existing user, regex for Egyptian mobile format; password required
- Response: `200 OK` with `access_token`, `token_type`, and user data
- Errors: returns a JSON message when the phone or password is invalid

## POST /api/v1/send-otp

- Authentication: none
- Purpose: generates a random six-digit number and returns it directly
- Validation: none
- Notes: the returned OTP is not stored or verified against any user action in the current codebase

## POST /api/v1/logout

- Authentication: `auth:sanctum`
- Purpose: revoke the current personal access token
- Response: JSON success message

## GET /api/v1/conversation

- Authentication: `auth:sanctum`
- Purpose: list conversations belonging to the authenticated user
- Behavior: loads conversations where the user is the sender, eager loads the receiver relationship, and serializes them with `ConversationResource`
- Response: array of conversation objects

## GET /api/v1/conversation/{id}

- Authentication: `auth:sanctum`
- Purpose: view one conversation for the authenticated user
- Behavior: finds the conversation only among the authenticated user’s sender conversations, marks all messages in that conversation as `seen`, and returns the serialized conversation
- Response: conversation resource with a nested `messages` collection

## DELETE /api/v1/conversation/{id}

- Authentication: `auth:sanctum`
- Purpose: delete a conversation by ID
- Behavior: calls `Conversation::findOrFail($conversation)->delete()` without checking whether the user owns it

## POST /api/v1/send-message

- Authentication: `auth:sanctum`
- Purpose: send a message to a recipient phone number
- Request body: `conversation_id` optional, `receiver_phone` required, `message` required
- Behavior: the controller reads the request values directly, resolves the target user with `User::where('phone', $request->receiver_phone)->first()`, and then either reuses the matching conversation or creates a new one. There is no dedicated Form Request for this endpoint in the current codebase.
- Validation note: the endpoint does not use a separate request class to validate `receiver_phone` before the controller runs; the controller simply performs a lookup on that value. The field is expected by the controller but not independently enforced by a dedicated validation layer in this route.
- Response: JSON success message and the created message model
- Real-time behavior: dispatches `MessageSent` event to `chat.{receiver_id}`

## PUT /api/v1/update-message/{id}

- Authentication: `auth:sanctum`
- Purpose: update the text for a message
- Validation: `message` required string between 1 and 500 characters
- Behavior: loads the message by ID and updates it without checking message ownership

## DELETE /api/v1/delete-message/{id}

- Authentication: `auth:sanctum`
- Purpose: delete a message by ID
- Behavior: loads the message by ID and deletes it without checking ownership

# Chat System

The chat system is centered on a simple conversation model between two users.

- A conversation contains `sender_id` and `receiver_id`.
- Message creation is done with a sender and receiver relationship on each message.
- A message stores `status` as `send`, `received`, or `seen`.
- When a conversation is opened, the controller updates all messages in that conversation to `seen`.
- Private broadcasting is partially implemented: `routes/channels.php` defines the channel authorization for `chat.{receiver_id}`, and the `MessageSent` event implements `ShouldBroadcastNow` for that private channel.
- The event listener `SendMessage` is present but remains a stub and does not handle the event in the current code.
- The project includes private-channel authorization logic for broadcasting, but it does not include a complete end-to-end realtime client or a fully implemented listener pipeline.
- There is no participant table, no read-by-user tracking, no pagination, and no message edit or delete history model.

# Testing

The complete test suite was run with `php artisan test --compact`. The verified result was:

- Tests: 5
- Passed: 4
- Failed: 1
- Skipped: 0
- Assertions: 13
- Duration: 2614 ms

The passing tests cover:

- Login with a normalized phone number
- Authenticated conversation listing
- Rejection of logout by an unauthenticated user
- The unit example test, `that true is true`

The duplicate-registration test, `P\Tests\Feature\Auth\ChatApiFlowTest::__pest_evaluable_duplicate__registration__returns__conflict__response`, creates a user with phone `+201012345678` and attempts registration with the same number in local form, `01012345678`. It intentionally exercises duplicate phone-number registration. The test expects HTTP `409 Conflict` and the message `You are registered already, please login`.

The current application instead returns HTTP `422 Unprocessable Entity`: the `unique:users,phone` validation rejects the duplicate before the controller's duplicate-user conflict branch can run. The test setup is valid; `RefreshDatabase` is not the cause. This expected-versus-actual difference is a known behavior discrepancy and has not been fixed. The duplicate-registration test remains failing and has not been changed or disabled.

The project does not currently provide broad coverage for conversation authorization, message ownership, validation edge cases, or broadcasting behavior.

# Known Issues / Observations

1. The route prefix is `/api/v1`, but the existing test suite still references `/api/register`; this indicates a mismatch between the current API version and the older test expectation.
2. `ConversationController` and `MessageController` call the policy methods with the authenticated user, but the code does not consistently enforce ownership before deleting or updating records by ID.
3. The `UserPolicy` is not enforced on conversations or messages at the model level; it is effectively a same-user identity check.
4. `sendOTP` returns a random integer but does not persist it, verify it, or associate it with a user action.
5. The controller intends to return `409 Conflict` for a duplicate phone number, but the current observed behavior is `422 Unprocessable Entity` because the request validation triggers `unique:users,phone` before the manual duplicate check is reached.
6. `MessageController::store()` resolves the receiver by phone in the controller and creates or reuses a conversation without a dedicated request validator for this endpoint.
7. The `UserFactory` defines an `email` property even though the `users` table does not include an `email` column; this may be a stale or incomplete factory definition and should be treated as a potential observation rather than a confirmed production bug.
8. `apiResource('/conversation', ConversationController::class)` generates a `store` route even though the controller does not implement `store()`, resulting in a missing method if that route is hit.
9. The billing-related migrations and Cashier configuration are present, but there is no active subscription or Stripe checkout flow wired into the current controller layer.
10. The `SendMessage` listener exists but does not handle the event, so the listener remains a stub rather than a fully implemented broadcast consumer.




# Future Extension Ideas

The following ideas fit the current architecture without inventing features the app does not already contain:

- Real-time messaging UI improvements with a front-end client using the private Reverb channels
- Conversation pagination and message pagination
- Message read status tracking beyond the current `seen` flag
- Conversation search or filtering by participant
- Message history control such as deletion or editing with ownership enforcement
- Better policy checks for conversation and message ownership
- OTP verification flow tied to a persisted or validated code
- Subscription or Stripe billing integration if the Cashier setup is expanded into the app

These are future ideas only and are not part of the current implementation.



# Current Status

**🚧 Under Development**

Vexo is currently under active development. The existing implementation represents a working prototype of the messaging API, but several parts of the system are still being developed and refined.

The project is **not production-ready** yet. Authentication, conversations, messaging, authorization, testing, and real-time communication are currently being expanded and improved.

Existing known issues and incomplete features are documented in this file and are intentionally preserved until they are addressed during development.