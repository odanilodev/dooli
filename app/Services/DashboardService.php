<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\UserTypeCode;
use App\Models\CertificateUser;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Refund;
use App\Models\Team;
use App\Models\TeamCourse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Build the administrative dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function overview(string $period = '30', ?string $from = null, ?string $to = null): array
    {
        $range = $this->resolveRange($period, $from, $to);
        $start = $range['from'];
        $end = $range['to'];
        $granularity = $range['granularity'];

        $paidStatuses = [PaymentStatus::Paid->value, PaymentStatus::Refunded->value];

        $payments = $this->applyRange(
            Payment::query()->whereIn('status', $paidStatuses),
            'paid_at',
            $start,
            $end,
        )->get(['amount', 'paid_at']);

        $refunds = $this->applyRange(
            Refund::query()->where('status', RefundStatus::Refunded->value),
            'refunded_at',
            $start,
            $end,
        )->get(['amount', 'refunded_at']);

        $ordersForSeries = $this->applyRange(Order::query(), 'created_at', $start, $end)->get(['created_at']);

        $revenue = round(
            $payments->sum(fn ($payment) => (float) $payment->amount)
            - $refunds->sum(fn ($refund) => (float) $refund->amount),
            2,
        );

        $ordersPaid = $this->applyRange(
            Order::query()->where('status', OrderStatus::Paid->value),
            'created_at',
            $start,
            $end,
        )->count();

        $ordersTotal = $this->applyRange(Order::query(), 'created_at', $start, $end)->count();

        return [
            'period' => $range['mode'] === 'custom' ? 'custom' : $period,
            'range' => [
                'from' => $start?->toDateString(),
                'to' => $end?->toDateString(),
            ],
            'kpis' => $this->kpis($start, $end, $revenue, $ordersPaid, $ordersTotal),
            'revenue_series' => $this->revenueSeries($granularity, $start, $end, $payments, $refunds, $ordersForSeries),
            'order_status' => $this->orderStatus($start, $end),
            'top_courses' => $this->topCourses($start, $end),
            'top_buyers' => $this->topBuyers($start, $end),
            'recent_orders' => $this->recentOrders(),
            'recent_students' => $this->recentStudents(),
            'students_per_course' => $this->studentsPerCourse(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function kpis(?Carbon $from, ?Carbon $to, float $revenue, int $ordersPaid, int $ordersTotal): array
    {
        $students = User::query()->whereHas('userType', fn ($query) => $query->where('code', UserTypeCode::Student->value));

        $studentsTotal = (clone $students)->count();
        $studentsActive = (clone $students)->where('status', GeneralStatus::Active->value)->count();
        $studentsNew = $from
            ? $this->applyRange(clone $students, 'created_at', $from, $to)->count()
            : $studentsTotal;

        return [
            'students_total' => $studentsTotal,
            'students_active' => $studentsActive,
            'students_new' => $studentsNew,
            'revenue' => $revenue,
            'orders_paid' => $ordersPaid,
            'orders_total' => $ordersTotal,
            'avg_ticket' => $ordersPaid > 0 ? round($revenue / $ordersPaid, 2) : 0.0,
            'courses_total' => Course::query()->count(),
            'courses_published' => Course::query()->where('status', GeneralStatus::Active->value)->count(),
            'teams_active' => Team::query()->where('status', GeneralStatus::Active->value)->count(),
            'certificates_valid' => CertificateUser::query()->whereNull('invalidated_at')->count(),
            'rating_avg' => round((float) Rating::query()->avg('rating'), 2),
            'ratings_count' => Rating::query()->count(),
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Refund>  $refunds
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function revenueSeries(
        string $granularity,
        ?Carbon $from,
        ?Carbon $to,
        $payments,
        $refunds,
        $orders,
    ): array {
        $buckets = $this->buildBuckets($granularity, $from, $to);

        if (empty($buckets)) {
            return [];
        }

        $index = [];
        foreach ($buckets as $position => $bucket) {
            $index[$bucket['key']] = $position;
        }

        foreach ($payments as $payment) {
            if ($payment->paid_at === null) {
                continue;
            }

            $key = $this->bucketKey($payment->paid_at, $granularity);

            if (isset($index[$key])) {
                $buckets[$index[$key]]['revenue'] += (float) $payment->amount;
            }
        }

        foreach ($refunds as $refund) {
            if ($refund->refunded_at === null) {
                continue;
            }

            $key = $this->bucketKey($refund->refunded_at, $granularity);

            if (isset($index[$key])) {
                $buckets[$index[$key]]['revenue'] -= (float) $refund->amount;
            }
        }

        foreach ($orders as $order) {
            $key = $this->bucketKey($order->created_at, $granularity);

            if (isset($index[$key])) {
                $buckets[$index[$key]]['orders']++;
            }
        }

        return array_map(function (array $bucket): array {
            $bucket['revenue'] = round($bucket['revenue'], 2);

            return $bucket;
        }, $buckets);
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function orderStatus(?Carbon $from, ?Carbon $to): array
    {
        $counts = $this->applyRange(Order::query(), 'created_at', $from, $to)
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'status');

        return array_map(
            fn (OrderStatus $status) => [
                'status' => $status->value,
                'count' => (int) ($counts[$status->value] ?? 0),
            ],
            OrderStatus::cases(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topCourses(?Carbon $from, ?Carbon $to): array
    {
        $rows = $this->applyRange(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('store_products', 'store_products.id', '=', 'order_items.store_product_id')
                ->where('orders.status', OrderStatus::Paid->value)
                ->whereNotNull('store_products.course_id'),
            'orders.created_at',
            $from,
            $to,
        )
            ->groupBy('store_products.course_id')
            ->orderByDesc('sales')
            ->limit(5)
            ->get([
                'store_products.course_id as course_id',
                DB::raw('SUM(order_items.quantity) as sales'),
                DB::raw('SUM(order_items.total) as revenue'),
            ]);

        $courses = Course::query()
            ->whereIn('id', $rows->pluck('course_id'))
            ->get(['id', 'name', 'image'])
            ->keyBy('id');

        return $rows
            ->filter(fn ($row) => $courses->has($row->course_id))
            ->map(fn ($row) => [
                'id' => (int) $row->course_id,
                'name' => $courses[$row->course_id]->name,
                'image' => $courses[$row->course_id]->image,
                'sales' => (int) $row->sales,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topBuyers(?Carbon $from, ?Carbon $to): array
    {
        $rows = $this->applyRange(
            Order::query()
                ->join('users', 'users.id', '=', 'orders.user_id')
                ->join('user_types', 'user_types.id', '=', 'users.user_type_id')
                ->where('orders.status', OrderStatus::Paid->value)
                ->where('user_types.code', UserTypeCode::Student->value),
            'orders.created_at',
            $from,
            $to,
        )
            ->groupBy('orders.user_id')
            ->orderByRaw('SUM(orders.total) DESC')
            ->limit(5)
            ->get([
                'orders.user_id as user_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(orders.total) as total'),
            ]);

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->get(['id', 'name', 'email', 'image'])
            ->keyBy('id');

        return $rows
            ->filter(fn ($row) => $users->has($row->user_id))
            ->map(fn ($row) => [
                'id' => (int) $row->user_id,
                'name' => $users[$row->user_id]->name,
                'email' => $users[$row->user_id]->email,
                'image' => $users[$row->user_id]->image,
                'orders_count' => (int) $row->orders_count,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::query()
            ->with('user:id,name,email,image')
            ->latest()
            ->limit(8)
            ->get(['id', 'user_id', 'status', 'total', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'user_name' => $order->user?->name,
                'user_email' => $order->user?->email,
                'status' => $order->status,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentStudents(): array
    {
        return User::query()
            ->whereHas('userType', fn ($query) => $query->where('code', UserTypeCode::Student->value))
            ->latest()
            ->limit(8)
            ->get(['id', 'name', 'email', 'image', 'status', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentsPerCourse(): array
    {
        $rows = TeamCourse::query()
            ->join('team_users', 'team_users.team_id', '=', 'team_courses.team_id')
            ->where('team_courses.status', GeneralStatus::Active->value)
            ->where('team_users.status', GeneralStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('team_users.expired_at')
                    ->orWhere('team_users.expired_at', '>', now());
            })
            ->groupBy('team_courses.course_id')
            ->orderByDesc('students_count')
            ->limit(5)
            ->get([
                'team_courses.course_id as course_id',
                DB::raw('COUNT(DISTINCT team_users.user_id) as students_count'),
            ]);

        $courses = Course::query()
            ->whereIn('id', $rows->pluck('course_id'))
            ->get(['id', 'name', 'image'])
            ->keyBy('id');

        return $rows
            ->filter(fn ($row) => $courses->has($row->course_id))
            ->map(fn ($row) => [
                'id' => (int) $row->course_id,
                'name' => $courses[$row->course_id]->name,
                'image' => $courses[$row->course_id]->image,
                'students_count' => (int) $row->students_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve the effective date window and chart granularity.
     *
     * @return array{mode: string, from: ?Carbon, to: ?Carbon, granularity: string}
     */
    private function resolveRange(string $period, ?string $from, ?string $to): array
    {
        if ($from !== null && $to !== null) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
            $days = (int) $start->diffInDays($end) + 1;

            return [
                'mode' => 'custom',
                'from' => $start,
                'to' => $end,
                'granularity' => match (true) {
                    $days <= 31 => 'daily',
                    $days <= 120 => 'weekly',
                    default => 'monthly',
                },
            ];
        }

        $start = match ($period) {
            '7' => now()->subDays(6)->startOfDay(),
            '30' => now()->subDays(29)->startOfDay(),
            '90' => now()->subDays(89)->startOfDay(),
            default => null,
        };

        return [
            'mode' => $start === null ? 'all' : 'preset',
            'from' => $start,
            'to' => $start === null ? null : now()->endOfDay(),
            'granularity' => match (true) {
                $start === null => 'monthly',
                $period === '90' => 'weekly',
                default => 'daily',
            },
        ];
    }

    private function applyRange(Builder $query, string $column, ?Carbon $from, ?Carbon $to): Builder
    {
        return $query
            ->when($from, fn (Builder $inner) => $inner->where($column, '>=', $from))
            ->when($to, fn (Builder $inner) => $inner->where($column, '<=', $to));
    }

    /**
     * @return array<int, array{key: string, label: string, revenue: float, orders: int}>
     */
    private function buildBuckets(string $granularity, ?Carbon $from, ?Carbon $to): array
    {
        $end = ($to ?? now())->copy()->endOfDay();

        if ($granularity === 'monthly') {
            $start = ($from ?? $this->earliestActivity($end))->copy()->startOfMonth();

            return $this->eachBucket($start, $end, 'month', fn (Carbon $cursor) => [
                'key' => 'M'.$cursor->format('Y-m'),
                'label' => $this->monthLabel($cursor),
            ]);
        }

        $start = $from->copy()->startOfDay();

        if ($granularity === 'weekly') {
            return $this->eachBucket($start, $end, 'week', fn (Carbon $cursor) => [
                'key' => 'W'.$cursor->format('o-W'),
                'label' => $cursor->format('d/m'),
            ]);
        }

        return $this->eachBucket($start, $end, 'day', fn (Carbon $cursor) => [
            'key' => $cursor->format('Y-m-d'),
            'label' => $cursor->format('d/m'),
        ]);
    }

    /**
     * @param  callable(Carbon): array{key: string, label: string}  $factory
     * @return array<int, array{key: string, label: string, revenue: float, orders: int}>
     */
    private function eachBucket(Carbon $start, Carbon $end, string $unit, callable $factory): array
    {
        $buckets = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $buckets[] = $factory($cursor) + ['revenue' => 0.0, 'orders' => 0];

            match ($unit) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }

    private function earliestActivity(Carbon $fallback): Carbon
    {
        $dates = array_filter([
            Payment::query()->min('paid_at'),
            Refund::query()->min('refunded_at'),
            Order::query()->min('created_at'),
        ]);

        if (empty($dates)) {
            return $fallback->copy()->startOfMonth();
        }

        return Carbon::parse(min($dates));
    }

    private function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'weekly' => 'W'.$date->format('o-W'),
            'monthly' => 'M'.$date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    private function monthLabel(Carbon $date): string
    {
        return $date->format('m/Y');
    }
}
