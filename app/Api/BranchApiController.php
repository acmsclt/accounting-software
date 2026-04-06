<?php
namespace App\Api;
use App\Core\ApiController; use App\Models\Branch;

class BranchApiController extends ApiController
{
    public function index(): void
    {
        $this->requireAuth();
        $branches = Branch::allForCompany($this->companyId);
        $this->success($branches);
    }
    public function store(): void
    {
        $this->requireAuth(); $body = $this->body();
        $errors = $this->validate($body, ['name' => 'required|min:2']);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);
        $id = Branch::create($this->companyId, $body);
        $this->created(Branch::findById($id, $this->companyId));
    }
    public function show(string $id): void
    {
        $this->requireAuth();
        $b = Branch::findById((int)$id, $this->companyId);
        if (!$b) $this->notFound();
        $summary = Branch::summary($this->companyId);
        $this->success(['branch' => $b, 'users' => Branch::getUsers((int)$id)]);
    }
    public function update(string $id): void
    {
        $this->requireAuth(); $body = $this->body();
        Branch::update((int)$id, $this->companyId, $body);
        $this->success(Branch::findById((int)$id, $this->companyId));
    }
    public function destroy(string $id): void
    {
        $this->requireAuth();
        $result = Branch::delete((int)$id, $this->companyId);
        if (!$result) $this->error('Cannot delete the default branch.', 422);
        $this->success(null, 'Branch deleted.');
    }
}
