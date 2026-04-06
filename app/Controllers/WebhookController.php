<?php
namespace App\Controllers;
use App\Core\Controller; use App\Core\Auth; use App\Core\Database; use App\Core\Webhook;

class WebhookController extends Controller
{
    public function index(): void
    {
        $c = $this->companyId();
        $endpoints = Database::fetchAll("SELECT * FROM webhook_endpoints WHERE company_id=? AND deleted_at IS NULL ORDER BY created_at DESC", [$c]);
        $logs      = Database::fetchAll("SELECT wl.*, we.url FROM webhook_logs wl JOIN webhook_endpoints we ON we.id=wl.webhook_endpoint_id WHERE we.company_id=? ORDER BY wl.created_at DESC LIMIT 50", [$c]);
        $events    = Webhook::events();
        $this->view('webhooks.index', compact('endpoints','logs','events'));
    }
    public function store(): void
    {
        Auth::verifyCsrf(); $c = $this->companyId();
        $url    = filter_var($_POST['url']??'', FILTER_SANITIZE_URL);
        $event  = $this->sanitize($_POST['event']??'');
        $secret = $this->sanitize($_POST['secret'] ?? bin2hex(random_bytes(16)));
        if (!$url || !$event) $this->with('error','URL and event are required.')->redirect('/webhooks');
        Webhook::register($c, $url, $event, $secret);
        $this->with('success','Webhook endpoint registered.')->redirect('/webhooks');
    }
    public function delete(string $id): void
    {
        Auth::verifyCsrf();
        Database::update('webhook_endpoints', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => (int)$id, 'company_id' => $this->companyId()]);
        $this->with('success','Webhook deleted.')->redirect('/webhooks');
    }
    public function toggle(string $id): void
    {
        Auth::verifyCsrf();
        $ep = Database::fetch("SELECT * FROM webhook_endpoints WHERE id=? AND company_id=?", [(int)$id, $this->companyId()]);
        if ($ep) Database::update('webhook_endpoints', ['is_active' => $ep['is_active'] ? 0 : 1], ['id' => $ep['id']]);
        $this->json(['success' => true]);
    }
}
