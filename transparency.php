<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Daily stats (last 30 days)
$query = "SELECT DATE(created_at) as scan_date,
          COUNT(*) as total_scans,
          SUM(CASE WHEN risk_level IN ('high','critical') THEN 1 ELSE 0 END) as high_risk,
          SUM(CASE WHEN action_taken = 'block' THEN 1 ELSE 0 END) as blocked,
          AVG(risk_score) as avg_risk_score
          FROM ai_moderation_logs
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY DATE(created_at)
          ORDER BY scan_date DESC";
$stmt = $db->query($query);
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Category breakdown (top violation types)
$categoryQuery = "SELECT categories_flagged, COUNT(*) as count
                  FROM ai_moderation_logs
                  WHERE categories_flagged IS NOT NULL
                    AND categories_flagged != '[]'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  LIMIT 200";
$catStmt = $db->query($categoryQuery);
$categoryData = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryCount = [];
foreach ($categoryData as $row) {
    $cats = json_decode($row['categories_flagged'], true);
    if ($cats && is_array($cats)) {
        foreach ($cats as $c) {
            $categoryCount[$c] = ($categoryCount[$c] ?? 0) + 1;
        }
    }
}
arsort($categoryCount);

// Aggregate stats
$aggregateQuery = "SELECT 
                   COUNT(*) as total_scans,
                   SUM(CASE WHEN risk_level IN ('high','critical') THEN 1 ELSE 0 END) as high_risk,
                   SUM(CASE WHEN action_taken = 'block' THEN 1 ELSE 0 END) as blocked,
                   AVG(risk_score) as avg_risk_score,
                   COUNT(DISTINCT user_id) as users_protected
                   FROM ai_moderation_logs
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$aggStmt = $db->query($aggregateQuery);
$aggregate = $aggStmt->fetch(PDO::FETCH_ASSOC);

// Content type breakdown
$typeQuery = "SELECT content_type, COUNT(*) as count
              FROM ai_moderation_logs
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY content_type
              ORDER BY count DESC";
$typeStmt = $db->query($typeQuery);
$contentTypes = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="min-h-screen bg-gh-bg">
    <!-- Hero -->
    <div class="border-b border-gh-border bg-gradient-to-br from-gh-panel via-gh-bg to-gh-panel">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16 text-center">
            <div class="flex items-center justify-center gap-4 mb-6">
                <img src="uploads/trubl_logo.jpeg" alt="Trubl" class="h-16 w-16 rounded-xl object-cover shadow-lg">
                <div class="h-12 w-px bg-gh-border"></div>
                <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-pink-600 to-purple-600 shadow-lg">
                    <i class="bi bi-robot text-3xl text-white"></i>
                </div>
            </div>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-white mb-4">Content Safety Transparency</h1>
            <p class="text-lg text-gh-muted max-w-3xl mx-auto mb-6">
                Real-time insights into how AI keeps listings, forums, stories, and private messages safe.
            </p>
            <div class="inline-flex items-center gap-3 rounded-full border border-purple-500/30 bg-purple-500/10 px-6 py-3">
                <i class="bi bi-shield-check text-2xl text-purple-400"></i>
                <div class="text-left">
                    <p class="text-xs font-semibold uppercase tracking-wide text-purple-400">Powered by</p>
                    <p class="text-sm font-bold text-white">Perplexity Sonar Pro AI</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary stats -->
    <div class="border-b border-gh-border bg-gh-panel">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
            <div>
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-blue-500/10 mb-4">
                    <i class="bi bi-search text-3xl text-blue-400"></i>
                </div>
                <h3 class="text-4xl font-bold text-white mb-1"><?php echo number_format($aggregate['total_scans']); ?></h3>
                <p class="text-sm font-semibold text-gh-muted">Total content scans</p>
                <p class="text-xs text-gh-muted mt-1">Last 30 days</p>
            </div>
            <div>
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-500/10 mb-4">
                    <i class="bi bi-shield-fill-check text-3xl text-green-400"></i>
                </div>
                <h3 class="text-4xl font-bold text-green-400 mb-1">
                    <?php
                    $approvedRate = $aggregate['total_scans'] > 0
                        ? (($aggregate['total_scans'] - $aggregate['high_risk']) / $aggregate['total_scans']) * 100
                        : 0;
                    echo number_format($approvedRate, 1);
                    ?>%
                </h3>
                <p class="text-sm font-semibold text-gh-muted">Content auto‑approved</p>
                <p class="text-xs text-gh-muted mt-1">Low‑risk only</p>
            </div>
            <div>
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-red-500/10 mb-4">
                    <i class="bi bi-shield-x text-3xl text-red-400"></i>
                </div>
                <h3 class="text-4xl font-bold text-red-400 mb-1"><?php echo number_format($aggregate['blocked']); ?></h3>
                <p class="text-sm font-semibold text-gh-muted">Threats auto‑blocked</p>
                <p class="text-xs text-gh-muted mt-1">Before anyone saw them</p>
            </div>
            <div>
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-purple-500/10 mb-4">
                    <i class="bi bi-people-fill text-3xl text-purple-400"></i>
                </div>
                <h3 class="text-4xl font-bold text-purple-400 mb-1"><?php echo number_format($aggregate['users_protected']); ?></h3>
                <p class="text-sm font-semibold text-gh-muted">Users protected</p>
                <p class="text-xs text-gh-muted mt-1">With active monitoring</p>
            </div>
        </div>
    </div>

    <!-- What we scan -->
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb  -8">
            <h2 class="text-3xl font-bold text-white mb-3">What content is scanned?</h2>
            <p class="text-lg text-gh-muted">AI checks every new listing, forum post, story, and private message in real‑time.</p>
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="group rounded-xl border border-gh-border bg-gh-panel p-6 hover:border-blue-500/50 hover:shadow-lg hover:shadow-blue-500/10 transition-all">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-blue-500/10 group-hover:bg-blue-500/20">
                    <i class="bi bi-tag-fill text-2xl text-blue-400"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Marketplace listings</h3>
                <p class="text-sm text-gh-muted">
                    Titles, descriptions, and pricing are scanned for scams, fraud, prohibited items, and unsafe language.
                </p>
            </div>

            <div class="group rounded-xl border border-gh-border bg-gh-panel p-6 hover:border-green-500/50 hover:shadow-lg hover:shadow-green-500/10 transition-all">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-green-500/10 group-hover:bg-green-500/20">
                    <i class="bi bi-chat-dots-fill text-2xl text-green-400"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Forum threads & replies</h3>
                <p class="text-sm text-gh-muted">
                    Discussions are checked for harassment, hate speech, spam, misinformation, and incitement to violence.
                </p>
            </div>

            <div class="group rounded-xl border border-gh-border bg-gh-panel p-6 hover:border-purple-500/50 hover:shadow-lg hover:shadow-purple-500/10 transition-all">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-purple-500/10 group-hover:bg-purple-500/20">
                    <i class="bi bi-envelope-fill text-2xl text-purple-400"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Private messages</h3>
                <p class="text-sm text-gh-muted">
                    Messages are scanned for romance scams, money requests, and pressure to move off‑platform too quickly.
                </p>
            </div>

            <div class="group rounded-xl border border-gh-border bg-gh-panel p-6 hover:border-pink-500/50 hover:shadow-lg hover:shadow-pink-500/10 transition-all">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-pink-500/10 group-hover:bg-pink-500/20">
                    <i class="bi bi-book-fill text-2xl text-pink-400"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">User stories</h3>
                <p class="text-sm text-gh-muted">
                    Stories are reviewed for explicit content, doxxing, and other violations of community guidelines.
                </p>
            </div>
        </div>

        <!-- Charts -->
        <div class="mt-12 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
                <h3 class="text-xl font-bold text-white mb-2">30‑day moderation activity</h3>
                <p class="text-sm text-gh-muted mb-4">How many items were scanned and how many were high‑risk.</p>
                <div id="dailyActivityChart"></div>
            </div>

            <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
                <h3 class="text-xl font-bold text-white mb-2">Content types scanned</h3>
                <p class="text-sm text-gh-muted mb-4">Share of moderation checks by content type.</p>
                <div id="contentTypeChart"></div>
            </div>
        </div>

        <!-- Categories -->
        <?php if (!empty($categoryCount)): ?>
        <div class="mt-12 rounded-xl border border-gh-border bg-gh-panel p-6">
            <h3 class="text-xl font-bold text-white mb-2">Top violation categories</h3>
            <p class="text-sm text-gh-muted mb-4">Most common issues the AI flags.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                $icons = [
                    'spam' => 'bi-exclamation-triangle-fill text-yellow-400',
                    'scam' => 'bi-shield-x text-red-400',
                    'harassment' => 'bi-person-x-fill text-orange-400',
                    'hate' => 'bi-chat-square-text-fill text-red-400',
                    'violence' => 'bi-exclamation-octagon-fill text-red-400',
                    'explicit' => 'bi-eye-slash-fill text-pink-400',
                    'misinformation' => 'bi-info-circle-fill text-blue-400'
                ];
                $i = 0;
                foreach ($categoryCount as $cat => $count) {
                    if ($i++ >= 6) break;
                    $key = strtolower($cat);
                    $iconClass = 'bi-flag-fill text-purple-400';
                    foreach ($icons as $needle => $icon) {
                        if (strpos($key, $needle) !== false) {
                            $iconClass = $icon;
                            break;
                        }
                    }
                    ?>
                    <div class="flex items-center gap-4 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-500/10">
                            <i class="bi <?php echo $iconClass; ?> text-xl"></i>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-white"><?php echo number_format($count); ?></p>
                            <p class="text-sm text-gh-muted"><?php echo htmlspecialchars(ucfirst($cat)); ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php else: ?>
        <div class="mt-12 rounded-xl border border-green-500/30 bg-green-500/5 p-12 text-center">
            <i class="bi bi-check-circle text-6xl text-green-500/30 mb-4"></i>
            <h3 class="text-2xl font-bold text-white mb-2">All clean</h3>
            <p class="text-gh-muted">No violations were detected in the last 30 days.</p>
        </div>
        <?php endif; ?>

        <!-- How it works -->
        <div class="mt-12 rounded-xl border border-gh-border bg-gh-panel overflow-hidden">
            <div class="border-b border-gh-border bg-gh-panel2 px-6 py-4">
                <h2 class="text-2xl font-bold text-white">How moderation works</h2>
                <p class="text-sm text-gh-muted mt-1">
                    Every content item gets a risk score and an action: approve, send to review, or block.
                </p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/10 border-2 border-blue-500/40">
                        <span class="text-blue-400 font-bold">1</span>
                    </div>
                    <p class="text-sm font-bold text-white mb-1">Instant scan</p>
                    <p class="text-xs text-gh-muted">Perplexity analyzes new content within milliseconds of submission.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-purple-500/10 border-2 border-purple-500/40">
                        <span class="text-purple-400 font-bold">2</span>
                    </div>
                    <p class="text-sm font-bold text-white mb-1">Category checks</p>
                    <p class="text-xs text-gh-muted">AI looks for scams, hate, violence, explicit content, spam, and more.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-500/10 border-2 border-green-500/40">
                        <span class="text-green-400 font-bold">3</span>
                    </div>
                    <p class="text-sm font-bold text-white mb-1">Risk scoring</p>
                    <p class="text-xs text-gh-muted">Each item gets a 0‑100 score: low, medium, high, or critical.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-orange-500/10 border-2 border-orange-500/40">
                        <span class="text-orange-400 font-bold">4</span>
                    </div>
                    <p class="text-sm font-bold text-white mb-1">Auto‑action</p>
                    <p class="text-xs text-gh-muted">Safe content is posted, critical items are blocked immediately.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-pink-500/10 border-2 border-pink-500/40">
                        <span class="text-pink-400 font-bold">5</span>
                    </div>
                    <p class="text-sm font-bold text-white mb-1">Human review</p>
                    <p class="text-xs text-gh-muted">Moderators review flagged content and can override AI decisions.</p>
                </div>
            </div>
        </div>

        <!-- Daily table & footer -->
        <div class="mt-12 rounded-xl border border-gh-border bg-gh-panel overflow-hidden">
            <div class="border-b border-gh-border bg-gh-panel2 px-6 py-4">
                <h3 class="text-xl font-bold text-white">Daily statistics (last 30 days)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-gh-border bg-gh-panel2">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Total scans</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">High risk</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Blocked</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gh-muted">Avg score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gh-border">
                    <?php if (empty($dailyStats)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gh-muted">
                                No data available yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyStats as $row): ?>
                        <tr class="hover:bg-gh-panel2 transition-colors">
                            <td class="px-6 py-3 text-sm text-gh-fg"><?php echo date('M j, Y', strtotime($row['scan_date'])); ?></td>
                            <td class="px-6 py-3 text-sm text-gh-fg"><?php echo number_format($row['total_scans']); ?></td>
                            <td class="px-6 py-3 text-sm text-gh-fg"><?php echo number_format($row['high_risk']); ?></td>
                            <td class="px-6 py-3 text-sm text-gh-fg"><?php echo number_format($row['blocked']); ?></td>
                            <td class="px-6 py-3 text-sm text-gh-fg"><?php echo number_format($row['avg_risk_score'], 1); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 rounded-xl border border-gh-border bg-gh-panel p-6 text-center">
            <p class="text-sm text-gh-muted">
                <i class="bi bi-clock-history mr-2"></i>
                Updated in real time. Last updated:
                <span class="font-semibold text-gh-fg"><?php echo date('F j, Y g:i A'); ?></span>
            </p>
            <div class="mt-3 flex items-center justify-center gap-4 text-sm">
                <a href="https://www.perplexity.ai" target="_blank" class="text-gh-accent hover:underline inline-flex items-center gap-1">
                    <i class="bi bi-box-arrow-up-right"></i>
                    Learn more about Perplexity AI
                </a>
                <span class="text-gh-border">|</span>
                <a href="mailto:support@trubl.co" class="text-gh-accent hover:underline inline-flex items-center gap-1">
                    <i class="bi bi-envelope"></i>
                    Contact support
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const dailyLabels = [<?php foreach(array_reverse($dailyStats) as $d) echo "'" . date('M j', strtotime($d['scan_date'])) . "',"; ?>];
const dailyScans  = [<?php foreach(array_reverse($dailyStats) as $d) echo $d['total_scans'] . ','; ?>];
const dailyHigh   = [<?php foreach(array_reverse($dailyStats) as $d) echo $d['high_risk'] . ','; ?>];

var dailyOptions = {
    series: [
        { name: 'Total scans', data: dailyScans },
        { name: 'High‑risk items', data: dailyHigh }
    ],
    chart: {
        type: 'area',
        height: 300,
        background: 'transparent',
        toolbar: { show: false }
    },
    colors: ['#3b82f6', '#ef4444'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.6, opacityTo: 0.05 }
    },
    xaxis: {
        categories: dailyLabels,
        labels: { style: { colors: '#9ca3af', fontSize: '12px' } }
    },
    yaxis: {
        labels: { style: { colors: '#9ca3af', fontSize: '12px' } }
    },
    grid: { borderColor: '#374151', strokeDashArray: 3 },
    legend: { labels: { colors: '#9ca3af' } },
    tooltip: { theme: 'dark' }
};
new ApexCharts(document.querySelector("#dailyActivityChart"), dailyOptions).render();

var typeOptions = {
    series: [<?php foreach($contentTypes as $t) echo $t['count'] . ','; ?>],
    chart: { type: 'donut', height: 300, background: 'transparent' },
    labels: [<?php foreach($contentTypes as $t) echo "'" . ucfirst(str_replace('_',' ', $t['content_type'])) . "',"; ?>],
    colors: ['#3b82f6','#10b981','#a855f7','#ec4899','#fb923c','#22d3ee'],
    legend: { position: 'right', labels: { colors: '#9ca3af' } },
    plotOptions: {
        pie: {
            donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', color: '#fff' } } }
        }
    },
    dataLabels: { enabled: false },
    tooltip: { theme: 'dark' }
};
new ApexCharts(document.querySelector("#contentTypeChart"), typeOptions).render();
</script>

<?php include 'views/footer.php'; ?>
