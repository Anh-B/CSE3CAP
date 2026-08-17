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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assessor feedback submitted successfully!',
            'data'    => $assessment
        ], 201);
    }
}
