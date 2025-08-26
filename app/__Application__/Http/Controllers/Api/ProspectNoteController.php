<?php

namespace App\__Application__\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProspectNoteController extends Controller
{
    public function index(Request $request, string $prospectId): JsonResponse
    {
        $userId = Auth::id();
        
        // Vérifier que le prospect appartient à l'utilisateur
        $prospect = DB::table('prospects')
            ->where('id', $prospectId)
            ->where('user_id', $userId)
            ->first();
            
        if (!$prospect) {
            return response()->json(['error' => 'Prospect not found'], 404);
        }

        $notes = DB::table('prospect_notes')
            ->where('prospect_id', $prospectId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'notes' => $notes
            ]
        ]);
    }

    public function store(Request $request, string $prospectId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
            'type' => 'required|in:note,call,email,meeting,task',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        
        // Vérifier que le prospect appartient à l'utilisateur
        $prospect = DB::table('prospects')
            ->where('id', $prospectId)
            ->where('user_id', $userId)
            ->first();
            
        if (!$prospect) {
            return response()->json(['error' => 'Prospect not found'], 404);
        }

        $noteId = DB::table('prospect_notes')->insertGetId([
            'prospect_id' => $prospectId,
            'user_id' => $userId,
            'content' => $request->content,
            'type' => $request->type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $note = DB::table('prospect_notes')->where('id', $noteId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'note' => $note
            ]
        ], 201);
    }

    public function update(Request $request, string $prospectId, string $noteId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
            'type' => 'required|in:note,call,email,meeting,task',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        
        // Vérifier que le prospect appartient à l'utilisateur
        $prospect = DB::table('prospects')
            ->where('id', $prospectId)
            ->where('user_id', $userId)
            ->first();
            
        if (!$prospect) {
            return response()->json(['error' => 'Prospect not found'], 404);
        }

        $updated = DB::table('prospect_notes')
            ->where('id', $noteId)
            ->where('prospect_id', $prospectId)
            ->update([
                'content' => $request->content,
                'type' => $request->type,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $note = DB::table('prospect_notes')->where('id', $noteId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'note' => $note
            ]
        ]);
    }

    public function destroy(string $prospectId, string $noteId): JsonResponse
    {
        $userId = Auth::id();
        
        // Vérifier que le prospect appartient à l'utilisateur
        $prospect = DB::table('prospects')
            ->where('id', $prospectId)
            ->where('user_id', $userId)
            ->first();
            
        if (!$prospect) {
            return response()->json(['error' => 'Prospect not found'], 404);
        }

        $deleted = DB::table('prospect_notes')
            ->where('id', $noteId)
            ->where('prospect_id', $prospectId)
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    }
}