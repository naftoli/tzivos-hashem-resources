<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentView;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Who opened which page or file. HQ admin only. */
class StatsController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = $this->query($from, $to);

        return view('admin.stats', [
            'from' => $from?->timezone(config('admin.timezone'))->toDateString(),
            'to' => $to?->timezone(config('admin.timezone'))->toDateString(),
            'pages' => $this->top($rows, 'page'),
            'files' => $this->top($rows, 'file'),
            'people' => $this->people($rows),
            'total' => (clone $rows)->count(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $tz = config('admin.timezone');
        $filename = 'resources-stats.csv';

        return response()->streamDownload(function () use ($from, $to, $tz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Who', 'Level', 'Kind', 'What']);
            $this->query($from, $to)->orderBy('id')->chunk(500, function ($views) use ($out, $tz) {
                foreach ($views as $view) {
                    fputcsv($out, [
                        $view->created_at->timezone($tz)->format('Y-m-d H:i'),
                        $view->email,
                        $view->role,
                        $view->kind,
                        $view->label,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function range(Request $request): array
    {
        $tz = config('admin.timezone');
        $from = $request->filled('from') ? Carbon::parse($request->input('from'), $tz)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'), $tz)->endOfDay() : null;

        return [$from, $to];
    }

    private function query(?Carbon $from, ?Carbon $to): Builder
    {
        return ContentView::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function top(Builder $rows, string $kind)
    {
        return (clone $rows)->where('kind', $kind)
            ->selectRaw('label, count(*) as opens')
            ->groupBy('label')
            ->orderByDesc('opens')
            ->orderBy('label')
            ->limit(20)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function people(Builder $rows)
    {
        $people = (clone $rows)
            ->selectRaw('email, count(*) as opens, max(id) as last_id, max(created_at) as last_at')
            ->groupBy('email')
            ->orderByDesc('opens')
            ->orderBy('email')
            ->get();
        $roles = ContentView::whereIn('id', $people->pluck('last_id'))->pluck('role', 'id');
        foreach ($people as $person) {
            $person->role = $roles[$person->last_id] ?? '';
        }

        return $people;
    }
}
