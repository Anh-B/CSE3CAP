<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function store(Request $request)
    {
    // Validation dữ liệu
        $validated = $request->validate([
            'reflection_id' => 'required|exists:reflections,id', // Bài đánh giá phải tồn tại trong CSDL
            'score'         => 'required|integer|min:1|max:5',
            'feedback'      => 'nullable|string|max:1000',
            // Per-competency counter-scores that feed the radar chart,
            // mirroring the self-assessment side in ReflectionController.
            // Kept separate from the overall `score` above so existing
            // clients that only send `score` keep working unchanged.
            'scores'                => 'nullable|array',
            'scores.contribution'   => 'required_with:scores|integer|min:1|max:5',
            'scores.communication'  => 'required_with:scores|integer|min:1|max:5',
            'scores.collaboration'  => 'required_with:scores|integer|min:1|max:5',
            'scores.agile'          => 'required_with:scores|integer|min:1|max:5',
            'scores.continuous'     => 'required_with:scores|integer|min:1|max:5',
            'scores.leadership'     => 'required_with:scores|integer|min:1|max:5',
        ], [
            'reflection_id.required' => 'The reflection ID is required.',
            'reflection_id.exists'   => 'The selected reflection does not exist.',
            'score.required'         => 'Please provide an assessment score.',
            'score.integer'          => 'The assessment score must be an integer.',
            'score.min'              => 'The assessment score must be at least 1.',
            'score.max'              => 'The assessment score may not be greater than 5.',
            'feedback.max'           => 'The feedback may not be greater than 1000 characters.',
        ]);

        // Lưu thông tin đánh giá
        $assessment = Assessment::create([
            'reflection_id' => $validated['reflection_id'],
            'assessor_id'   => auth()->id() ?? null,
            'score'         => $validated['score'],
            'feedback'      => $validated['feedback'] ?? null,
            'scores'        => $validated['scores'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assessor feedback submitted successfully!',
            'data'    => $assessment
        ], 201);
    }

    /**
     * List assessments, newest first. Paginated the same way as
     * GET /api/reflections. Pass ?reflection_id=5 to only get the
     * assessments for one reflection.
     */
    public function index(Request $request)
    {
        $request->validate([
            'reflection_id' => 'nullable|integer',
        ]);

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // clamp to 1-100

        $query = Assessment::latest();

        if ($request->filled('reflection_id')) {
            $query->where('reflection_id', $request->query('reflection_id'));
        }

        $assessments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $assessments->items(),
            'meta'    => [
                'current_page' => $assessments->currentPage(),
                'per_page'     => $assessments->perPage(),
                'total'        => $assessments->total(),
                'last_page'    => $assessments->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single assessment.
     */
    public function show($id)
    {
        $assessment = Assessment::find($id);

        if (!$assessment) {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $assessment
        ], 200);
    }
}
