<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
   public function index(Request $request)
{
    $merchantId = auth()->user()->merchant_id ?? auth()->id();

    $search = $request->input('search');

    $query = Product::with('category')
        ->where('merchant_id', $merchantId);

    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%")
              ->orWhere('price', 'like', "%{$search}%");
        });
    }

    $products = $query->paginate(10)->withQueryString();

    $categories = Category::where('merchant_id', $merchantId)->get();

    return Inertia::render('Merchant/Products', [
        'products' => $products,
        'categories' => $categories,
        'filters' => $request->only('search'),
    ]);
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $merchantId = Auth::id(); // or Auth::user()->merchant_id if applicable

        Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'category_id' => $validated['category_id'],
            'merchant_id' => $merchantId, // ✅ Important!
        ]);

        return redirect()->route('merchant.products')->with('success', 'Product added!');
    }

    public function update(Request $request, Product $product)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $product->update($validated);
        

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

 public function exportProductsCsv(Request $request)
{
    $categoryIds = $request->input('category_id'); // Expecting array of IDs

    // Ensure it's always an array (in case of single value passed)
    if (!is_array($categoryIds) && $categoryIds !== null) {
        $categoryIds = [$categoryIds];
    }

    // Fetch products with optional category filtering
    $products = Product::with('category')
        ->when(!empty($categoryIds), function ($query) use ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        })
        ->get();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column headers
    $sheet->fromArray([
        ['Category', 'Name', 'Description', 'Price', 'Stock']
    ]);

    // Fill in product rows
    $rowIndex = 2;
    foreach ($products as $product) {
        $sheet->fromArray([
            [
                $product->category->name ?? 'Uncategorized',
                $product->name,
                $product->description,
                $product->price,
                $product->stock,
            ]
        ], null, 'A' . $rowIndex++);
    }

    $filename = 'products_' . now()->format('Ymd_His') . '.xlsx';

    return new StreamedResponse(function () use ($spreadsheet) {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 200, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
        'Cache-Control' => 'max-age=0',
    ]);
}
}
