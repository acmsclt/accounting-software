<?php
// app/Controllers/AuthController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth.login', ['title' => 'Sign In — AccountingPro']);
    }

    public function login(): void
    {
        Auth::verifyCsrf();

        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->with('error', 'Email and password are required.')->redirect('/login');
        }

        $user = User::findByEmail($email);

        if (!$user || !User::verifyPassword($password, $user['password'])) {
            $this->with('error', 'Invalid credentials. Please try again.')->redirect('/login');
        }

        if (!$user['is_active']) {
            $this->with('error', 'Your account has been suspended.')->redirect('/login');
        }

        User::updateLastLogin($user['id']);
        Auth::login($user);

        $this->redirect('/dashboard');
    }

    public function showRegister(): void
    {
        if (Auth::check()) $this->redirect('/dashboard');
        $this->view('auth.register', ['title' => 'Create Account — AccountingPro']);
    }

    public function register(): void
    {
        Auth::verifyCsrf();

        $data = [
            'name'     => $this->sanitize($_POST['name'] ?? ''),
            'email'    => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'password' => $_POST['password'] ?? '',
        ];

        $errors = $this->validate($data, [
            'name'     => 'required|min:2',
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);

        if (!empty($errors)) {
            $this->with('errors', $errors)->with('old', $data)->redirect('/register');
        }

        if (User::findByEmail($data['email'])) {
            $this->with('error', 'An account with this email already exists.')->redirect('/register');
        }

        $data['role']            = 'admin';
        $data['subscription_status'] = 'trialing';
        $data['trial_ends_at']   = date('Y-m-d', strtotime('+14 days'));
        $userId = User::create($data);

        // Auto-create a default company
        $companyId = \App\Core\Database::insert('companies', [
            'owner_id'   => $userId,
            'name'       => $data['name'] . "'s Company",
            'currency'   => 'USD',
            'timezone'   => 'UTC',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create default branch
        \App\Core\Database::insert('branches', [
            'company_id' => $companyId,
            'name'       => 'Main Branch',
            'code'       => 'HQ',
            'is_default' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        \App\Core\Database::insert('company_users', [
            'company_id' => $companyId,
            'user_id'    => $userId,
            'role'       => 'admin',
        ]);
        User::update($userId, ['active_company_id' => $companyId]);

        // Seed default chart of accounts (minimal)
        $user = User::findById($userId);
        Auth::login(array_merge($user, ['active_company_id' => $companyId]));

        $this->with('success', 'Welcome! Your 14-day trial has started.')->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
