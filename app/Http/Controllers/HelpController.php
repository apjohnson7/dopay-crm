<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** The in-app guide (same content as "Ask Dopay" in the prototype). */
class HelpController extends Controller
{
    public function __invoke(Request $request)
    {
        $topics = require resource_path('help/topics.php');
        $q = mb_strtolower(trim((string) $request->query('q')));
        if ($q !== '') {
            $topics = collect($topics)->map(function ($t) use ($q) {
                $score = collect($t['keywords'])->sum(fn ($k) => str_contains(" $q ", " $k") ? (str_contains($k, ' ') ? 3 : 1) : 0);

                return $t + ['score' => $score];
            })->filter(fn ($t) => $t['score'] > 0)->sortByDesc('score')->values()->all();
        }

        return view('help.index', ['topics' => $topics, 'q' => $request->query('q')]);
    }
}
