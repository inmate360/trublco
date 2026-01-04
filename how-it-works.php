<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-16">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-bold text-white backdrop-blur-sm">
            <i class="bi bi-lightbulb-fill text-yellow-300"></i>
            Getting Started Guide
        </div>
        <h1 class="mb-4 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-5xl font-bold text-transparent md:text-6xl">
            How trubl Works
        </h1>
        <p class="mb-6 text-lg text-pink-200">Simple, safe, and effective. Start connecting in minutes!</p>
        
        <div class="flex flex-wrap justify-center gap-3">
            <a href="register.php" class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 font-bold text-pink-600 shadow-lg transition-all hover:scale-105">
                <i class="bi bi-person-plus-fill"></i>
                Create Free Account
            </a>
            <a href="choose-location.php" class="inline-flex items-center gap-2 rounded-lg border-2 border-white bg-white/10 px-6 py-3 font-bold text-white backdrop-blur-sm transition-all hover:bg-white/20">
                <i class="bi bi-geo-alt-fill"></i>
                Browse as Guest
            </a>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-6xl px-4">

        <!-- Steps Section -->
        <div class="mb-12">
            <h2 class="mb-8 text-center text-3xl font-bold text-white">
                <i class="bi bi-rocket-takeoff-fill mr-2 text-gh-accent"></i>
                Getting Started in 5 Easy Steps
            </h2>

            <div class="space-y-6">
                <!-- Step 1 -->
                <div class="group flex flex-col gap-6 rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent hover:shadow-xl md:flex-row">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-3xl font-bold text-white shadow-lg">
                        1
                    </div>
                    <div class="flex-1">
                        <h3 class="mb-3 text-2xl font-bold text-white">
                            <i class="bi bi-person-check-fill mr-2 text-green-500"></i>
                            Create Your Free Account
                        </h3>
                        <p class="mb-4 leading-relaxed text-gh-muted">
                            Sign up in seconds with just your email and username. No credit card required to join. 
                            Choose a unique username and create a secure password. Verify your email and you're ready to go!
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-500/20 px-3 py-1 text-xs font-semibold text-green-400">
                                <i class="bi bi-check-circle-fill"></i>
                                Quick signup
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-500/20 px-3 py-1 text-xs font-semibold text-green-400">
                                <i class="bi bi-check-circle-fill"></i>
                                No credit card
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-500/20 px-3 py-1 text-xs font-semibold text-green-400">
                                <i class="bi bi-check-circle-fill"></i>
                                Email verification
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="group flex flex-col gap-6 rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent hover:shadow-xl md:flex-row">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 text-3xl font-bold text-white shadow-lg">
                        2
                    </div>
                    <div class="flex-1">
                        <h3 class="mb-3 text-2xl font-bold text-white">
                            <i class="bi bi-geo-alt-fill mr-2 text-blue-500"></i>
                            Set Your Location
                        </h3>
                        <p class="mb-4 leading-relaxed text-gh-muted">
                            Choose your city or let us auto-detect your location for the best local matches. 
                            Browse by neighborhood, set your search radius, and use travel mode if you're visiting another city. 
                            Your location helps us show you the most relevant listings.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-3 py-1 text-xs font-semibold text-blue-400">
                                <i class="bi bi-pin-map-fill"></i>
                                Auto-detect
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-3 py-1 text-xs font-semibold text-blue-400">
                                <i class="bi bi-compass-fill"></i>
                                Radius search
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-3 py-1 text-xs font-semibold text-blue-400">
                                <i class="bi bi-airplane-fill"></i>
                                Travel mode
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="group flex flex-col gap-6 rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent hover:shadow-xl md:flex-row">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-pink-600 text-3xl font-bold text-white shadow-lg">
                        3
                    </div>
                    <div class="flex-1">
                        <h3 class="mb-3 text-2xl font-bold text-white">
                            <i class="bi bi-file-earmark-text-fill mr-2 text-purple-500"></i>
                            Create Your Listing
                        </h3>
                        <p class="mb-4 leading-relaxed text-gh-muted">
                            Post your ad in minutes! Choose a category, write an engaging title and description, 
                            add photos (optional), and specify what you're looking for. Our AI moderation ensures 
                            quality content while keeping the platform safe.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-500/20 px-3 py-1 text-xs font-semibold text-purple-400">
                                <i class="bi bi-tags-fill"></i>
                                Categories
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-500/20 px-3 py-1 text-xs font-semibold text-purple-400">
                                <i class="bi bi-image-fill"></i>
                                Photo upload
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-500/20 px-3 py-1 text-xs font-semibold text-purple-400">
                                <i class="bi bi-robot"></i>
                                AI moderation
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="group flex flex-col gap-6 rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent hover:shadow-xl md:flex-row">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-yellow-500 to-orange-600 text-3xl font-bold text-white shadow-lg">
                        4
                    </div>
                    <div class="flex-1">
                        <h3 class="mb-3 text-2xl font-bold text-white">
                            <i class="bi bi-search-heart-fill mr-2 text-yellow-500"></i>
                            Browse & Connect
                        </h3>
                        <p class="mb-4 leading-relaxed text-gh-muted">
                            Search listings in your area using filters for category, distance, and preferences. 
                            View profiles, check who's online, and see nearby users. When you find someone interesting, 
                            send them a message instantly using our real-time chat system.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-500/20 px-3 py-1 text-xs font-semibold text-yellow-400">
                                <i class="bi bi-filter-circle-fill"></i>
                                Advanced filters
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-500/20 px-3 py-1 text-xs font-semibold text-yellow-400">
                                <i class="bi bi-chat-dots-fill"></i>
                                Instant messaging
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-500/20 px-3 py-1 text-xs font-semibold text-yellow-400">
                                <i class="bi bi-circle-fill"></i>
                                Online status
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Step 5 -->
                <div class="group flex flex-col gap-6 rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent hover:shadow-xl md:flex-row">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-pink-600 text-3xl font-bold text-white shadow-lg">
                        5
                    </div>
                    <div class="flex-1">
                        <h3 class="mb-3 text-2xl font-bold text-white">
                            <i class="bi bi-shield-check-fill mr-2 text-red-500"></i>
                            Meet & Stay Safe
                        </h3>
                        <p class="mb-4 leading-relaxed text-gh-muted">
                            Chat to get to know each other, exchange photos, and when you're comfortable, arrange to meet. 
                            Always meet in public places first, tell a friend where you're going, and trust your instincts. 
                            Use our safety features like blocking and reporting if needed.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-500/20 px-3 py-1 text-xs font-semibold text-red-400">
                                <i class="bi bi-people-fill"></i>
                                Public meetings
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-500/20 px-3 py-1 text-xs font-semibold text-red-400">
                                <i class="bi bi-shield-fill-check"></i>
                                Safety tools
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-500/20 px-3 py-1 text-xs font-semibold text-red-400">
                                <i class="bi bi-flag-fill"></i>
                                Report & block
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="mb-12">
            <h2 class="mb-8 text-center text-3xl font-bold text-white">
                <i class="bi bi-stars mr-2 text-yellow-500"></i>
                Powerful Features
            </h2>
            <p class="mb-8 text-center text-gh-muted">Everything you need for successful connections</p>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Feature 1 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-purple-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Location-Based</h3>
                    <p class="text-sm text-gh-muted">Find people nearby with radius search and neighborhood filtering</p>
                </div>

                <!-- Feature 2 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Real-Time Chat</h3>
                    <p class="text-sm text-gh-muted">Instant messaging with typing indicators and read receipts</p>
                </div>

                <!-- Feature 3 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-images"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Photo Sharing</h3>
                    <p class="text-sm text-gh-muted">Upload multiple photos to your listing and share in messages</p>
                </div>

                <!-- Feature 4 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-yellow-500 to-orange-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-robot"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">AI Moderation</h3>
                    <p class="text-sm text-gh-muted">Automatic spam detection and content filtering</p>
                </div>

                <!-- Feature 5 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-pink-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-airplane-fill"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Travel Mode</h3>
                    <p class="text-sm text-gh-muted">Post your listing in multiple cities when traveling</p>
                </div>

                <!-- Feature 6 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-pink-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Favorites</h3>
                    <p class="text-sm text-gh-muted">Save listings and profiles for easy access later</p>
                </div>

                <!-- Feature 7 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 text-2xl text-white shadow-lg">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Notifications</h3>
                    <p class="text-sm text-gh-muted">Get instant alerts for messages and activity</p>
                </div>

                <!-- Feature 8 -->
                <div class="rounded-lg border border-gh-border bg-gh-panel p-6 text-center transition-all hover:border-gh-accent hover:shadow-lg">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-gray-600 to-gray-800 text-2xl text-white shadow-lg">
                        <i class="bi bi-incognito"></i>
                    </div>
                    <h3 class="mb-2 font-bold text-white">Incognito Mode</h3>
                    <p class="text-sm text-gh-muted">Browse anonymously without appearing in search results</p>
                </div>
            </div>
        </div>

        <!-- Safety Tips Section -->
        <div class="mb-12 rounded-xl border border-gh-border bg-gh-panel p-8">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-3xl text-white shadow-lg">
                    <i class="bi bi-shield-fill-check"></i>
                </div>
                <h2 class="mb-2 text-3xl font-bold text-white">Safety Tips</h2>
                <p class="text-gh-muted">Stay safe while using trubl</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-gh-border bg-gh-bg p-6">
                    <div class="mb-3 flex items-center gap-3">
                        <i class="bi bi-people-fill text-2xl text-blue-500"></i>
                        <h3 class="font-bold text-white">Meet in Public</h3>
                    </div>
                    <p class="text-sm text-gh-muted">Always meet in a public place for the first time. Coffee shops, restaurants, and busy areas are ideal.</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-bg p-6">
                    <div class="mb-3 flex items-center gap-3">
                        <i class="bi bi-telephone-fill text-2xl text-green-500"></i>
                        <h3 class="font-bold text-white">Tell Someone</h3>
                    </div>
                    <p class="text-sm text-gh-muted">Let a friend or family member know where you're going and who you're meeting.</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-bg p-6">
                    <div class="mb-3 flex items-center gap-3">
                        <i class="bi bi-heart-pulse-fill text-2xl text-red-500"></i>
                        <h3 class="font-bold text-white">Trust Your Instincts</h3>
                    </div>
                    <p class="text-sm text-gh-muted">If something feels off, it probably is. Don't hesitate to leave or cancel plans.</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-bg p-6">
                    <div class="mb-3 flex items-center gap-3">
                        <i class="bi bi-lock-fill text-2xl text-purple-500"></i>
                        <h3 class="font-bold text-white">Protect Your Info</h3>
                    </div>
                    <p class="text-sm text-gh-muted">Don't share personal details like your home address or financial information early on.</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-bg p-6">
                    <div class="mb-3 flex items-center gap-3">
                        <i class="bi bi-chat-square-text-fill text-2xl text-yellow-500"></i>
                        <h3 class="font-bold text-white">Use Platform Chat</h3>
                    </div>
                    <p class="text-sm text-gh-muted">Keep conversations on trubl initially. This provides a record and security.</p>
                </div>

                <div class="rounded-lg border border-gh-border bg-gh-bg p-6">
                    <div class="mb-3 flex items-center gap-3">
                        <i class="bi bi-flag-fill text-2xl text-orange-500"></i>
                        <h3 class="font-bold text-white">Report Issues</h3>
                    </div>
                    <p class="text-sm text-gh-muted">If someone violates our terms or makes you uncomfortable, report them immediately.</p>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="rounded-xl border border-gh-accent/30 bg-gradient-to-br from-gh-accent/10 to-gh-success/10 p-8 text-center">
            <h2 class="mb-4 text-3xl font-bold text-white">Ready to Start Connecting?</h2>
            <p class="mb-6 text-gh-muted">Join trubl today and meet people in your area</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="register.php" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-8 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110">
                    <i class="bi bi-person-plus-fill"></i>
                    Get Started Now - It's Free!
                </a>
                <a href="choose-location.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-8 py-3 font-bold text-white transition-all hover:border-gh-accent">
                    <i class="bi bi-search"></i>
                    Browse Listings
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
