<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function __construct(
        private TenantService $tenantService
    ) {}

    /**
     * Register a new user and create their tenant
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'tenant_name' => 'required|string|max:255',
            'domain' => [
                'required',
                'string',
                'max:255',
                'unique:tenants',
                'regex:/^[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare tenant and user data
            $tenantData = [
                'name' => $request->tenant_name,
                'domain' => $request->domain,
            ];

            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
            ];

            // Validate data using service
            $validatedTenantData = $this->tenantService->validateTenantData($tenantData);
            $validatedUserData = $this->tenantService->validateUserData($userData);

            // Create tenant with owner
            $result = $this->tenantService->createTenantWithOwner(
                $validatedTenantData,
                $validatedUserData
            );

            return response()->json([
                'message' => 'Registration successful',
                'user' => $result['user'],
                'tenant' => $result['tenant'],
                'token' => $result['token'],
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'error' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
