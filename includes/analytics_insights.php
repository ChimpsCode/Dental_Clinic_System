<?php
/** Plain-language summaries derived from the same values displayed in analytics. */
function clinicRevenueInsight(array $months, int $year): array
{
    $total = array_sum($months);
    $peak = $months ? max($months) : 0;
    if ($peak <= 0) {
        return ['description' => "No positive revenue is recorded in the displayed months for {$year}.",
            'recommendation' => 'There is not enough revenue data to identify a pattern. Check that billing records are complete before planning from this graph.'];
    }
    $leaders = [];
    foreach ($months as $month => $amount) {
        if (abs($amount - $peak) < 0.005) {
            $leaders[] = date('F', strtotime($month . '-01'));
        }
    }
    $names = implode(', ', $leaders);
    $peakText = count($leaders) === 1 ? "{$names} has the highest recorded revenue" : "{$names} share the highest recorded revenue";
    return ['description' => 'The displayed monthly revenue for ' . $year . ' totals ₱' . number_format($total, 2) . '. ' . $peakText . ' at ₱' . number_format($peak, 2) . '. These amounts are grouped by billing date; this graph covers the displayed year rather than the period filter.',
        'recommendation' => 'Review the services and collections contributing to the highest months when planning resources. Check that monthly records are complete before interpreting a lower point as a decline. The current month is still in progress, and future months are not plotted.'];
}

function clinicServiceInsight(array $services, int $total, string $range): array
{
    if ($total <= 0 || !$services) {
        return ['description' => "No service records are available for {$range}.",
            'recommendation' => 'There is not enough data to recommend appointment capacity by service. Review this graph when service records are available.'];
    }
    $peak = max(array_column($services, 'total'));
    $leaders = array_filter($services, static function ($row) use ($peak) { return (int)$row['total'] === (int)$peak; });
    $names = implode(', ', array_column($leaders, 'service_name'));
    $share = number_format(($peak / $total) * 100, 1);
    $leadText = count($leaders) === 1
        ? "{$names} is the most recorded service, with " . number_format($peak) . " records ({$share}%)."
        : "{$names} are tied as the most recorded services, each with " . number_format($peak) . " records ({$share}% each).";
    return ['description' => "For {$range}, the graph contains " . number_format($total) . ' service records. ' . $leadText . ' Counts combine appointment and queue records; they are not unique patients or completed treatments.',
        'recommendation' => 'Consider reviewing appointment slots and supplies for the most recorded services. Check upcoming bookings and dentist availability before changing capacity.'];
}

function clinicPeriodInsight(int $count, string $period, string $type, bool $available = true): array
{
    if (!$available) {
        return ['description' => 'Statistics could not be loaded.', 'recommendation' => 'Reload the page before using these figures to make decisions.'];
    }
    $ranges = ['today' => 'today', 'week' => 'this calendar week (Monday to Sunday)', 'month' => 'this calendar month', 'total' => 'across all recorded dates'];
    $range = $ranges[$period] ?? 'in this period';
    $isAppointment = $type === 'appointment';
    $description = $isAppointment
        ? number_format($count) . " non-archived appointments are scheduled {$range}. Counts include all appointment statuses, not only completed visits."
        : number_format($count) . " non-archived patient inquiries were received {$range}. This count does not show whether they resulted in bookings.";
    if ($count === 0) {
        $recommendation = 'No records are available for this period. Check that records are up to date before drawing conclusions about activity.';
    } elseif ($period === 'total') {
        $recommendation = 'Use the daily, weekly, and monthly figures for current planning. These periods overlap and should not be added together.';
    } elseif ($isAppointment) {
        $recommendation = 'Review appointment statuses and dentist availability for this period, and confirm upcoming visits with patients.';
    } else {
        $recommendation = 'Review unanswered inquiries and follow up with patients who still need assistance with booking.';
    }
    return ['description' => $description, 'recommendation' => $recommendation];
}
