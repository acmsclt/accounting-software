<?php
// app/Api/AuthApiController.php

namespace App\Api;

use App\Core\ApiController;
use App\Core\Auth;
use App\Core\Database;
use App\Models\User;

class AuthApiController extends ApiController
{
    /** POST /api/login */
    public function login(): void
    {
        $body  = $this->body();
        $email = filter_var($body['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $pass  = $body['password'] ?? '';

        $errors = $this->validate($body, ['email' => 'required|email', 'password' => 'required']);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);

        $user = User::findByEmail($email);
        if (!$user || !User::verifyPassword($pass, $user['password'])) {
            $this->error('Invalid credentials.', 401);
        }

        if (!$user['is_active']) $this->error('Account is suspended.', 403);

        $companyId = (int)($user['active_company_id'] ?? 0);
        $token     = Auth::generateJwt($user, $companyId);
        $refresh   = Auth::generateRefreshToken($user['id']);

        User::updateLastLogin($user['id']);

        // Store refresh token
        Database::query(
            "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()",
            [$user['email'], hash('sha256', $refresh), date('Y-m-d H:i:s')]
        );

        $this->success([
            'access_token'  => $token,
            'refresh_token' => $refresh,
            'token_type'    => 'Bearer',
            'expires_in'    => (int)($_ENV['JWT_EXPIRY'] ?? 3600),
            'user' => [
                'id'         => $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'company_id' => $companyId,
            ],
        ], 'Login successful.');
    }

    /** POST /api/register */
    public function register(): void
    {
        $body   = $this->body();
        $errors = $this->validate($body, [
            'name'     => 'required|min:2',
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);

        if (User::findByEmail($body['email'])) {
            $this->error('Email already registered.', 409);
        }

        $userId    = User::create([
            'name'                => htmlspecialchars($body['name'], ENT_QUOTES),
            'email'               => $body['email'],
            'password'            => $body['password'],
            'role'                => 'admin',
            'subscription_status' => 'trialing',
            'trial_ends_at'       => date('Y-m-d', strtotime('+14 days')),
        ]);

        $companyId = Database::insert('companies', [
            'owner_id'   => $userId,
            'name'       => htmlspecialchars($body['company_name'] ?? $body['name'] . "'s Company", ENT_QUOTES),
            'currency'   => 'USD',
            'timezone'   => 'UTC',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::insert('branches', [
            'company_id' => $companyId,
            'name'       => 'Main Branch',
            'code'       => 'HQ',
            'is_default' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::insert('company_users', ['company_id' => $companyId, 'user_id' => $userId, 'role' => 'admin']);
        User::update($userId, ['active_company_id' => $companyId]);

        $user  = User::findById($userId);
        $token = Auth::generateJwt($user, $companyId);

        $this->created([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => (int)($_ENV['JWT_EXPIRY'] ?? 3600),
            'user' => ['id' => $userId, 'name' => $user['name'], 'email' => $user['email']],
        ], 'Account created. 14-day trial started.');
    }

    /** POST /api/refresh */
    public function refresh(): void
    {
        $body  = $this->body();
        $token = $body['refresh_token'] ?? '';

        $record = Database::fetch(
            "SELECT * FROM password_resets WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [hash('sha256', $token)]
        );

        if (!$record) $this->error('Invalid or expired refresh token.', 401);

        $user = User::findByEmail($record['email']);
        if (!$user) $this->error('User not found.', 404);

        $newToken = Auth::generateJwt($user, (int)($user['active_company_id'] ?? 0));
        $this->success(['access_token' => $newToken, 'token_type' => 'Bearer']);
    }
}
