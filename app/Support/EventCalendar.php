<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One month of events laid out as a Monday-first grid, in Nigerian time. The grid includes the
 * days before and after the month that complete its first and last weeks, and their events.
 */
final class EventCalendar
{
    /**
     * @param  list<list<array{date: string, day: int, inMonth: bool, isToday: bool, isPast: bool, events: Collection<int, Event>}>>  $weeks
     * @param  Collection<int, Event>  $monthEvents
     */
    private function __construct(
        public readonly CarbonImmutable $month,
        public readonly array $weeks,
        public readonly Collection $monthEvents,
    ) {}

    /**
     * @param  Builder<Event>  $events  Already scoped to what the viewer may see.
     * @param  string|null  $month  "YYYY-MM"; anything else means the current month.
     */
    public static function for(Builder $events, ?string $month): self
    {
        $tz = config('membership.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $first = self::parseMonth($month, $tz) ?? $today->startOfMonth();

        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addDay()->startOfDay();

        $found = (clone $events)
            ->where('starts_at', '>=', $gridStart->utc())
            ->where('starts_at', '<', $gridEnd->utc())
            ->with('state:id,name')
            ->orderBy('starts_at')
            ->get();
        $byDay = $found->groupBy(fn (Event $e) => $e->starts_at->setTimezone($tz)->format('Y-m-d'));

        $weeks = [];
        for ($day = $gridStart; $day < $gridEnd; $day = $day->addDay()) {
            $weeks[intdiv((int) $gridStart->diffInDays($day), 7)][] = [
                'date' => $day->format('Y-m-d'),
                'day' => $day->day,
                'inMonth' => $day->month === $first->month,
                'isToday' => $day->equalTo($today),
                'isPast' => $day < $today,
                'events' => $byDay->get($day->format('Y-m-d'), collect()),
            ];
        }

        $monthEvents = $found->filter(fn (Event $e) => $e->starts_at->setTimezone($tz)->format('Y-m') === $first->format('Y-m'))->values();

        return new self($first, $weeks, $monthEvents);
    }

    public function previous(): string
    {
        return $this->month->subMonth()->format('Y-m');
    }

    public function next(): string
    {
        return $this->month->addMonth()->format('Y-m');
    }

    public function isCurrentMonth(): bool
    {
        return $this->month->format('Y-m') === CarbonImmutable::now(config('membership.timezone'))->format('Y-m');
    }

    private static function parseMonth(?string $month, string $tz): ?CarbonImmutable
    {
        if (! is_string($month) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m) || $m[1] < 2020 || $m[1] > 2100) {
            return null;
        }

        return CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $tz);
    }
}
