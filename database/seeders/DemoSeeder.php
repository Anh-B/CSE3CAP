<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Reflection;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample data for demos.
 *
 * Creates 3 students + 1 assessor, 6 reflections (with the 6 competency
 * scores for the radar chart), and assessor feedback on 4 of them.
 * The other 2 have no assessment yet, so the demo can also show what
 * an entry waiting for review looks like.
 *
 * Run with:  php artisan db:seed --class=DemoSeeder
 * All demo accounts use the password: password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            'lan'   => User::factory()->create(['name' => 'Demo Student - Lan',   'email' => 'lan.demo@example.com']),
            'minh'  => User::factory()->create(['name' => 'Demo Student - Minh',  'email' => 'minh.demo@example.com']),
            'sam'   => User::factory()->create(['name' => 'Demo Student - Sam',   'email' => 'sam.demo@example.com']),
        ];

        $assessor = User::factory()->create(['name' => 'Demo Assessor', 'email' => 'assessor.demo@example.com']);

        // [student, overall score, comment, self scores, assessor feedback or null]
        $entries = [
            [
                'lan', 4,
                'Sprint 1 - set up the project board and helped split up the tasks. Took a while to get everyone using Jira properly, but by the end of the sprint the board was actually up to date.',
                ['contribution' => 4, 'communication' => 3, 'collaboration' => 4, 'agile' => 4, 'continuous' => 3, 'leadership' => 3],
                [4, 'Good start. Board was well organised. Try to speak up more in standups.',
                    ['contribution' => 4, 'communication' => 3, 'collaboration' => 4, 'agile' => 3, 'continuous' => 3, 'leadership' => 3]],
            ],
            [
                'lan', 4,
                'Sprint 2 - built the self-review scoring and validation. Had to redo the validation rules once I realised each competency needed its own 1-5 check, not just one overall score.',
                ['contribution' => 4, 'communication' => 4, 'collaboration' => 4, 'agile' => 3, 'continuous' => 4, 'leadership' => 3],
                [5, 'Caught the validation issue yourself before it became a bug - nice work.',
                    ['contribution' => 5, 'communication' => 4, 'collaboration' => 4, 'agile' => 4, 'continuous' => 4, 'leadership' => 4]],
            ],
            [
                'minh', 5,
                'Sprint 2 - connected the mock UI to the Laravel API and got the radar chart drawing from real data instead of hardcoded numbers.',
                ['contribution' => 5, 'communication' => 3, 'collaboration' => 4, 'agile' => 4, 'continuous' => 4, 'leadership' => 4],
                [4, 'Strong technical work. Keep the team updated a bit more often on what you are working on.',
                    ['contribution' => 5, 'communication' => 3, 'collaboration' => 3, 'agile' => 4, 'continuous' => 4, 'leadership' => 4]],
            ],
            [
                'minh', 4,
                'Sprint 3 - working on search and sorting for the entry list. Still testing it with bigger amounts of data before calling it done.',
                ['contribution' => 4, 'communication' => 4, 'collaboration' => 4, 'agile' => 4, 'continuous' => 4, 'leadership' => 3],
                null, // not reviewed yet
            ],
            [
                'sam', 3,
                'Sprint 2 - fixed a bunch of UI bugs and made the forms look consistent. Found it hard to estimate how long each fix would take.',
                ['contribution' => 3, 'communication' => 3, 'collaboration' => 4, 'agile' => 3, 'continuous' => 3, 'leadership' => 2],
                [3, 'Solid effort. Breaking bugs into smaller tickets would help with estimating.',
                    ['contribution' => 3, 'communication' => 3, 'collaboration' => 4, 'agile' => 3, 'continuous' => 4, 'leadership' => 2]],
            ],
            [
                'sam', 4,
                'Sprint 3 - tested the app on phone, tablet and laptop and fixed the layout issues. Took the lead on organising the bug list this time.',
                ['contribution' => 4, 'communication' => 4, 'collaboration' => 4, 'agile' => 3, 'continuous' => 4, 'leadership' => 4],
                null, // not reviewed yet
            ],
        ];

        foreach ($entries as [$studentKey, $score, $comment, $selfScores, $assessment]) {
            $reflection = Reflection::create([
                'user_id' => $students[$studentKey]->id,
                'score'   => $score,
                'comment' => $comment,
                'scores'  => $selfScores,
            ]);

            if ($assessment) {
                [$assessorScore, $feedback, $assessorScores] = $assessment;

                Assessment::create([
                    'reflection_id' => $reflection->id,
                    'assessor_id'   => $assessor->id,
                    'score'         => $assessorScore,
                    'feedback'      => $feedback,
                    'scores'        => $assessorScores,
                ]);
            }
        }
    }
}
