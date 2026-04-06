<?php
// app/Controllers/BranchController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Branch;
use App\Models\User;

class BranchController extends Controller
{
    public function index(): void
    {
        $companyId = $this->companyId();
        $branches  = Branch::allForCompany($companyId);
        $summary   = Branch::summary($companyId);

        // Index summary by branch id for easy lookup
        $summaryMap = [];
        foreach ($summary as $s) $summaryMap[$s['id']] = $s;

        $this->view('branches.index', compact('branches', 'summaryMap'));
    }

    public function create(): void
    {
        $this->view('branches.form', ['branch' => null]);
    }

    public function store(): void
    {
        Auth::verifyCsrf();
        $companyId = $this->companyId();

        $data = [
            'name'       => $this->sanitize($_POST['name'] ?? ''),
            'code'       => strtoupper($this->sanitize($_POST['code'] ?? '')),
            'address'    => $this->sanitize($_POST['address'] ?? ''),
            'city'       => $this->sanitize($_POST['city'] ?? ''),
            'state'      => $this->sanitize($_POST['state'] ?? ''),
            'country'    => $this->sanitize($_POST['country'] ?? ''),
            'phone'      => $this->sanitize($_POST['phone'] ?? ''),
            'email'      => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'is_active'  => 1,
        ];

        $errors = $this->validate($data, ['name' => 'required|min:2']);
        if (!empty($errors)) {
            $this->with('errors', $errors)->redirect('/branches/create');
        }

        Branch::create($companyId, $data);
        $this->with('success', 'Branch created successfully.')->redirect('/branches');
    }

    public function edit(string $id): void
    {
        $branch = Branch::findById((int)$id, $this->companyId());
        if (!$branch) $this->redirect('/branches');
        $this->view('branches.form', compact('branch'));
    }

    public function update(string $id): void
    {
        Auth::verifyCsrf();
        $companyId = $this->companyId();

        $data = [
            'name'       => $this->sanitize($_POST['name'] ?? ''),
            'code'       => strtoupper($this->sanitize($_POST['code'] ?? '')),
            'address'    => $this->sanitize($_POST['address'] ?? ''),
            'city'       => $this->sanitize($_POST['city'] ?? ''),
            'state'      => $this->sanitize($_POST['state'] ?? ''),
            'country'    => $this->sanitize($_POST['country'] ?? ''),
            'phone'      => $this->sanitize($_POST['phone'] ?? ''),
            'email'      => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];

        Branch::update((int)$id, $companyId, $data);
        $this->with('success', 'Branch updated.')->redirect('/branches');
    }

    public function delete(string $id): void
    {
        Auth::verifyCsrf();
        $result = Branch::delete((int)$id, $this->companyId());

        if ($result === 0) {
            $this->with('error', 'Cannot delete the default branch.')->redirect('/branches');
        }

        $this->with('success', 'Branch deleted.')->redirect('/branches');
    }

    /** AJAX: Switch active branch for session */
    public function switchBranch(): void
    {
        $branchId  = (int)($_POST['branch_id'] ?? 0);
        $companyId = $this->companyId();

        $branch = Branch::findById($branchId, $companyId);
        if (!$branch) {
            $this->json(['success' => false, 'message' => 'Branch not found.'], 404);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['branch_id']   = $branch['id'];
        $_SESSION['branch_name'] = $branch['name'];

        $this->json(['success' => true, 'branch' => $branch]);
    }
}
