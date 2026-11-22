<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

/** Proveedores (solo administrador). No se borran: se desactivan para conservar el historial de compras. */
class SupplierController extends Controller
{
    private function rules(): array
    {
        return [
            'name'      => 'required|string|max:250',
            'contact'   => 'nullable|string|max:120',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:180',
            'address'   => 'nullable|string|max:500',
            'notes'     => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($request->validate($this->rules()));
        return back()->with('success', "Proveedor \"{$supplier->name}\" agregado.");
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($request->validate($this->rules()));
        return back()->with('success', "Proveedor \"{$supplier->name}\" actualizado.");
    }
}
