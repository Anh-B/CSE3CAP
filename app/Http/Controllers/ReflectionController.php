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
        // auth()->id() is always set here - this route sits behind
        // the auth:sanctum middleware group, so there's no anonymous case.
        $reflection = Reflection::create([
            'user_id' => auth()->id(),
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
        // Any logged-in user can list reflections, since assessors need to
        // browse everyone's to find ones to score.
        //
        // Paginated so the response stays fast as the table grows.
        // Clients can pass ?per_page=20&page=2.
        //
        // Each item includes assessment_status ('assessed' or 'pending'),
        // and ?status=pending or ?status=assessed filters the list, so the
        // frontend can show which reflections still need scoring.
        $request->validate([
            'status' => 'nullable|in:pending,assessed',
        ], [
            'status.in' => 'Status must be either pending or assessed.',
        ]);

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // clamp to 1-100

        $query = Reflection::latest()->withExists('assessments');

        if ($request->query('status') === 'pending') {
            $query->whereDoesntHave('assessments');
        } elseif ($request->query('status') === 'assessed') {
            $query->whereHas('assessments');
        }

        $reflections = $query->paginate($perPage);

        // withExists() adds an 'assessments_exists' true/false to each row;
        // turn that into a clearer label for the frontend.
        $items = collect($reflections->items())->map(function ($reflection) {
            $data = $reflection->toArray();
            $data['assessment_status'] = $reflection->assessments_exists ? 'assessed' : 'pending';
            unset($data['assessments_exists']);
            return $data;
        });

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $reflections->currentPage(),
                'per_page'     => $reflections->perPage(),
                'total'        => $reflections->total(),
                'last_page'    => $reflections->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single reflection entry, with any assessor feedback and
     * evidence attached to it.
     * Gives the radar chart both sets of scores in one call:
     * data.scores (self) and data.assessments[].scores (assessor).
     */
    public function show($id)
    {
        // 1. Find the reflection entry by ID, with its assessments and evidence
        $reflection = Reflection::with(['assessments', 'evidence'])->find($id);

        // 2. Check if the entry exists (Edge case handling)
        if (!$reflection) {
            return response()->json([
                'success' => false,
                'message' => 'Reflection entry not found.'
            ], 404);
        }

        // 3. Return the entry. assessment_status matches the list
        // endpoint: 'assessed' once an assessor has scored it, otherwise
        // 'pending', so the page can show which state it's in.
        $data = $reflection->toArray();
        $data['assessment_status'] = $reflection->assessments->isNotEmpty() ? 'assessed' : 'pending';

        return response()->json([
            'success' => true,
            'data'    => $data
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

        // 3. Only the student who owns this entry can edit it
        if ((int) $reflection->user_id !== (int) auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own reflections.'
            ], 403);
        }

        // 4. Validate input data
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

        // 5. Update the database record
        $reflection->update($validated);

        // 6. Return success response
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

        // 3. Only the student who owns this entry can delete it
        if ((int) $reflection->user_id !== (int) auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own reflections.'
            ], 403);
        }

        // 4. Delete the record
        $reflection->delete();

        // 5. Return success response
        return response()->json([
            'success' => true,
            'message' => 'Reflection deleted successfully!'
        ], 200);
    }
}