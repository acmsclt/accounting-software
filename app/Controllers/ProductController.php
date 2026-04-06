<?php
namespace App\Controllers;
use App\Core\Controller; use App\Core\Auth; use App\Core\Database; use App\Models\Product;

class ProductController extends Controller
{
    public function index(): void
    {
        $c = $this->companyId(); $filters = ['search' => $_GET['search'] ?? '', 'low_stock' => isset($_GET['low_stock'])];
        $products = Product::all($c, array_filter($filters), 50, 0);
        $this->view('products.index', compact('products', 'filters'));
    }
    public function create(): void
    {
        $c = $this->companyId();
        $categories = Database::fetchAll("SELECT * FROM categories WHERE company_id=?", [$c]);
        $taxes      = Database::fetchAll("SELECT * FROM taxes WHERE company_id=? AND is_active=1", [$c]);
        $this->view('products.form', ['product' => null, 'categories' => $categories, 'taxes' => $taxes]);
    }
    public function store(): void
    {
        Auth::verifyCsrf(); $c = $this->companyId();
        $data = ['name' => $this->sanitize($_POST['name']??''), 'sku' => strtoupper($this->sanitize($_POST['sku']??'')),
                 'type' => $_POST['type']??'product', 'sale_price' => (float)($_POST['sale_price']??0),
                 'purchase_price' => (float)($_POST['purchase_price']??0), 'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0,
                 'stock_alert_qty' => (int)($_POST['stock_alert_qty']??10), 'description' => $this->sanitize($_POST['description']??'')];
        $errors = $this->validate($data, ['name' => 'required', 'sku' => 'required']);
        if (!empty($errors)) $this->with('errors', $errors)->redirect('/products/create');
        $id = Product::create($c, $data);
        \App\Core\Webhook::dispatch($c, 'product.updated', Product::findById($id, $c));
        $this->with('success', 'Product created.')->redirect('/products');
    }
    public function edit(string $id): void
    {
        $c = $this->companyId();
        $product    = Product::findById((int)$id, $c);
        $categories = Database::fetchAll("SELECT * FROM categories WHERE company_id=?", [$c]);
        $taxes      = Database::fetchAll("SELECT * FROM taxes WHERE company_id=? AND is_active=1", [$c]);
        $this->view('products.form', compact('product', 'categories', 'taxes'));
    }
    public function update(string $id): void
    {
        Auth::verifyCsrf();
        $data = ['name' => $this->sanitize($_POST['name']??''), 'sale_price' => (float)($_POST['sale_price']??0),
                 'purchase_price' => (float)($_POST['purchase_price']??0), 'description' => $this->sanitize($_POST['description']??'')];
        Product::update((int)$id, $this->companyId(), $data);
        $this->with('success', 'Product updated.')->redirect('/products');
    }
    public function delete(string $id): void
    {
        Auth::verifyCsrf(); Product::delete((int)$id, $this->companyId());
        $this->with('success', 'Product deleted.')->redirect('/products');
    }
}
