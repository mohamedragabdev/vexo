# Vexo

## Overview

Vexo is an in-development Laravel backend for phone-number-based messaging. It provides registration and login, Sanctum bearer-token authentication, conversation and message operations, and an initial private-channel broadcasting path. The repository also contains a minimal Blade chat view and Cashier/Stripe schema and configuration, but does not implement a billing workflow.

## Project Status

Vexo is a prototype and is **not production-ready**. Core account and chat endpoints exist, but message creation is not validated, delete-message currently has a runtime defect, generated conversation routes target missing controller methods, and the real-time client depends on a hard-coded user ID. Review the limitations below before exposing the API to users.

### Implemented

- Phone-based registration and login with Egyptian mobile-number normalization.
- Sanctum personal access tokens and authenticated API routes.
- Conversation listing, participant-scoped retrieval/deletion, message status updates when opening a conversation, and message creation/update logic.
- A private broadcast event for newly sent messages, with channel authorization.
- Login, OTP, and send-message rate limits.

### Partial or not implemented

- OTP generation is not persisted, delivered, or verified.
- Conversation resource routes include `store` and `update` actions that have no corresponding controller methods.
- Message deletion reaches an invalid variable reference and does not complete successfully.
- Cashier tables and configuration are present, but no application billing flow is implemented.
- The broadcast channel and client are wired, but the chat view uses a fixed user ID and the application default broadcaster is not set to Reverb by `.env.example`.

## Features

- Public registration and login using phone numbers and passwords.
- Authentication via Sanctum personal access tokens.
- Conversation listing and participant-restricted conversation access.
- Message sending, sender-only message update logic, and `send`/`received`/`seen` status values.
- A private `chat.{receiver_id}` channel and `message.sent` broadcast event.
- A minimal authenticated `/api/v1/chat` Blade view.

## Technology Stack

- PHP `^8.3` (the project has been run with PHP 8.5).
- Laravel `^13.17` (installed version verified as 13.32.0).
- Laravel Sanctum `^4.0` for personal access tokens.
- Laravel Reverb `^1.11` and Laravel Echo/Pusher JS packages for the configured broadcast path.
- Laravel Cashier `^16.8` for Stripe-related schema/configuration only.
- Pest `^5.2` with PHPUnit.
- Vite 8.
- Database configuration is environment-driven. `.env.example` selects MySQL; the PHPUnit test configuration uses MySQL database `vexo-test`.

## Architecture Overview

The application follows Laravel's standard HTTP structure:

- `routes/api.php` defines `/api/v1` endpoints and groups protected operations behind `auth:sanctum`.
- Authentication controllers use Form Requests and `PhoneNormalizeService`.
- `ConversationController` and `MessageController` implement chat operations using Eloquent models.
- `ConversationResource` shapes conversation responses.
- Policies express participant/owner checks for conversations and messages.
- `MessageSent` broadcasts synchronously to a private receiver channel. `SendMessage` exists as an empty listener and is not the broadcast implementation.
- `routes/web.php` serves Laravel's welcome page at `/`; the application also registers Laravel's `/up` health route.

## Database Design

The migrations define these principal tables:

| Table | Purpose and relevant fields |
| --- | --- |
| `users` | String primary key (UUID values are created at registration), nullable `name`, unique `phone`, password, remember token, timestamps. |
| `conversations` | Auto-incrementing ID, `sender_id` and `receiver_id` foreign keys to users, timestamps. Deleting either user cascades to the conversation. |
| `messages` | Auto-incrementing ID, sender/receiver user foreign keys, conversation foreign key, text body, status enum (`send`, `received`, `seen`, default `send`), timestamps. Parent deletion cascades. |
| `personal_access_tokens` | Sanctum token records with UUID morph columns, token hash, abilities, last-used and optional expiry metadata. |
| `subscriptions`, `subscription_items` | Cashier/Stripe subscription metadata. No application subscription workflow is connected. |
| `jobs`, `job_batches`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens` | Laravel infrastructure tables. |

`User` has many conversations through `sender()` and `receiver()`, and messages through `senderMessages()` and `receiverMessages()`. `Conversation` belongs to a sender and receiver and has many messages. `Message` belongs to its sender, receiver, and conversation. There is no separate participant table or uniqueness constraint preventing duplicate conversations between the same users.

## Authentication

Registration and login are public. Login validates the normalized phone against the users table and checks the password through Laravel authentication. A successful login deletes the user's existing personal access tokens before creating a token named `auth_personal`; logging in therefore invalidates tokens from other sessions/devices. Protected API routes require `Authorization: Bearer <token>` through `auth:sanctum`. Logout deletes the current access token.

Phone normalization removes non-digits, prefixes local numbers beginning with `0` with `+2`, and prefixes numbers beginning with `20` with `+`. Registration and login then validate the `+20` mobile-number pattern for prefixes `10`, `11`, `12`, or `15`.

## Authorization and User Ownership

- Conversation listing queries conversations where the authenticated user is either sender or receiver.
- Conversation retrieval and deletion are scoped to either conversation participant; the policy methods also allow either participant.
- Opening a conversation changes messages addressed to the current user to `seen`.
- Message update queries only messages sent by the current user and the policy checks `sender_id` again.
- Message deletion also scopes its initial lookup to the sender, but the controller currently passes an invalid variable reference to `authorize()` and fails before deletion.
- Message creation does not verify that a supplied `conversation_id` belongs to the authenticated user or matches the supplied receiver. This can permit writing into another conversation if its ID is known, and is a BOLA/data-integrity risk.
- The `UserPolicy` is defined but is not used by the current API controllers.

## API Documentation

All application API routes are under `/api/v1`, except Laravel's broadcast authorization endpoint at `/api/broadcasting/auth`. Responses are JSON for API requests, except `/api/v1/chat`, which returns an HTML view. Validation errors use Laravel's standard response format rather than a project-specific error envelope.

### Web and Health Routes

- `GET /` renders Laravel's default welcome view.
- `GET /up` is Laravel's configured health endpoint.

### API Endpoints

| Method | URL | Authentication | Behavior |
| --- | --- | --- | --- |
| `POST` | `/api/v1/register` | None | Validate and create an account; returns an OTP value but does not verify it. |
| `POST` | `/api/v1/login` | None; 5 requests/minute | Authenticate by phone/password and return a bearer token. |
| `POST` | `/api/v1/send-otp` | None; custom OTP limiter | Return a generated six-digit number directly; it is not stored or sent. |
| `POST` | `/api/v1/logout` | Sanctum | Delete the current token. |
| `GET` | `/api/v1/conversation` | Sanctum | List conversations involving the authenticated user. |
| `GET` | `/api/v1/conversation/{conversation}` | Sanctum | Return a participant's conversation and mark messages addressed to them as seen. |
| `DELETE` | `/api/v1/conversation/{conversation}` | Sanctum | Delete a participant's conversation and its cascading messages. |
| `POST` | `/api/v1/send-message` | Sanctum; 100 requests/minute | Create a message in the supplied conversation or create a new conversation. |
| `PUT` | `/api/v1/update-message/{id}` | Sanctum; 100 requests/minute | Update a message authored by the current user. |
| `DELETE` | `/api/v1/delete-message/{id}` | Sanctum; 100 requests/minute | Intended to delete a message authored by the current user; currently fails due to a controller defect. |
| `GET` | `/api/v1/chat` | Sanctum | Render the minimal chat Blade view. |
| `GET\|POST\|HEAD` | `/api/broadcasting/auth` | Sanctum | Laravel's broadcast authorization endpoint for private channels. Echo uses `POST`. |

`Route::apiResource('/conversation', ...)` also registers `POST /api/v1/conversation` and `PUT|PATCH /api/v1/conversation/{conversation}`. The controller does not define `store()` or `update()`, so these routes are registered but unsupported and should not be treated as functioning endpoints.

### Endpoint Details and Examples

#### `POST /api/v1/register`

Request body:

```json
{
  "name": "Ahmed",
  "phone": "01012345678",
  "password": "password123"
}
```

`name` is optional, a string up to 50 characters. `phone` is required, normalized, unique, and must match the configured Egyptian mobile pattern. `password` is required and 8-20 characters. On success the endpoint returns `201` with `message`, `user`, and a generated integer `otp`. Duplicate normalized phone values fail the Form Request's `unique` validation with `422` before the controller's `409` branch can run; the active feature test verifies this validation response.

#### `POST /api/v1/login`

Request body:

```json
{
  "phone": "01012345678",
  "password": "password123"
}
```

On success, `200` returns `access_token`, `token_type` (`Bearer`), and `user`. Invalid request fields return `422`. A valid registered phone with a wrong password returns a JSON `message`, but the controller does not set an error status and therefore responds with `200`. The route is limited to 5 requests per minute.

#### `POST /api/v1/send-otp`

No body fields are validated. The response is the generated six-digit number itself, not an envelope. The code neither persists nor verifies nor delivers the value. The custom limiter permits up to 3 requests per minute per supplied phone key and 5 per minute per IP; exceeding a limit returns `429`.

#### `POST /api/v1/logout`

Requires a Sanctum bearer token. Success is `200`:

```json
{
  "message": "success logout"
}
```

An unauthenticated request is rejected with `401`.

#### `GET /api/v1/conversation`

Requires Sanctum authentication. Success returns a Laravel resource collection under the `data` key. Each item includes `conversation`, `sender_id`, `receiver_id`, `conversation_name`, `receiver_number`, and `messages`. Conversations are ordered newest first. The resource always uses the conversation's `receiver` for the displayed name and phone, including when the authenticated user is the receiver.

#### `GET /api/v1/conversation/{conversation}`

Requires Sanctum authentication and participation in the conversation. Success returns one conversation resource under the `data` key with the fields above. Messages in that conversation whose `receiver_id` is the authenticated user and whose status is not already `seen` are updated to `seen`. Nonexistent or non-participant conversations return `404` due to the participant-scoped lookup.

#### `DELETE /api/v1/conversation/{conversation}`

Requires Sanctum authentication and participation. Success returns `200` with `{"message":"success deleted"}`. A conversation and its messages are removed via database cascade. A non-participant or nonexistent ID returns `404`.

#### `POST /api/v1/send-message`

Requires Sanctum authentication; limited to 100 requests per minute. The controller reads `conversation_id`, `receiver_phone`, and `message` directly from the request. These inputs have no Form Request or explicit validation. The response on the successful path is `200` with `message` (`message sent successfully`) and `model` (the created message). A `MessageSent` event is dispatched.

Example request for a new conversation (the receiver phone must already exist exactly as stored):

```json
{
  "receiver_phone": "+201003334444",
  "message": "Hello"
}
```

The successful response has this shape; IDs and timestamps depend on the inserted records:

```json
{
  "message": "message sent successfully",
  "model": {
    "sender_id": "<authenticated-user-id>",
    "receiver_id": "<receiver-user-id>",
    "conversation_id": 1,
    "message": "Hello",
    "id": 1,
    "created_at": "<timestamp>",
    "updated_at": "<timestamp>"
  }
}
```

The controller looks up the receiver by exact phone value. If a conversation ID resolves, it uses that conversation without checking the sender/receiver participants. If it does not resolve, it creates a conversation with the authenticated user and resolved receiver. Missing/unknown receiver data can therefore cause an exception rather than a controlled validation response. Ensure any test request uses a real registered receiver and valid message fields; no stable error contract is defined for invalid input.

#### `PUT /api/v1/update-message/{id}`

Requires Sanctum authentication and a message sent by the current user. Request body:

```json
{
  "message": "Updated message text"
}
```

The message must be a string of 1-500 characters. Success returns `200` with `message` (`Successfully updated`) and `content` containing the updated message. Invalid body data is rejected with `422`; a missing or non-owned message is not found through the sender-scoped query (`404`).

#### `DELETE /api/v1/delete-message/{id}`

Requires Sanctum authentication and initially looks up only a message sent by the current user. The controller then calls authorization with `$$message` instead of `$message`, which is an invalid reference; deletion does not reach the delete call. No successful response should be relied upon until this is corrected. A missing or non-owned message is returned as `404` by the scoped lookup.

#### `GET /api/v1/chat`

Requires Sanctum authentication and returns an HTML Blade view rather than a JSON API response. The view contains a fixed chat user ID and renders an incoming event's message text; it is a development stub, not a multi-user chat interface.

#### `GET|POST|HEAD /api/broadcasting/auth`

Laravel's broadcast authorization route is registered with `api` and `auth:sanctum` middleware. It authorizes `chat.{receiver_id}` only when the authenticated user's ID equals the channel receiver ID. The route follows Laravel's broadcasting protocol; it is not a custom API response contract.

## Error Handling

- Unauthenticated protected-route requests: `401`.
- Form Request validation errors: Laravel `422` response.
- Scoped model lookup misses: `404`.
- Policy denials: Laravel authorization response, normally `403` when a lookup has already succeeded.
- Configured rate-limit responses: `429`.
- Wrong password after valid login validation currently returns a message with `200`, not an authentication error status.
- Unvalidated message creation and the message deletion defect can raise server exceptions. Error responses are not normalized into a custom application format.

## Installation and Setup

Prerequisites: PHP `^8.3`, Composer, a database supported by Laravel's configured drivers, and Node.js/npm if building the chat view assets.

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Set the database connection and credentials in `.env`, then run migrations:

```sh
php artisan migrate
```

`.env.example` selects MySQL, while its host, port, database, username, and password entries are commented out. Configure valid MySQL connection details in `.env` before migrating. The committed `DatabaseSeeder` should not be assumed to work: its user factory omits the required `phone` column and provides `email`, which is not a users-table column or fillable model attribute.

For frontend assets:

```sh
npm install
npm run build
```

## Environment Configuration

Configure at least `APP_KEY`, `APP_URL`, `DB_CONNECTION`, and the database-specific settings (`DB_DATABASE`, plus host/port/username/password for network databases). Do not commit secrets.

The scaffold `.env.example` sets `BROADCAST_CONNECTION=log`. For Reverb, configure `BROADCAST_CONNECTION=reverb`, the `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, and `REVERB_SCHEME` values, and the corresponding `VITE_REVERB_*` client values used in `resources/js/echo.js`. The client reads a bearer token from browser `localStorage` under `access_token`.

## Database Migration and Seeding

Apply schema changes with:

```sh
php artisan migrate
```

The repository includes `DatabaseSeeder` and `UserFactory`, but their current attributes do not satisfy the non-null `users.phone` requirement. Seeding has not been run as part of this documentation update; repair the seed data before relying on `php artisan db:seed`.

## Running the Application

```sh
php artisan serve
```

The API base path is `/api/v1`. When using the browser chat stub, build the Vite assets first. To run a Reverb server, configure the environment as described above and run:

```sh
php artisan reverb:start
```

The broadcast client and Blade view are prototype code with a hard-coded user ID; this does not constitute a complete chat client setup.

## Running Tests

Run the suite with:

```sh
php artisan test
```

Verified in this repository on 2026-09-28: **5 tests passed, 0 failed, 0 skipped, 14 assertions**. Active tests cover normalized-phone login, duplicate registration returning `422` validation, authenticated conversation listing, rejecting unauthenticated logout, and a trivial unit example. The duplicate-registration test no longer expects the controller's `409` branch because Form Request uniqueness validation runs first. Test configuration in `phpunit.xml` sets MySQL database `vexo-test`; the database must be available to run the suite in another environment.

Coverage does not currently exercise message creation/deletion, resource authorization boundaries, invalid inputs, OTP behavior, or broadcast delivery.

## Known Limitations

- The API is a prototype and has not received a production security or operational readiness review.
- Registration validates uniqueness before the controller's duplicate-account `409` branch, so duplicates produce `422`.
- Wrong-password login responses use HTTP `200`.
- The OTP is exposed in the response and has no persistence, delivery, expiry, or verification flow.
- `send-message` has no explicit input validation and does not ensure a supplied conversation belongs to the caller or receiver.
- Message delete fails due to the invalid `$$message` reference.
- `apiResource` exposes conversation store/update routes whose controller actions do not exist.
- Conversation resources access messages without eager loading them in the index path, causing an additional query per conversation; the list is also unpaginated.
- The conversation resource labels the receiver as the conversation name even for the receiver's own view.
- The chat page uses a fixed user ID; event reception and deployment configuration are not a production-ready client workflow.
- The seeder/factory do not provide the required phone field.
- No account recovery, OTP verification, conversation/message pagination, or message-history/audit model is implemented.
- Cashier/Stripe database and configuration support exists without subscription or checkout application flows.

## Future Improvements

Potential next steps, not currently implemented, include correcting route/controller mismatches, adding validated and participant-safe message creation, fixing message deletion, using consistent status codes for authentication failures, persisting and verifying OTPs, adding pagination and broadcast integration tests, replacing the hard-coded chat identity, and implementing account recovery or billing flows only if required.

## Contributing

Contributions should include focused tests for changed behavior, preserve existing Laravel conventions, and document API contract changes. Do not treat the currently passing suite as comprehensive authorization or production-readiness coverage.

## License

The project declares the MIT License in `composer.json`; see [`LICENSE`](LICENSE).