<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $brands = Brand::query()
            ->with('creator:id,name')
            ->withCount('products')
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('country', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('brands/index', [
            'brands'  => $brands,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('brands/create');
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands/logos', 'public');
        }

        $brand = Brand::create($data);

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$brand->name}\" was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Brand $brand): Response
    {
        if ($brand->trash) {
            abort(404);
        }

        $brand->load([
            'creator:id,name,email',
            'products' => fn ($q) => $q->notTrashed()->limit(20),
        ]);

        return Inertia::render('brands/show', [
            'brand' => $brand,
            'stats' => [
                'total_products' => $brand->products()->notTrashed()->count(),
            ],
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Brand $brand): Response
    {
        if ($brand->trash) {
            abort(404);
        }

        return Inertia::render('brands/edit', [
            'brand' => $brand,
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        if ($request->hasFile('logo')) {
            if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
                Storage::disk('public')->delete($brand->logo);
            }
            $data['logo'] = $request->file('logo')->store('brands/logos', 'public');
        }

        $brand->update($data);

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$brand->name}\" was updated successfully.");
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */

    public function destroy(Brand $brand): RedirectResponse
    {
        $name = $brand->name;

        $brand->update([
            'trash'   => true,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$name}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);

        $brand->update([
            'trash'   => false,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$brand->name}\" was restored.");
    }

    /* =========================================================
     |  FORCE DELETE
     ========================================================= */

    public function forceDelete(int $id): RedirectResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);

        if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
            Storage::disk('public')->delete($brand->logo);
        }

        $name = $brand->name;
        $brand->forceDelete();

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$name}\" was permanently deleted.");
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */

    public function trash(): Response
    {
        $brands = Brand::onlyTrashed()
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('brands/trash', [
            'brands' => $brands,
        ]);
    }

    /* =========================================================
     |  TOGGLE STATUS
     ========================================================= */

    public function toggleStatus(Brand $brand): RedirectResponse
    {
        $brand->update([
            'status'  => $brand->status === 'active' ? 'inactive' : 'active',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Brand status changed to {$brand->status}.");
    }
}