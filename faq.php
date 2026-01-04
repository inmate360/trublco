<?php
session_start();
include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-3xl backdrop-blur-sm">
            <i class="bi bi-question-circle-fill text-pink-300"></i>
        </div>
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            Frequently Asked Questions
        </h1>
        <p class="text-base text-pink-200">Find answers to common questions about trubl</p>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-4xl px-4">

        <!-- Search Box -->
        <div class="mb-8">
            <div class="relative">
                <input 
                    type="text" 
                    placeholder="Search for answers..." 
                    class="w-full rounded-lg border border-gh-border bg-gh-panel px-5 py-4 pl-12 text-white placeholder-gh-muted focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                >
                <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-gh-muted"></i>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mb-8 grid gap-4 sm:grid-cols-4">
            <a href="#account" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-person-fill mb-2 text-2xl text-blue-500"></i>
                <div class="text-sm font-semibold text-white">Account</div>
            </a>
            <a href="#listings" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-file-text-fill mb-2 text-2xl text-green-500"></i>
                <div class="text-sm font-semibold text-white">Listings</div>
            </a>
            <a href="#messaging" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-chat-dots-fill mb-2 text-2xl text-purple-500"></i>
                <div class="text-sm font-semibold text-white">Messaging</div>
            </a>
            <a href="#membership" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-gem mb-2 text-2xl text-yellow-500"></i>
                <div class="text-sm font-semibold text-white">Premium</div>
            </a>
        </div>

        <!-- FAQ Sections -->
        <div class="space-y-8">

            <!-- Account & Profile -->
            <div id="account" class="rounded-lg border border-gh-border bg-gh-panel p-8">
                <h2 class="mb-6 flex items-center gap-3 text-2xl font-bold text-white">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-cyan-600 text-white">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    Account & Profile
                </h2>

                <div class="space-y-4">
                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I create an account?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Click the "Sign Up" button in the top navigation, fill in your email, username, and password, then click "Register". You'll be able to start posting immediately.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>I forgot my password. How do I reset it?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            On the login page, click "Forgot Password". Enter your email address and we'll send you instructions to reset your password.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I update my profile information?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Log in and go to your account settings. Here you can update your email, username, phone number, and other profile details.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I delete my account?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Go to account settings and look for the "Delete Account" option at the bottom. Note that this action is permanent and cannot be undone.
                        </div>
                    </details>
                </div>
            </div>

            <!-- Listings & Posts -->
            <div id="listings" class="rounded-lg border border-gh-border bg-gh-panel p-8">
                <h2 class="mb-6 flex items-center gap-3 text-2xl font-bold text-white">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 text-white">
                        <i class="bi bi-file-text-fill"></i>
                    </div>
                    Listings & Posts
                </h2>

                <div class="space-y-4">
                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I create a listing?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            After logging in and selecting your city, click the "+ Post Ad" button. Fill in the title, description, and other details, then submit.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I upload images to my listing?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            After creating a listing, go to "My Listings" and click "Manage Images". You can upload up to 10 images per listing. Supported formats: JPG, PNG, GIF, WebP. Maximum file size: 5MB per image.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How long do listings stay active?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Regular listings stay active for 30 days. Featured listings duration depends on the package purchased (1, 3, 7, 14, or 30 days).
                        </div>
                    </details>
                </div>
            </div>

            <!-- Messaging -->
            <div id="messaging" class="rounded-lg border border-gh-border bg-gh-panel p-8">
                <h2 class="mb-6 flex items-center gap-3 text-2xl font-bold text-white">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 text-white">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    Messaging
                </h2>

                <div class="space-y-4">
                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I send a message?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Open the listing you're interested in and click the "Send Message" button. This will open a conversation with the poster.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>Where can I view my messages?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Click "Messages" in the navigation menu. You'll see all your conversations. Unread messages are highlighted with a notification badge.
                        </div>
                    </details>
                </div>
            </div>

            <!-- Membership -->
            <div id="membership" class="rounded-lg border border-gh-border bg-gh-panel p-8">
                <h2 class="mb-6 flex items-center gap-3 text-2xl font-bold text-white">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-yellow-500 to-orange-600 text-white">
                        <i class="bi bi-gem"></i>
                    </div>
                    Premium Membership
                </h2>

                <div class="space-y-4">
                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>What features do I get with a free account?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Free accounts can post up to 3 active listings, browse all categories, search listings, and send messages.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I upgrade to premium?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Click "Membership" in the navigation menu, choose a plan that suits your needs, and complete the payment process.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>What payment methods do you accept?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            We accept Bitcoin payments processed securely through Coinbase Commerce for all premium subscriptions.
                        </div>
                    </details>
                </div>
            </div>

            <!-- Safety -->
            <div class="rounded-lg border border-gh-border bg-gh-panel p-8">
                <h2 class="mb-6 flex items-center gap-3 text-2xl font-bold text-white">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-pink-600 text-white">
                        <i class="bi bi-shield-fill-check"></i>
                    </div>
                    Safety & Security
                </h2>

                <div class="space-y-4">
                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>How do I report suspicious activity?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Click the "Report" button on the listing, select a reason, and provide details. Our moderation team will review it promptly.
                        </div>
                    </details>

                    <details class="group rounded-lg border border-gh-border bg-gh-bg">
                        <summary class="flex cursor-pointer items-center justify-between p-4 font-semibold text-white transition-colors hover:text-gh-accent">
                            <span>What are your safety recommendations?</span>
                            <i class="bi bi-chevron-down transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gh-border p-4 text-sm text-gh-muted">
                            Please read our comprehensive Safety Tips page. Always meet in public places, tell someone where you're going, and trust your instincts.
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <!-- Still Need Help -->
        <div class="mt-12 rounded-xl border border-gh-accent/30 bg-gradient-to-br from-gh-accent/10 to-gh-success/10 p-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-purple-600 text-3xl text-white shadow-lg">
                <i class="bi bi-headset"></i>
            </div>
            <h3 class="mb-2 text-2xl font-bold text-white">Can't find what you're looking for?</h3>
            <p class="mb-6 text-gh-muted">Our support team is here to help</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="contact.php" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110">
                    <i class="bi bi-envelope-fill"></i>
                    Contact Support
                </a>
                <a href="forum.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-6 py-3 font-bold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-people-fill"></i>
                    Community Forum
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
