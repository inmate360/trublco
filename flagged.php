<?php
require_once 'config/database.php';
require_once 'includes/ModerationPublicStats.php';

$stats = new ModerationPublicStats($db);

// Get time period from query string
$period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
$period = in_array($period, [7, 30, 90]) ? $period : 30;

// Fetch all data
$overallStats = $stats->getOverallStats($period);
$categoryBreakdown = $stats->getCategoryBreakdown($period);
$contentTypeStats = $stats->getContentTypeStats($period);
$recentActivity = $stats->getRecentActivity(15);
$responseTime = $stats->getResponseTimeStats($period);
$accuracy = $stats->getAccuracyStats($period);

// Calculate rates
$approvalRate = $overallStats['total_scanned'] > 0 
    ? round(($overallStats['approved'] / $overallStats['total_scanned']) * 100, 1) 
    : 0;
$rejectionRate = $overallStats['total_scanned'] > 0 
    ? round(($overallStats['rejected'] / $overallStats['total_scanned']) * 100, 1) 
    : 0;
$autoAccuracy = $overallStats['auto_actions'] > 0
    ? round(($overallStats['auto_actions'] / $overallStats['total_scanned']) * 100, 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Moderation Transparency - Basehit</title>
    <meta name="description" content="See how our AI-powered content moderation keeps Basehit safe and transparent">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'gh-bg': '#0a0a0f',
                        'gh-panel': '#1a1a2e',
                        'gh-panel2': '#16213e',
                        'gh-border': '#2a2a3e',
                        'gh-accent': '#e94560',
                        'gh-success': '#00d9a3',
                        'gh-muted': '#9ca3af',
                        'gh-fg': '#f5f5f7'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gh-bg text-gh-fg">
    
    <!-- Header -->
    <header class="border-b border-gh-border">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <a href="index.php" class="text-2xl font-bold bg-gradient-to-r from-gh-accent to-purple-500 bg-clip-text text-transparent">
                    Basehit
                </a>
                <nav class="flex items-center gap-6">
                    <a href="index.php" class="text-gh-muted hover:text-gh-fg transition">Home</a>
                    <a href="safety.php" class="text-gh-muted hover:text-gh-fg transition">Safety</a>
                    <a href="flagged-content.php" class="text-gh-fg font-semibold">Transparency</a>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="container mx-auto px-4 py-12 max-w-7xl">
        
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-gh-panel border border-gh-border rounded-full mb-4">
                <i class="bi bi-shield-check text-gh-success"></i>
                <span class="text-sm text-gh-muted">AI-Powered by Perplexity Sonar Pro</span>
            </div>
            <h1 class="text-5xl font-bold mb-4 bg-gradient-to-r from-gh-accent to-purple-500 bg-clip-text text-transparent">
                Content Moderation Transparency
            </h1>
            <p class="text-xl text-gh-muted max-w-3xl mx-auto">
                We believe in radical transparency. See exactly how our AI content scanner keeps Basehit safe for everyone.
            </p>
        </div>
        
        <!-- Time Period Selector -->
        <div class="flex justify-center mb-8">
            <div class="bg-gh-panel border border-gh-border rounded-xl p-2 inline-flex gap-2">
                <a href="?period=7" class="px-6 py-2 rounded-lg <?= $period === 7 ? 'bg-gh-accent text-white' : 'text-gh-muted hover:bg-gh-panel2' ?> transition">
                    Last 7 Days
                </a>
                <a href="?period=30" class="px-6 py-2 rounded-lg <?= $period === 30 ? 'bg-gh-accent text-white' : 'text-gh-muted hover:bg-gh-panel2' ?> transition">
                    Last 30 Days
                </a>
                <a href="?period=90" class="px-6 py-2 rounded-lg <?= $period === 90 ? 'bg-gh-accent text-white' : 'text-gh-muted hover:bg-gh-panel2' ?> transition">
                    Last 90 Days
                </a>
            </div>
        </div>
        
        <!-- Key Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <i class="bi bi-file-earmark-text text-2xl text-purple-500"></i>
                    <span class="text-xs text-gh-muted uppercase">Total Scanned</span>
                </div>
                <div class="text-3xl font-bold text-gh-fg"><?= number_format($overallStats['total_scanned']) ?></div>
                <div class="text-sm text-gh-muted mt-1">Content items reviewed</div>
            </div>
            
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <i class="bi bi-check-circle text-2xl text-gh-success"></i>
                    <span class="text-xs text-gh-muted uppercase">Approved</span>
                </div>
                <div class="text-3xl font-bold text-gh-success"><?= $approvalRate ?>%</div>
                <div class="text-sm text-gh-muted mt-1"><?= number_format($overallStats['approved']) ?> items</div>
            </div>
            
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <i class="bi bi-x-circle text-2xl text-red-500"></i>
                    <span class="text-xs text-gh-muted uppercase">Rejected</span>
                </div>
                <div class="text-3xl font-bold text-red-500"><?= $rejectionRate ?>%</div>
                <div class="text-sm text-gh-muted mt-1"><?= number_format($overallStats['rejected']) ?> items</div>
            </div>
            
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <i class="bi bi-flag text-2xl text-yellow-500"></i>
                    <span class="text-xs text-gh-muted uppercase">Flagged</span>
                </div>
                <div class="text-3xl font-bold text-yellow-500"><?= number_format($overallStats['flagged']) ?></div>
                <div class="text-sm text-gh-muted mt-1">Manual review needed</div>
            </div>
        </div>
        
        <!-- Two Column Layout -->
        <div class="grid md:grid-cols-2 gap-8 mb-12">
            
            <!-- Violation Categories -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-3">
                    <i class="bi bi-bar-chart-fill text-gh-accent"></i>
                    Top Violation Categories
                </h2>
                <div class="space-y-3">
                    <?php 
                    $categoryLabels = [
                        'spam' => ['label' => 'Spam & Scams', 'icon' => 'envelope-x', 'color' => 'orange'],
                        'harassment' => ['label' => 'Harassment', 'icon' => 'megaphone', 'color' => 'red'],
                        'hate_speech' => ['label' => 'Hate Speech', 'icon' => 'chat-square-text', 'color' => 'red'],
                        'violence' => ['label' => 'Violence', 'icon' => 'exclamation-triangle', 'color' => 'red'],
                        'sexual_minors' => ['label' => 'Sexual/Minors', 'icon' => 'shield-x', 'color' => 'red'],
                        'illegal_activity' => ['label' => 'Illegal Activity', 'icon' => 'ban', 'color' => 'red'],
                        'self_harm' => ['label' => 'Self Harm', 'icon' => 'heart-pulse', 'color' => 'red'],
                        'impersonation' => ['label' => 'Impersonation', 'icon' => 'person-badge', 'color' => 'yellow']
                    ];
                    
                    $maxCount = !empty($categoryBreakdown) ? max($categoryBreakdown) : 1;
                    $displayCount = 0;
                    
                    foreach ($categoryBreakdown as $category => $count):
                        if ($displayCount >= 8) break;
                        $meta = $categoryLabels[$category] ?? ['label' => ucwords(str_replace('_', ' ', $category)), 'icon' => 'flag', 'color' => 'gray'];
                        $percentage = ($count / $maxCount) * 100;
                        $displayCount++;
                    ?>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-<?= $meta['icon'] ?> text-<?= $meta['color'] ?>-500"></i>
                                    <span class="font-medium"><?= $meta['label'] ?></span>
                                </div>
                                <span class="text-gh-muted text-sm"><?= $count ?> flagged</span>
                            </div>
                            <div class="bg-gh-bg border border-gh-border rounded-full h-2">
                                <div class="bg-<?= $meta['color'] ?>-500 rounded-full h-2 transition-all" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($categoryBreakdown)): ?>
                        <div class="text-center py-8">
                            <i class="bi bi-emoji-smile text-4xl text-gh-success mb-2"></i>
                            <p class="text-gh-muted">No violations detected in this period!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Content Type Breakdown -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-3">
                    <i class="bi bi-collection text-purple-500"></i>
                    Content Types Scanned
                </h2>
                <div class="space-y-4">
                    <?php foreach ($contentTypeStats as $type): 
                        $rejectionPercent = $type['total'] > 0 ? round(($type['rejected'] / $type['total']) * 100, 1) : 0;
                        $icons = [
                            'listing' => 'card-list',
                            'story' => 'book',
                            'forum_post' => 'chat-dots',
                            'message' => 'envelope',
                            'profile' => 'person-circle'
                        ];
                    ?>
                        <div class="bg-gh-panel2 border border-gh-border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-<?= $icons[$type['content_type']] ?? 'file-earmark' ?> text-xl text-gh-accent"></i>
                                    <span class="font-semibold capitalize"><?= str_replace('_', ' ', $type['content_type']) ?>s</span>
                                </div>
                                <span class="text-2xl font-bold"><?= number_format($type['total']) ?></span>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                                    <span class="text-gh-muted"><?= $type['rejected'] ?> rejected (<?= $rejectionPercent ?>%)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                    <span class="text-gh-muted"><?= $type['flagged'] ?> flagged</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
        
        <!-- System Performance -->
        <div class="grid md:grid-cols-3 gap-6 mb-12">
            <div class="bg-gradient-to-br from-purple-500/20 to-pink-500/20 border border-purple-500/30 rounded-xl p-6">
                <i class="bi bi-lightning-charge text-3xl text-purple-400 mb-3"></i>
                <div class="text-3xl font-bold text-gh-fg mb-1"><?= $autoAccuracy ?>%</div>
                <div class="text-sm text-gh-muted">Automated decisions</div>
                <div class="text-xs text-purple-400 mt-2">AI handles most content instantly</div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500/20 to-teal-500/20 border border-green-500/30 rounded-xl p-6">
                <i class="bi bi-bullseye text-3xl text-green-400 mb-3"></i>
                <div class="text-3xl font-bold text-gh-fg mb-1"><?= $accuracy['accuracy_percentage'] ?>%</div>
                <div class="text-sm text-gh-muted">AI accuracy rate</div>
                <div class="text-xs text-green-400 mt-2">Based on <?= number_format($accuracy['total_auto_decisions']) ?> decisions</div>
            </div>
            
            <div class="bg-gradient-to-br from-blue-500/20 to-cyan-500/20 border border-blue-500/30 rounded-xl p-6">
                <i class="bi bi-clock-history text-3xl text-blue-400 mb-3"></i>
                <div class="text-3xl font-bold text-gh-fg mb-1">
                    <?php 
                    if ($responseTime['avg_review_time']) {
                        $minutes = floor($responseTime['avg_review_time'] / 60);
                        echo $minutes < 60 ? $minutes . 'm' : round($minutes / 60, 1) . 'h';
                    } else {
                        echo '<1m';
                    }
                    ?>
                </div>
                <div class="text-sm text-gh-muted">Avg review time</div>
                <div class="text-xs text-blue-400 mt-2">For manual reviews</div>
            </div>
        </div>
        
        <!-- Recent Activity Feed -->
        <div class="bg-gh-panel border border-gh-border rounded-xl p-6 mb-12">
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
                <i class="bi bi-activity text-gh-accent"></i>
                Recent Moderation Activity
            </h2>
            <div class="space-y-3">
                <?php foreach ($recentActivity as $activity): 
                    $categories = json_decode($activity['flagged_categories'], true) ?? [];
                    $statusColors = [
                        'rejected' => 'red',
                        'flagged' => 'yellow',
                        'approved' => 'green'
                    ];
                    $color = $statusColors[$activity['status']] ?? 'gray';
                ?>
                    <div class="bg-gh-panel2 border border-gh-border rounded-lg p-4 hover:border-gh-accent/50 transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="px-3 py-1 bg-<?= $color ?>-500/20 text-<?= $color ?>-400 border border-<?= $color ?>-500/30 rounded-full text-xs font-semibold uppercase">
                                        <?= $activity['status'] ?>
                                    </span>
                                    <span class="px-3 py-1 bg-gh-bg border border-gh-border rounded-full text-xs text-gh-muted">
                                        <?= ucfirst(str_replace('_', ' ', $activity['content_type'])) ?>
                                    </span>
                                    <?php if ($activity['auto_action']): ?>
                                        <span class="px-3 py-1 bg-purple-500/20 text-purple-400 border border-purple-500/30 rounded-full text-xs">
                                            <i class="bi bi-cpu"></i> Auto
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-xs text-gh-muted">
                                        <?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($categories)): ?>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <?php foreach (array_slice($categories, 0, 3) as $cat): ?>
                                            <span class="text-xs px-2 py-1 bg-red-500/10 text-red-400 border border-red-500/20 rounded">
                                                <?= htmlspecialchars($cat) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-sm text-gh-muted italic">
                                    Content preview hidden for privacy
                                </div>
                            </div>
                            
                            <?php if ($activity['confidence_score']): ?>
                                <div class="text-right">
                                    <div class="text-xs text-gh-muted mb-1">Confidence</div>
                                    <div class="text-lg font-bold text-gh-fg"><?= round($activity['confidence_score'] * 100) ?>%</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Educational Section -->
        <div class="bg-gradient-to-r from-gh-panel to-gh-panel2 border border-gh-border rounded-xl p-8 mb-12">
            <h2 class="text-3xl font-bold mb-6 text-center">How Our Moderation Works</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-gh-accent/20 border border-gh-accent/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="bi bi-shield-check text-3xl text-gh-accent"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">AI-First Review</h3>
                    <p class="text-gh-muted">Every submission is scanned by Perplexity Sonar Pro AI within seconds, checking against our community guidelines.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-yellow-500/20 border border-yellow-500/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="bi bi-person-check text-3xl text-yellow-500"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Human Verification</h3>
                    <p class="text-gh-muted">Flagged content gets reviewed by trained moderators who make final decisions with context and nuance.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-gh-success/20 border border-gh-success/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="bi bi-arrow-repeat text-3xl text-gh-success"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Appeals Process</h3>
                    <p class="text-gh-muted">Users can appeal any decision. We review appeals within 24 hours and update our AI if mistakes are found.</p>
                </div>
            </div>
        </div>
        
        <!-- Community Guidelines Summary -->
        <div class="bg-gh-panel border border-gh-border rounded-xl p-8">
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
                <i class="bi bi-book text-gh-accent"></i>
                What We Moderate
            </h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-red-400 mb-3 flex items-center gap-2">
                        <i class="bi bi-x-circle"></i>
                        Prohibited Content
                    </h3>
                    <ul class="space-y-2 text-gh-muted">
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-accent mt-1"></i>
                            <span>Sexual content involving minors (zero tolerance)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-accent mt-1"></i>
                            <span>Hate speech targeting protected groups</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-accent mt-1"></i>
                            <span>Threats, violence, or promotion of harm</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-accent mt-1"></i>
                            <span>Illegal activities (drugs, weapons, trafficking)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-accent mt-1"></i>
                            <span>Spam, scams, and phishing attempts</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-accent mt-1"></i>
                            <span>Harassment, doxxing, and stalking</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gh-success mb-3 flex items-center gap-2">
                        <i class="bi bi-check-circle"></i>
                        Allowed Content
                    </h3>
                    <ul class="space-y-2 text-gh-muted">
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-success mt-1"></i>
                            <span>Adult content between consenting adults (18+)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-success mt-1"></i>
                            <span>Casual dating and hookup listings</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-success mt-1"></i>
                            <span>Creator content and marketplace items</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-success mt-1"></i>
                            <span>Honest reviews and feedback</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-success mt-1"></i>
                            <span>Community discussions and stories</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="bi bi-dash text-gh-success mt-1"></i>
                            <span>Location-based personals</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gh-border">
                <a href="safety.php" class="inline-flex items-center gap-2 text-gh-accent hover:underline">
                    Read Full Community Guidelines <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <!-- Footer CTA -->
        <div class="text-center mt-12 bg-gradient-to-r from-gh-accent/10 to-purple-500/10 border border-gh-accent/30 rounded-xl p-8">
            <i class="bi bi-envelope text-4xl text-gh-accent mb-4"></i>
            <h3 class="text-2xl font-bold mb-2">Questions About Moderation?</h3>
            <p class="text-gh-muted mb-4">We're committed to transparency and continuous improvement.</p>
            <a href="contact.php" class="inline-block px-6 py-3 bg-gh-accent text-white font-semibold rounded-lg hover:bg-red-600 transition">
                Contact Moderation Team
            </a>
        </div>
        
    </div>
    
    <!-- Footer -->
    <footer class="border-t border-gh-border mt-12">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center text-gh-muted text-sm">
                <p>Last Updated: <?= date('F j, Y g:i A') ?> EST</p>
                <p class="mt-2">This page updates in real-time. Data anonymized for user privacy.</p>
            </div>
        </div>
    </footer>
    
</body>
</html>
