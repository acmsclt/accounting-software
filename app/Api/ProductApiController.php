<?php
namespace App\Api;
use App\Core\ApiController; use App\Core\Webhook; use App\Models\Product;

class ProductApiController extends ApiController
{
    public function index(): void
    {
        $this->requireAuth();
        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginationParams();
        $filters  = array_filter(['search' => $_GET['search']??'', 'type' => $_GET['type']??'', 'low_stock' => isset($_GET['low_stock'])]);
        $products = Product::all($this->companyId, $filters, $perPage, $offset);
        $total    = Product::count($this->companyId);
        $this->paginatedResponse($products, $total, $page, $perPage);
    }
    public function store(): void
    {
        $this->requireAuth(); $body = $this->body();
        $errors = $this->validate($body, ['name' => 'required', 'sku' => 'required']);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);
        $id = Product::create($this->companyId, $body);
        $p  = Product::findById($id, $this->companyId);
        Webhook::dispatch($this->companyId, 'product.updated', $p);
        $this->created($p);
    }
    public function show(string $id): void
    {
        $this->requireAuth();
        $p = Product::findById((int)$id, $this->companyId);
        if (!$p) $this->notFound();
        $this->success(['product' => $p, 'stock' => Product::stockByWarehouse((int)$id)]);
    }
    public function update(string $id): void
    {
        $this->requireAuth(); $body = $this->body();
        $p = Product::findById((int)$id, $this->companyId);
        if (!$p) $this->notFound();
        Product::update((int)$id, $this->companyId, $body);
        $updated = Product::findById((int)$id, $this->companyId);
        Webhook::dispatch($this->companyId, 'product.updated', $updated);
        $this->success($updated);
    }
    public function destroy(string $id): void
    {
        $this->requireAuth();
        Product::delete((int)$id, $this->companyId);
        $this->success(null, 'Product deleted.');
    }
}
