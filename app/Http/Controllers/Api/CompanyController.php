<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Get master product catalog for a company
     */
    public function products(Company $company): JsonResponse
    {
        $products = $company->products()->get();

        return response()->json([
            'data' => $products
        ]);
    }

    /**
     * Get all locations for a company
     */
    public function locations(Company $company): JsonResponse
    {
        $locations = $company->locations()->get();

        return response()->json([
            'data' => $locations
        ]);
    }
}
