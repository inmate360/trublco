<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

include 'views/header.php';
?>

<!-- Hero Header Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4">
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl text-3xl" 
                 style="background: #e11d48; box-shadow: 0 10px 30px #e11d4840;">
                <i class="bi bi-book text-white"></i>
            </div>
            <div class="flex-1">
                <h1 class="mb-2 text-4xl font-bold text-white md:text-5xl">
                    Community Guidelines
                </h1>
                <p class="text-pink-200">Rules and standards for a safe, respectful community</p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-4xl px-4">

        <!-- Introduction -->
        <div class="mb-8 rounded-lg border border-gh-border bg-gh-panel p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-gh-accent/10">
                    <i class="bi bi-info-circle-fill text-2xl text-gh-accent"></i>
                </div>
                <div>
                    <h2 class="mb-2 text-xl font-bold text-gh-fg">Welcome to trubl</h2>
                    <p class="text-sm text-gh-muted leading-relaxed">
                        Our community thrives when everyone feels safe and respected. These guidelines help maintain a positive environment for all members. By using trubl, you agree to follow these rules.
                    </p>
                </div>
            </div>
        </div>

        <!-- Core Principles -->
        <div class="mb-8">
            <h2 class="mb-4 text-2xl font-bold text-gh-fg">Core Principles</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gh-border bg-gh-panel p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/10">
                        <i class="bi bi-shield-check text-xl text-green-500"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-gh-fg">Be Respectful</h3>
                    <p class="text-sm text-gh-muted">Treat everyone with dignity and respect, regardless of differences</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-panel p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10">
                        <i class="bi bi-heart text-xl text-blue-500"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-gh-fg">Be Authentic</h3>
                    <p class="text-sm text-gh-muted">Use real information and be honest in your interactions</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-panel p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/10">
                        <i class="bi bi-shield-fill-check text-xl text-purple-500"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-gh-fg">Stay Safe</h3>
                    <p class="text-sm text-gh-muted">Protect your privacy and meet safely when connecting with others</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-panel p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-500/10">
                        <i class="bi bi-people text-xl text-yellow-500"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-gh-fg">Be Inclusive</h3>
                    <p class="text-sm text-gh-muted">Welcome diversity and create a positive space for all</p>
                </div>
            </div>
        </div>

        <!-- Prohibited Content -->
        <div class="mb-8">
            <h2 class="mb-4 text-2xl font-bold text-gh-fg">Prohibited Content & Behavior</h2>
            <div class="space-y-3">
                
                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Illegal Content</h3>
                            <p class="text-sm text-gh-muted">Any content depicting or promoting illegal activities, including drugs, weapons, or human trafficking</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Minors & Age Verification</h3>
                            <p class="text-sm text-gh-muted">Strictly 18+ only. Any content involving minors will result in immediate account termination and reporting to authorities</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Harassment & Threats</h3>
                            <p class="text-sm text-gh-muted">Bullying, stalking, doxxing, or threatening language toward any member</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Spam & Scams</h3>
                            <p class="text-sm text-gh-muted">Commercial solicitation, pyramid schemes, phishing attempts, or misleading links</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Hate Speech</h3>
                            <p class="text-sm text-gh-muted">Discrimination based on race, ethnicity, religion, gender, sexual orientation, or disability</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Impersonation & Fake Accounts</h3>
                            <p class="text-sm text-gh-muted">Pretending to be someone else or creating multiple fake accounts</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-x-circle-fill text-xl text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-bold text-red-500">Non-Consensual Content</h3>
                            <p class="text-sm text-gh-muted">Sharing private images, videos, or personal information without consent</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Creating Great Listings -->
        <div class="mb-8">
            <h2 class="mb-4 text-2xl font-bold text-gh-fg">Creating Great Listings</h2>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill text-xl text-green-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Be Clear & Honest</h3>
                            <p class="text-sm text-gh-muted">Clearly state what you're looking for and be truthful about yourself</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill text-xl text-green-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Use Appropriate Photos</h3>
                            <p class="text-sm text-gh-muted">Share clear, recent photos. Explicit content should be marked appropriately</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill text-xl text-green-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Include Relevant Details</h3>
                            <p class="text-sm text-gh-muted">Age, location, interests, and what you're seeking help others find compatible matches</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill text-xl text-green-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Keep It Updated</h3>
                            <p class="text-sm text-gh-muted">Remove or update listings when your situation changes</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill text-xl text-green-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Respect Boundaries</h3>
                            <p class="text-sm text-gh-muted">If someone declines or doesn't respond, move on respectfully</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enforcement -->
        <div class="mb-8">
            <h2 class="mb-4 text-2xl font-bold text-gh-fg">Enforcement & Consequences</h2>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                <p class="mb-4 text-sm text-gh-muted">
                    We take community safety seriously. Violations will result in:
                </p>
                <div class="space-y-3">
                    <div class="flex items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-yellow-500/10">
                            <span class="font-bold text-yellow-500">1</span>
                        </div>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Warning</h3>
                            <p class="text-sm text-gh-muted">First offense for minor violations</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-orange-500/10">
                            <span class="font-bold text-orange-500">2</span>
                        </div>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Temporary Suspension</h3>
                            <p class="text-sm text-gh-muted">1-30 days depending on severity</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-red-500/10">
                            <span class="font-bold text-red-500">3</span>
                        </div>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Permanent Ban</h3>
                            <p class="text-sm text-gh-muted">Serious violations or repeat offenses</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-purple-500/10">
                            <span class="font-bold text-purple-500">4</span>
                        </div>
                        <div>
                            <h3 class="mb-1 font-semibold text-gh-fg">Legal Action</h3>
                            <p class="text-sm text-gh-muted">Illegal content reported to law enforcement</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reporting -->
        <div class="mb-8">
            <h2 class="mb-4 text-2xl font-bold text-gh-fg">Reporting Violations</h2>
            <div class="rounded-lg border border-gh-accent/20 bg-gh-accent/5 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-gh-accent/10">
                        <i class="bi bi-flag-fill text-2xl text-gh-accent"></i>
                    </div>
                    <div>
                        <h3 class="mb-2 font-bold text-gh-fg">See Something? Say Something!</h3>
                        <p class="mb-4 text-sm text-gh-muted">
                            Help us maintain a safe community by reporting violations. All reports are reviewed within 24 hours and kept confidential.
                        </p>
                        <a href="report-abuse.php" 
                           class="inline-flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-600">
                            <i class="bi bi-flag-fill"></i>
                            Report Content
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Resources -->
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="safety.php" 
               class="group rounded-lg border border-gh-border bg-gh-panel p-5 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                <i class="bi bi-shield-check text-3xl text-green-500 mb-3 block"></i>
                <h3 class="mb-2 font-bold text-gh-fg group-hover:text-gh-accent">Safety Tips</h3>
                <p class="text-sm text-gh-muted">Stay safe online</p>
            </a>

            <a href="terms.php" 
               class="group rounded-lg border border-gh-border bg-gh-panel p-5 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                <i class="bi bi-file-text text-3xl text-blue-500 mb-3 block"></i>
                <h3 class="mb-2 font-bold text-gh-fg group-hover:text-gh-accent">Terms of Service</h3>
                <p class="text-sm text-gh-muted">Legal agreements</p>
            </a>

            <a href="faq.php" 
               class="group rounded-lg border border-gh-border bg-gh-panel p-5 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                <i class="bi bi-question-circle text-3xl text-purple-500 mb-3 block"></i>
                <h3 class="mb-2 font-bold text-gh-fg group-hover:text-gh-accent">FAQ</h3>
                <p class="text-sm text-gh-muted">Get answers</p>
            </a>
        </div>

    </div>
</div>

<?php include 'views/footer.php'; ?>
