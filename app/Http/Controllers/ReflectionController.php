<?php

namespace App\Http\Controllers;

use App\Models\Reflection;
use Illuminate\Http\Request;

class ReflectionController extends Controller
{
    public function store(Request $request)
    {
        // 1. Kiểm tra dữ liệu (score bắt buộc từ 1 đến 5)
        $validated = $request->validate([
            'score'   => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ], [
            'score.required' => 'Please select a self-review score.',
            'score.integer'  => 'The score must be an integer.',
            'score.min'      => 'The self-review score must be at least 1.',
            'score.max'      => 'The self-review score may not be greater than 5.',
            'comment.max'    => 'The comment may not be greater than 1000 characters.',
        ]);

        // 2. Lưu vào CSDL
        $reflection = Reflection::create([
            'user_id' => auth()->id() ?? null,
            'score'   => $validated['score'],
            'comment' => $validated['comment'] ?? null,
        ]);

        // 3. Trả về thông báo thành công
        return response()->json([
            'success' => true,
            'message' => 'Reflection score submitted successfully!',
            'data'    => $reflection,
        ], 201);
    }

    public function index()
    {
        $reflections = Reflection::latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $reflections,
        ]);
    }
}
