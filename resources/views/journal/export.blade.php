<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reflection Journal</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            padding: 30px;
            color: #222;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
        }

        .entry {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }

        .score-list {
            margin-top: 10px;
        }

        .score-list p {
            margin: 4px 0;
        }

        .date {
            margin-top: 15px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>

<h1>Alumable Reflection Journal</h1>

@forelse ($reflections as $reflection)

    <div class="entry">

        <h2>{{ $reflection->gig_title ?: 'Reflection #' . $reflection->id }}</h2>

        @if ($reflection->category)
            <p class="date">Category: {{ $reflection->category }}</p>
        @endif

        <p>
            <strong>Overall Score:</strong>
            {{ $reflection->score }}/5
        </p>

        @if ($reflection->comment)
            <p>
                <strong>Comment:</strong><br>
                {{ $reflection->comment }}
            </p>
        @endif

        @if ($reflection->scores)

            <h3>Competency Scores</h3>

            <div class="score-list">

                <p>
                    <strong>Contribution:</strong>
                    {{ $reflection->scores['contribution'] ?? '-' }}/5
                </p>

                <p>
                    <strong>Communication:</strong>
                    {{ $reflection->scores['communication'] ?? '-' }}/5
                </p>

                <p>
                    <strong>Collaboration:</strong>
                    {{ $reflection->scores['collaboration'] ?? '-' }}/5
                </p>

                <p>
                    <strong>Agile Improvement:</strong>
                    {{ $reflection->scores['agile'] ?? '-' }}/5
                </p>

                <p>
                    <strong>Continuous Improvement:</strong>
                    {{ $reflection->scores['continuous'] ?? '-' }}/5
                </p>

                <p>
                    <strong>Leadership & Initiative:</strong>
                    {{ $reflection->scores['leadership'] ?? '-' }}/5
                </p>

            </div>

        @endif

        @php($assessment = $reflection->assessments->first())

        <h3>Assessor Feedback</h3>

        @if ($assessment)

            <div class="score-list">

                @if ($assessment->assessor)
                    <p>
                        <strong>Assessed by:</strong>
                        {{ $assessment->assessor->name }}
                    </p>
                @endif

                <p>
                    <strong>Overall Score:</strong>
                    {{ $assessment->score }}/5
                </p>

                @if ($assessment->scores)

                    <p><strong>Contribution:</strong> {{ $assessment->scores['contribution'] ?? '-' }}/5</p>
                    <p><strong>Communication:</strong> {{ $assessment->scores['communication'] ?? '-' }}/5</p>
                    <p><strong>Collaboration:</strong> {{ $assessment->scores['collaboration'] ?? '-' }}/5</p>
                    <p><strong>Agile Improvement:</strong> {{ $assessment->scores['agile'] ?? '-' }}/5</p>
                    <p><strong>Continuous Improvement:</strong> {{ $assessment->scores['continuous'] ?? '-' }}/5</p>
                    <p><strong>Leadership & Initiative:</strong> {{ $assessment->scores['leadership'] ?? '-' }}/5</p>

                @endif

                @if ($assessment->feedback)
                    <p>
                        <strong>Feedback:</strong><br>
                        {{ $assessment->feedback }}
                    </p>
                @endif

            </div>

        @else

            <p>Not assessed yet.</p>

        @endif

        <p class="date">
            Created:
            {{ $reflection->created_at }}
        </p>

    </div>

@empty

    <p>No reflection entries found.</p>

@endforelse

</body>
</html>