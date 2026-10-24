<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeTypeRequest;
use App\Models\FeeType;
use Illuminate\Http\Request;

class FeeTypeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $types = FeeType::query()
            ->withCount('fees')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('fee-types.index', compact('types', 'search'));
    }

    public function create()
    {
        return view('fee-types.create');
    }

    public function store(FeeTypeRequest $request)
    {
        $type = FeeType::create($request->validated());

        return redirect()->route('fee-types.index')->with('success', "Fee type \"{$type->name}\" added.");
    }

    public function edit(FeeType $feeType)
    {
        return view('fee-types.edit', compact('feeType'));
    }

    public function update(FeeTypeRequest $request, FeeType $feeType)
    {
        $feeType->update($request->validated());

        return redirect()->route('fee-types.index')->with('success', 'Fee type updated successfully.');
    }

    public function destroy(FeeType $feeType)
    {
        // Jis fee type ke vouchers ban chuke hon wo delete nahi hoti
        if ($feeType->fees()->exists()) {
            return redirect()->route('fee-types.index')
                ->with('error', "\"{$feeType->name}\" ke vouchers maujood hain, isliye delete nahi ho sakti.");
        }

        $name = $feeType->name;
        $feeType->delete();

        return redirect()->route('fee-types.index')->with('success', "Fee type \"{$name}\" deleted.");
    }
}
