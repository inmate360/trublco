<?php
session_start();
require_once 'config/database.php';

$page_title = 'Site Map - trubl.co';
include 'views/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- SITEMAP PAGE -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<div class="relative min-h-screen bg-gh-bg py-12">
    <div class="container mx-auto px-4">

        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gh-fg mb-4">Site Map</h1>
            <p class="text-gh-muted text-lg max-w-2xl mx-auto">
                Find your way around trubl.co. Browse all our pages and features organized by category.
            </p>
        </div>

        <!-- Sitemap Grid -->
        <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- MAIN PAGES -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-gh-accent/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-house-door text-gh-accent text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Main Pages</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/index.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="/browse.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Browse Personals
                        </a>
                    </li>
                    <li>
                        <a href="/story.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Hookup Stories
                        </a>
                    </li>
                    <li>
                        <a href="/marketplace.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Creator Marketplace
                        </a>
                    </li>
                    <li>
                        <a href="/forum.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Community Forum
                        </a>
                    </li>
                    <li>
                        <a href="/how-it-works.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            How It Works
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- POSTING & CONTENT -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-purple-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-megaphone text-purple-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Posting & Content</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/post-ad.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Post a Personal Ad
                        </a>
                    </li>
                    <li>
                        <a href="/story-submit.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Submit a Story
                        </a>
                    </li>
                    <li>
                        <a href="/forum-create-thread.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Create Forum Thread
                        </a>
                    </li>
                    <li>
                        <a href="/my-listings.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            My Listings
                        </a>
                    </li>
                    <li>
                        <a href="/my-stories.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            My Stories
                        </a>
                    </li>
                    <li>
                        <a href="/favorites.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Favorites
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- ACCOUNT & PROFILE -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-person text-blue-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Account & Profile</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/login.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Login
                        </a>
                    </li>
                    <li>
                        <a href="/register.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Sign Up
                        </a>
                    </li>
                    <li>
                        <a href="/profile.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            My Profile
                        </a>
                    </li>
                    <li>
                        <a href="/edit-profile.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Edit Profile
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="/settings.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Settings
                        </a>
                    </li>
                    <li>
                        <a href="/privacy-settings.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Privacy Settings
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- MESSAGING & SOCIAL -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-green-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-chat-dots text-green-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Messaging & Social</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/messages.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Messages
                        </a>
                    </li>
                    <li>
                        <a href="/messages-inbox.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Inbox
                        </a>
                    </li>
                    <li>
                        <a href="/notifications.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Notifications
                        </a>
                    </li>
                    <li>
                        <a href="/awards.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Awards & Achievements
                        </a>
                    </li>
                    <li>
                        <a href="/followers.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Followers
                        </a>
                    </li>
                    <li>
                        <a href="/following.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Following
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- CREATOR TOOLS -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-yellow-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-star text-yellow-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Creator Tools</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/creator-dashboard.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Creator Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="/creator-earnings.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Earnings
                        </a>
                    </li>
                    <li>
                        <a href="/creator-content.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            My Content
                        </a>
                    </li>
                    <li>
                        <a href="/creator-signup.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Become a Creator
                        </a>
                    </li>
                    <li>
                        <a href="/creator-analytics.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Analytics
                        </a>
                    </li>
                    <li>
                        <a href="/creator-subscribers.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Subscribers
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- PREMIUM & MEMBERSHIP -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-pink-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-gem text-pink-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Premium & Membership</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/membership.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Go Premium
                        </a>
                    </li>
                    <li>
                        <a href="/subscription.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Subscription Plans
                        </a>
                    </li>
                    <li>
                        <a href="/pricing.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Pricing
                        </a>
                    </li>
                    <li>
                        <a href="/billing.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Billing & Payments
                        </a>
                    </li>
                    <li>
                        <a href="/benefits.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Premium Benefits
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SUPPORT & HELP -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-cyan-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-question-circle text-cyan-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Support & Help</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/help.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Help Center
                        </a>
                    </li>
                    <li>
                        <a href="/faq.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            FAQ
                        </a>
                    </li>
                    <li>
                        <a href="/contact.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Contact Us
                        </a>
                    </li>
                    <li>
                        <a href="/safety.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Safety Tips
                        </a>
                    </li>
                    <li>
                        <a href="/report-abuse.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Report Abuse
                        </a>
                    </li>
                    <li>
                        <a href="/feedback.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Send Feedback
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- LEGAL & POLICIES -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-shield-check text-red-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">Legal & Policies</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/terms.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Terms of Service
                        </a>
                    </li>
                    <li>
                        <a href="/privacy.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Privacy Policy
                        </a>
                    </li>
                    <li>
                        <a href="/cookie-policy.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Cookie Policy
                        </a>
                    </li>
                    <li>
                        <a href="/dmca.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            DMCA
                        </a>
                    </li>
                    <li>
                        <a href="/2257.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            18 U.S.C. 2257
                        </a>
                    </li>
                    <li>
                        <a href="/community-guidelines.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Community Guidelines
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- ABOUT & COMPANY -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="bg-gh-panel border border-gh-border rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-orange-500/10 rounded-lg flex items-center justify-center">
                        <i class="bi bi-info-circle text-orange-500 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gh-fg">About & Company</h2>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="/about.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            About Us
                        </a>
                    </li>
                    <li>
                        <a href="/team.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Our Team
                        </a>
                    </li>
                    <li>
                        <a href="/careers.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Careers
                        </a>
                    </li>
                    <li>
                        <a href="/press.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Press & Media
                        </a>
                    </li>
                    <li>
                        <a href="/blog.php" class="text-gh-muted hover:text-gh-accent transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Blog
                        </a>
                    </li>
                    <li>
                        <a href="/sitemap.php" class="text-gh-accent font-semibold flex items-center gap-2">
                            <i class="bi bi-chevron-right text-xs"></i>
                            Site Map (You are here)
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <!-- Bottom CTA -->
        <div class="max-w-4xl mx-auto mt-16 text-center">
            <div class="bg-gh-panel border border-gh-border rounded-xl p-8">
                <h3 class="text-2xl font-bold text-gh-fg mb-4">Can't Find What You're Looking For?</h3>
                <p class="text-gh-muted mb-6">
                    Our support team is here to help you navigate trubl.co
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/contact.php" 
                       class="bg-gh-accent text-white font-semibold px-6 py-3 rounded-lg hover:opacity-90 transition-opacity">
                        Contact Support
                    </a>
                    <a href="/help.php" 
                       class="bg-transparent border-2 border-gh-accent text-gh-accent font-semibold px-6 py-3 rounded-lg hover:bg-gh-accent hover:text-white transition-all">
                        Visit Help Center
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'views/footer.php'; ?>
