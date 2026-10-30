<?php
$revenueNow = $revenueNow ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
$revenueMonth = $revenueNow->format('Y-m');
$revenueCompact = $revenueCompact ?? false;
$revenuePlotBottom = $revenueCompact ? 280 : 220;
$revenuePlotHeight = $revenueCompact ? 240 : 180;
$revenueSvgHeight = $revenueCompact ? 340 : 280;
$revenueMonthKeys = array_keys($allMonths);
$revenueMonthCount = count($revenueMonthKeys);
$revenueXStep = 628 / max(1, $revenueMonthCount - 1);
$displayedRevenueMonths = array_filter($allMonths, static fn($month) => $month <= $revenueMonth, ARRAY_FILTER_USE_KEY);
$revenueLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$revenuePeak = $displayedRevenueMonths ? max($displayedRevenueMonths) : 0;
$revenueTotalShown = array_sum($displayedRevenueMonths);
$revenuePeakMonths = array_keys(array_filter($displayedRevenueMonths, static fn($value) => $value > 0 && abs($value - $revenuePeak) < .005));
$revenuePositiveMonths = array_filter($displayedRevenueMonths, static fn($value) => $value > 0);
$revenueLatest = array_key_last($revenuePositiveMonths);
$revenueStepRaw = max($revenuePeak, 1000) / 4;
$revenueMagnitude = 10 ** floor(log10($revenueStepRaw));
$revenueRatio = $revenueStepRaw / $revenueMagnitude;
$revenueFactor = $revenueRatio <= 1 ? 1 : ($revenueRatio <= 2 ? 2 : ($revenueRatio <= 2.5 ? 2.5 : ($revenueRatio <= 5 ? 5 : 10)));
$revenueStep = $revenueFactor * $revenueMagnitude;
$revenueAxisMax = ceil(max($revenuePeak, 1000) / $revenueStep) * $revenueStep;
$revenuePoints = [];
foreach ($displayedRevenueMonths as $month => $amount) {
    $index = array_search($month, $revenueMonthKeys, true);
    $revenuePoints[] = [72 + $index * $revenueXStep, $revenuePlotBottom - ((float)$amount / $revenueAxisMax) * $revenuePlotHeight, $month, (float)$amount];
}
$revenuePointString = implode(' ', array_map(static fn($point) => round($point[0], 2) . ',' . round($point[1], 2), $revenuePoints));
$revenueMoney = static fn($value) => '₱' . number_format((float)$value, 2);
$revenueEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div id="analyticsRevenueLine" class="analytics-revenue-line<?php echo $revenueCompact ? ' revenue-line-compact' : ''; ?>" data-compact="<?php echo $revenueCompact ? 'true' : 'false'; ?>">
    <?php if (!$revenueCompact): ?>
    <p class="revenue-measure">Paid amounts grouped by billing month · Full-year view, independent of the period filter</p>
    <div class="revenue-line-summary">
        <div><span><?php echo $chartYear === (int)$revenueNow->format('Y') ? 'Collected this year' : 'Collected in ' . $chartYear; ?></span><strong><?php echo $revenueMoney($revenueTotalShown); ?></strong><small>By billing date</small></div>
        <div><span>Highest month</span><strong><?php echo $revenuePeak > 0 ? $revenueMoney($revenuePeak) : '—'; ?></strong><small><?php echo $revenuePeakMonths ? $revenueEscape(implode(', ', array_map(static fn($month) => date('M', strtotime($month . '-01')), $revenuePeakMonths))) : 'No revenue recorded'; ?></small></div>
        <div><span>Latest recorded revenue</span><strong><?php echo $revenueLatest ? $revenueMoney($displayedRevenueMonths[$revenueLatest]) : '—'; ?></strong><small><?php echo $revenueLatest ? $revenueEscape(date('M Y', strtotime($revenueLatest . '-01')) . ($revenueLatest === $revenueMonth ? ' · Month to date' : '')) : 'No revenue recorded'; ?></small></div>
    </div>
    <?php endif; ?>
    <div class="revenue-plot-scroll">
        <svg viewBox="0 0 720 <?php echo $revenueSvgHeight; ?>" role="group" aria-labelledby="revenueLineTitle revenueLineDesc">
            <title id="revenueLineTitle">Monthly revenue for the displayed period</title>
            <desc id="revenueLineDesc">Monthly paid amounts grouped by billing date. Select a monthly point for its exact peso amount and change from the preceding month. Future months have no plotted points.</desc>
            <text x="72" y="18" class="revenue-axis-caption">Revenue (₱)</text>
            <?php if (count($revenuePoints) < $revenueMonthCount): $futureX = max(72, 72 + (count($revenuePoints) - .5) * $revenueXStep); ?>
                <rect x="<?php echo round($futureX, 2); ?>" y="30" width="<?php echo round(708 - $futureX, 2); ?>" height="<?php echo $revenuePlotBottom + 4; ?>" fill="#f8fafc" rx="6"/>
                <text x="<?php echo round(($futureX + 708) / 2, 2); ?>" y="31" text-anchor="middle" class="revenue-future-label">Future</text>
            <?php endif; ?>
            <?php for ($tick = 0; $tick <= (int)round($revenueAxisMax / $revenueStep); $tick++):
                $value = $tick * $revenueStep; $y = $revenuePlotBottom - $value / $revenueAxisMax * $revenuePlotHeight;
                $tickText = $value >= 1000000 ? '₱' . number_format($value / 1000000, 1) . 'm' : ($value >= 1000 ? '₱' . number_format($value / 1000, $value % 1000 === 0 ? 0 : 1) . 'k' : '₱' . number_format($value));
            ?>
                <line x1="72" x2="700" y1="<?php echo round($y,2); ?>" y2="<?php echo round($y,2); ?>" stroke="#e2e8f0" stroke-dasharray="<?php echo $tick ? '4 5' : '0'; ?>"/>
                <text x="61" y="<?php echo round($y + 4,2); ?>" text-anchor="end" class="revenue-axis-label"><?php echo $tickText; ?></text>
            <?php endfor; ?>
            <?php if ($revenuePoints): ?>
                <polygon points="<?php echo $revenuePointString . ' ' . round($revenuePoints[count($revenuePoints)-1][0],2) . ',' . $revenuePlotBottom . ' 72,' . $revenuePlotBottom; ?>" fill="#2563eb" fill-opacity=".06"/>
                <polyline points="<?php echo $revenuePointString; ?>" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <?php endif; ?>
            <?php foreach ($revenueMonthKeys as $index => $month): $label = $revenueLabels[(int)substr($month,5,2)-1]; ?>
                <text x="<?php echo round(72 + $index * $revenueXStep,2); ?>" y="<?php echo $revenuePlotBottom + 28; ?>" text-anchor="middle" class="revenue-axis-label<?php echo $month > $revenueMonth ? ' future' : ''; ?>"><?php echo $label . (!$revenueCompact && $month === $revenueMonth ? '*' : ''); ?></text>
            <?php endforeach; ?>
            <?php foreach ($revenuePoints as $index => [$x,$y,$month,$amount]):
                $name = date('F Y', strtotime($month . '-01')) . ($month === $revenueMonth ? ' · Month to date' : '');
                $previous = $index > 0 ? $revenuePoints[$index-1][3] : null;
                if ($previous === null) $change = 'No preceding month in the displayed period to compare.';
                else {
                    $difference = $amount - $previous;
                    $change = abs($difference) < .005 ? 'No change from the previous month.' : ($difference > 0 ? '+' : '−') . $revenueMoney(abs($difference)) . ($previous > 0 ? ' (' . ($difference > 0 ? '+' : '−') . number_format(abs($difference) / $previous * 100, 1) . '%)' : '') . ' from the previous month.';
                    if ($previous == 0 && $amount > 0) $change .= ' Previous month: ₱0.00; percentage change is not available.';
                }
                if ($month === $revenueMonth) $change .= ' This month is incomplete; compare with care.';
            ?>
                <g class="revenue-month-point" tabindex="0" role="button" aria-label="<?php echo $revenueEscape($name . ': ' . $revenueMoney($amount) . '. ' . $change); ?>" data-revenue-month="<?php echo $month; ?>" data-label="<?php echo $revenueEscape($name); ?>" data-amount="<?php echo $revenueEscape($revenueMoney($amount)); ?>" data-change="<?php echo $revenueEscape($change); ?>">
                    <title><?php echo $revenueEscape($name . ': ' . $revenueMoney($amount)); ?></title>
                    <circle cx="<?php echo round($x,2); ?>" cy="<?php echo round($y,2); ?>" r="14" fill="transparent"/>
                    <circle class="revenue-point-dot" cx="<?php echo round($x,2); ?>" cy="<?php echo round($y,2); ?>" r="4.5" fill="#fff" stroke="<?php echo $amount > 0 ? '#2563eb' : '#94a3b8'; ?>" stroke-width="2.5"/>
                    <?php if ($revenuePeak > 0 && abs($amount - $revenuePeak) < .005): ?><text x="<?php echo round($x,2); ?>" y="<?php echo round($y-13,2); ?>" text-anchor="middle" class="revenue-peak-label">Peak</text><?php endif; ?>
                </g>
            <?php endforeach; ?>
        </svg>
    </div>
    <?php if (!$revenueCompact): ?><p class="revenue-line-legend"><span aria-hidden="true"></span>Monthly paid amounts <small>* Current month is month to date. Future months are not plotted.</small></p><?php endif; ?>
    <div class="revenue-point-details" aria-live="polite" aria-atomic="true"<?php echo $revenueCompact ? ' hidden' : ''; ?>><div><strong data-revenue-detail-label>Monthly details</strong><b data-revenue-detail-amount></b></div><p data-revenue-detail-change>Hover, tap or focus a dot to view its amount and monthly change.</p></div>
    <?php if (!$revenueCompact && $revenuePeak <= 0): ?><p class="revenue-empty-note">No positive revenue has been recorded for the elapsed months. Zero points indicate no recorded paid amount.</p><?php endif; ?>
</div>
<style>
.revenue-line-card { min-width:0; }
.analytics-revenue-line { color:#334155; }
.analytics-revenue-line.revenue-line-compact { position:relative; }
.revenue-line-compact .revenue-point-details { position:absolute; right:12px; bottom:46px; max-width:min(320px,calc(100% - 24px)); box-sizing:border-box; box-shadow:0 8px 24px #0f172a18; z-index:2; }
.analytics-revenue-line.revenue-line-compact svg { min-width:360px; }
.analytics-revenue-line .revenue-measure { color:#64748b; font-size:12px; line-height:1.6; margin:0 0 16px; }
.analytics-revenue-line .revenue-line-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:16px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; }
.analytics-revenue-line .revenue-line-summary span { display:block; font-size:11px; color:#64748b; }
.analytics-revenue-line .revenue-line-summary strong { display:block; margin-top:5px; color:#0f172a; font-size:17px; font-weight:700; overflow-wrap:anywhere; }
.analytics-revenue-line .revenue-line-summary small { display:block; margin-top:3px; color:#64748b; font-size:11px; }
.analytics-revenue-line .revenue-plot-scroll { overflow-x:auto; }
.analytics-revenue-line svg { display:block; width:100%; min-width:540px; font-family:inherit; }
.analytics-revenue-line .revenue-axis-label { fill:#64748b; font-size:12px; }
.analytics-revenue-line .revenue-axis-caption { fill:#475569; font-size:11px; font-weight:600; }
.analytics-revenue-line .revenue-axis-label.future,.analytics-revenue-line .revenue-future-label { fill:#94a3b8; font-size:11px; }
.analytics-revenue-line .revenue-peak-label { fill:#1d4ed8; font-size:10px; font-weight:700; }
.analytics-revenue-line.revenue-line-compact .revenue-axis-label { fill:#475569; font-size:18px; font-weight:500; }
.analytics-revenue-line.revenue-line-compact .revenue-axis-caption { font-size:15px; }
.analytics-revenue-line.revenue-line-compact .revenue-peak-label { font-size:14px; }
.analytics-revenue-line .revenue-month-point { cursor:pointer; outline:none; }
.analytics-revenue-line .revenue-month-point:hover .revenue-point-dot,.analytics-revenue-line .revenue-month-point:focus .revenue-point-dot,.analytics-revenue-line .revenue-month-point.is-selected .revenue-point-dot { fill:#2563eb; stroke:#bfdbfe; stroke-width:4; }
.analytics-revenue-line .revenue-line-legend { display:flex; flex-wrap:wrap; align-items:center; gap:7px; margin:0 0 14px; font-size:11px; color:#475569; }
.analytics-revenue-line .revenue-line-legend > span { width:18px; height:3px; border-radius:2px; background:#2563eb; }
.analytics-revenue-line .revenue-line-legend small { flex:1 1 100%; color:#64748b; font-size:11px; }
.analytics-revenue-line .revenue-point-details { border:1px solid #dbeafe; border-radius:10px; padding:12px 14px; background:#f8fbff; }
.analytics-revenue-line .revenue-point-details > div { display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px; }
.analytics-revenue-line .revenue-point-details strong { color:#334155; font-size:12px; }
.analytics-revenue-line .revenue-point-details b { color:#1d4ed8; font-size:14px; }
.analytics-revenue-line .revenue-point-details p,.analytics-revenue-line .revenue-empty-note { margin:6px 0 0; font-size:12px; line-height:1.6; color:#64748b; }
@media(max-width:600px) { .analytics-revenue-line .revenue-line-summary { gap:8px; padding:10px; } .analytics-revenue-line .revenue-line-summary strong { font-size:14px; } }
</style>
<script>
(function () {
    const root = document.getElementById('analyticsRevenueLine');
    if (!root) return;
    const points = Array.from(root.querySelectorAll('[data-revenue-month]'));
    const compact = root.dataset.compact === 'true';
    const details = root.querySelector('.revenue-point-details');
    function select(point) {
        details.hidden = false;
        points.forEach(item => item.classList.toggle('is-selected', item === point));
        root.querySelector('[data-revenue-detail-label]').textContent = point.dataset.label;
        root.querySelector('[data-revenue-detail-amount]').textContent = point.dataset.amount;
        root.querySelector('[data-revenue-detail-change]').textContent = point.dataset.change;
    }
    points.forEach((point, index) => {
        ['mouseenter','focus','click'].forEach(event => point.addEventListener(event, () => select(point)));
        point.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(point); }
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault(); points[Math.max(0, Math.min(points.length - 1, index + (event.key === 'ArrowRight' ? 1 : -1)))].focus();
            }
        });
    });
    if (compact) {
        root.addEventListener('mouseleave', () => { details.hidden = true; });
        root.addEventListener('focusout', event => { if (!root.contains(event.relatedTarget)) details.hidden = true; });
    } else if (points.length) select(points[points.length - 1]);
})();
</script>
