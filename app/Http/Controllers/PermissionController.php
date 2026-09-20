<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function (Permission $permission) {
            return explode('.', $permission->name)[0];
        });

        return response()->json($permissions);
    }
}
