<?php
namespace App\Api;
use App\Core\ApiController; use App\Core\Database; use App\Core\Webhook;

class WebhookApiController extends ApiController
{
    public function index(): void
    {
        $this->requireAuth();
        $endpoints = Database::fetchAll("SELECT * FROM webhook_endpoints WHERE company_id=? AND deleted_at IS NULL", [$this->companyId]);
        $this->success(['endpoints' => $endpoints, 'events' => Webhook::events()]);
    }
    public function store(): void
    {
        $this->requireAuth(); $body = $this->body();
        $errors = $this->validate($body, ['url' => 'required', 'event' => 'required']);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);
        if (!in_array($body['event'], Webhook::events())) $this->error('Invalid event type.', 422);
        $id = Webhook::register($this->companyId, $body['url'], $body['event'], $body['secret'] ?? '');
        $this->created(Database::fetch("SELECT * FROM webhook_endpoints WHERE id=?", [$id]));
    }
    public function destroy(string $id): void
    {
        $this->requireAuth();
        Database::update('webhook_endpoints', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => (int)$id, 'company_id' => $this->companyId]);
        $this->success(null, 'Webhook deleted.');
    }
}
