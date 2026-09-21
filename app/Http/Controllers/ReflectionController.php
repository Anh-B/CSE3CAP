<?php

namespace App\Http\Controllers;

use App\Models\Reflection;
use Illuminate\Http\Request;

class ReflectionController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate request data
        $validated = $request->validate([
            'score'                 => 'required|integer|min:1|max:5',
            'comment'               => 'nullable|string|max:1000',
            // Optional per-competency scores that feed the radar chart.
            // Kept separate from the overall `score` above so existing
            // clients that only send `score` keep working unchanged.
            'scores'                    => 'nullable|array',
            'scores.contribution'      => 'required_with:scores|integer|min:1|max:5',
            'scores.communication'     => 'required_with:scores|integer|min:1|max:5',
            'scores.collaboration'     => 'required_with:scores|integer|min:1|max:5',
            'scores.agile'             => 'required_with:scores|integer|min:1|max:5',
            'scores.continuous'        => 'required_with:scores|integer|min:1|max:5',
            'scores.leadership'        => 'required_with:scores|integer|min:1|max:5',
        ], [
            'score.required' => 'Please select a self-review score.',
            'score.integer'  => 'The score must be an integer.',
            'score.min'      => 'The self-review score must be at least 1.',
            'score.max'      => 'The self-review score may not be greater than 5.',
            'comment.max'    => 'The comment may not be greater than 1000 characters.',
        ]);

        // 2. Save the reflection entry to the database
        $reflection = Reflection::create([
            'user_id' => auth()->id() ?? null,
            'score'   => $validated['score'],
            'comment' => $validated['comment'] ?? null,
            'scores'  => $validated['scores'] ?? null,
        ]);

        // 3. Return success response
        return response()->json([
            'success' => true,
            'message' => 'Reflection score submitted successfully!',
            'data'    => $reflection,
        ], 201);
    }

    public function index(Request $request)
{
    // Paginate instead of loading the entire table at once,
    // keeping the response fast and stable as the number of
    // reflections grows. Clients can pass ?per_page=20&page=2.
    $perPage = (int) $request->query('per_page', 15);
    $perPage = min(max($perPage, 1), 100); // clamp to 1-100

    $reflections = Reflection::latest()->paginate($perPage);

    return response()->json([
        'success' => true,
        'data'    => $reflections->items(),
        'meta'    => [
            'current_page' => $reflections->currentPage(),
            'per_page'     => $reflections->perPage(),
            'total'        => $reflections->total(),
            'last_page'    => $reflections->lastPage(),
        ],
    ]);
}

    /**
     * Show a single reflection entry, with any assessor feedback on it.
     * Gives the radar chart both sets of scores in one call:
     * data.scores (self) and data.assessments[].scores (assessor).
     */
    public function show($id)
    {
        // 1. Find the reflection entry by ID, with its assessments
        $reflection = Reflection::with('assessments')->find($id);

        // 2. Check if the entry exists (Edge case handling)
        if (!$reflection) {
            return response()->json([
                'success' => false,
                'message' => 'Reflection entry not found.'
            ], 404);
        }

        // 3. Return the entry
        return response()->json([
            'success' => true,
            'data'    => $reflection
        ], 200);
    }

    /**
     * Update the specified reflection entry.
     */
    public function update(Request $request, $id)
    {
        // 1. Find the reflection entry by ID
        $reflection = Reflection::find($id);

        // 2. Check if the entry exists (Edge case handling)
        if (!$reflection) {
            return response()->json([
                'success' => false,
                'message' => 'Reflection entry not found.'
            ], 404);
        }

        // 3. Validate input data
        $validated = $request->validate([
            'score'                 => 'sometimes|required|integer|min:1|max:5',
            'comment'               => 'nullable|string|max:1000',
            'scores'                    => 'nullable|array',
            'scores.contribution'      => 'required_with:scores|integer|min:1|max:5',
            'scores.communication'     => 'required_with:scores|integer|min:1|max:5',
            'scores.collaboration'     => 'required_with:scores|integer|min:1|max:5',
            'scores.agile'             => 'required_with:scores|integer|min:1|max:5',
            'scores.continuous'        => 'required_with:scores|integer|min:1|max:5',
            'scores.leadership'        => 'required_with:scores|integer|min:1|max:5',
        ], [
            'score.integer' => 'The score must be an integer.',
            'score.min'     => 'The self-review score must be at least 1.',
            'score.max'     => 'The self-review score may not be greater than 5.',
            'comment.max'   => 'The comment may not exceed 1000 characters.'
        ]);

        // 4. Update the database record
        $reflection->update($validated);

        // 5. Return success response
        return response()->json([
            'success' => true,
            'message' => 'Reflection updated successfully!',
            'data'    => $reflection
        ], 200);
    }

    /**
     * Remove the specified reflection entry from storage.
     */
    public function destroy($id)
    {
        // 1. Find the reflection entry by ID
        $reflection = Reflection::find($id);

        // 2. Check if the entry exists (Edge case handling)
        if (!$reflection) {
            return response()->json([
                'success' => false,
                'message' => 'Reflection entry not found.'
            ], 404);
        }

        // 3. Delete the record
        $reflection->delete();

        // 4. Return success response
        return response()->json([
            'success' => true,
            'message' => 'Reflection deleted successfully!'
        ], 200);
    }
}