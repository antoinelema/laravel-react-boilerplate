<?php

namespace App\__Application__\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\__Domain__\UseCase\ProspectCategory\Input\ListInput;
use App\__Domain__\UseCase\ProspectCategory\Input\CreateInput;
use App\__Domain__\UseCase\ProspectCategory\Input\UpdateInput;
use App\__Domain__\UseCase\ProspectCategory\Input\DeleteInput;
use App\__Domain__\UseCase\ProspectCategory\Input\AssignProspectInput;
use App\__Domain__\UseCase\ProspectCategory\ListHandler;
use App\__Domain__\UseCase\ProspectCategory\CreateHandler;
use App\__Domain__\UseCase\ProspectCategory\UpdateHandler;
use App\__Domain__\UseCase\ProspectCategory\DeleteHandler;
use App\__Domain__\UseCase\ProspectCategory\AssignProspectHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProspectCategoryController extends Controller
{
    public function __construct(
        private ListHandler $listHandler,
        private CreateHandler $createHandler,
        private UpdateHandler $updateHandler,
        private DeleteHandler $deleteHandler,
        private AssignProspectHandler $assignProspectHandler
    ) {}

    /**
     * Liste des catégories de l'utilisateur avec nombre de prospects
     */
    public function index(): JsonResponse
    {
        $input = new ListInput(userId: Auth::id());
        $output = $this->listHandler->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage
            ], 500);
        }

        // Transformer les catégories en format API
        $categories = $output->categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'position' => $category->position,
                'prospects_count' => $category->prospectsCount ?? 0,
                'created_at' => $category->createdAt?->format('Y-m-d H:i:s'),
                'updated_at' => $category->updatedAt?->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories
            ]
        ]);
    }

    /**
     * Création d'une nouvelle catégorie
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'string|regex:/^#[0-9a-fA-F]{6}$/',
            'position' => 'integer|min:0'
        ]);

        $input = CreateInput::fromData(Auth::id(), $validated);
        $output = $this->createHandler->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée avec succès',
            'data' => [
                'category' => [
                    'id' => $output->category->id,
                    'name' => $output->category->name,
                    'color' => $output->category->color,
                    'position' => $output->category->position,
                    'prospects_count' => 0,
                    'created_at' => $output->category->createdAt?->format('Y-m-d H:i:s'),
                    'updated_at' => $output->category->updatedAt?->format('Y-m-d H:i:s')
                ]
            ]
        ], 201);
    }

    /**
     * Mise à jour d'une catégorie
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'color' => 'string|regex:/^#[0-9a-fA-F]{6}$/',
            'position' => 'integer|min:0'
        ]);

        $input = UpdateInput::fromData(Auth::id(), $id, $validated);
        $output = $this->updateHandler->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage
            ], $output->errorMessage === 'Catégorie introuvable' ? 404 : 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour avec succès',
            'data' => [
                'category' => [
                    'id' => $output->category->id,
                    'name' => $output->category->name,
                    'color' => $output->category->color,
                    'position' => $output->category->position,
                    'created_at' => $output->category->createdAt?->format('Y-m-d H:i:s'),
                    'updated_at' => $output->category->updatedAt?->format('Y-m-d H:i:s')
                ]
            ]
        ]);
    }

    /**
     * Suppression d'une catégorie
     */
    public function destroy(int $id): JsonResponse
    {
        $input = new DeleteInput(userId: Auth::id(), categoryId: $id);
        $output = $this->deleteHandler->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage
            ], $output->errorMessage === 'Catégorie introuvable' ? 404 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée avec succès'
        ]);
    }

    /**
     * Assigner un prospect à des catégories
     */
    public function assignProspect(Request $request, int $prospectId): JsonResponse
    {
        $validated = $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'integer|exists:prospect_categories,id'
        ]);

        $input = new AssignProspectInput(
            userId: Auth::id(),
            prospectId: $prospectId,
            categoryIds: $validated['category_ids']
        );

        $output = $this->assignProspectHandler->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Prospect assigné aux catégories avec succès'
        ]);
    }

    /**
     * Retirer un prospect d'une catégorie
     */
    public function unassignProspect(int $prospectId, int $categoryId): JsonResponse
    {
        try {
            // Récupérer les catégories actuelles du prospect
            $prospect = \App\__Infrastructure__\Eloquent\ProspectEloquent::where('id', $prospectId)
                                                                        ->where('user_id', Auth::id())
                                                                        ->first();

            if (!$prospect) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prospect introuvable'
                ], 404);
            }

            // Retirer la catégorie
            $prospect->categories()->detach($categoryId);

            return response()->json([
                'success' => true,
                'message' => 'Prospect retiré de la catégorie avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'assignation'
            ], 500);
        }
    }

    /**
     * Réorganiser les catégories (mise à jour des positions)
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|integer|exists:prospect_categories,id',
            'categories.*.position' => 'required|integer|min:0'
        ]);

        try {
            $categoryPositions = [];
            foreach ($validated['categories'] as $categoryData) {
                $categoryPositions[$categoryData['id']] = $categoryData['position'];
            }

            $repository = app(\App\__Infrastructure__\Persistence\ProspectCategory\ProspectCategoryRepository::class);
            $repository->updatePositions($categoryPositions, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Ordre des catégories mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réorganisation des catégories'
            ], 500);
        }
    }
}