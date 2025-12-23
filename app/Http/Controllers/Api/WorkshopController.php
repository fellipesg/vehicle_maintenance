<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WorkshopController extends Controller
{
    /**
     * Display a listing of workshops with optional search
     */
    public function index(Request $request): JsonResponse
    {
        $query = Workshop::query();

        // Search by name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('neighborhood', 'like', "%{$search}%");
            });
        }

        $workshops = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $workshops,
        ]);
    }

    /**
     * Store a newly created workshop
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'facebook' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'cep' => 'required|string|size:8',
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If whatsapp is not provided, use phone
        $whatsapp = $request->whatsapp ?? $request->phone;

        $workshop = Workshop::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'whatsapp' => $whatsapp,
            'email' => $request->email,
            'facebook' => $request->facebook,
            'instagram' => $request->instagram,
            'cep' => preg_replace('/\D/', '', $request->cep), // Remove non-digits
            'street' => $request->street,
            'number' => $request->number,
            'complement' => $request->complement,
            'neighborhood' => $request->neighborhood,
            'city' => $request->city,
            'state' => strtoupper($request->state),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Oficina cadastrada com sucesso!',
            'data' => $workshop,
        ], 201);
    }

    /**
     * Display the specified workshop
     */
    public function show(string $id): JsonResponse
    {
        $workshop = Workshop::find($id);

        if (!$workshop) {
            return response()->json([
                'success' => false,
                'message' => 'Oficina não encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $workshop,
        ]);
    }

    /**
     * Update the specified workshop
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $workshop = Workshop::find($id);

        if (!$workshop) {
            return response()->json([
                'success' => false,
                'message' => 'Oficina não encontrada',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'facebook' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'cep' => 'sometimes|required|string|size:8',
            'street' => 'sometimes|required|string|max:255',
            'number' => 'sometimes|required|string|max:20',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'sometimes|required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name', 'phone', 'whatsapp', 'email', 'facebook', 'instagram',
            'cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'
        ]);

        // Format CEP and state
        if (isset($data['cep'])) {
            $data['cep'] = preg_replace('/\D/', '', $data['cep']);
        }
        if (isset($data['state'])) {
            $data['state'] = strtoupper($data['state']);
        }

        // If whatsapp is not provided, use phone
        if (!isset($data['whatsapp']) && isset($data['phone'])) {
            $data['whatsapp'] = $data['phone'];
        } elseif (!isset($data['whatsapp']) && !isset($data['phone'])) {
            $data['whatsapp'] = $workshop->phone;
        }

        $workshop->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Oficina atualizada com sucesso!',
            'data' => $workshop->fresh(),
        ]);
    }

    /**
     * Remove the specified workshop
     */
    public function destroy(string $id): JsonResponse
    {
        $workshop = Workshop::find($id);

        if (!$workshop) {
            return response()->json([
                'success' => false,
                'message' => 'Oficina não encontrada',
            ], 404);
        }

        // Check if workshop has maintenances
        if ($workshop->maintenances()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir a oficina pois ela possui manutenções associadas',
            ], 422);
        }

        $workshop->delete();

        return response()->json([
            'success' => true,
            'message' => 'Oficina excluída com sucesso!',
        ]);
    }
}
