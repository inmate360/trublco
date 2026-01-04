<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$page_title = "About trubl";
include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
  <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>

  <div class="relative mx-auto max-w-6xl px-4 text-center">
    <h1 class="mb-3 text-4xl font-bold text-white md:text-5xl">About trubl</h1>
    <p class="text-lg text-pink-200">Your trusted platform for authentic personals. Find what you're looking for.</p>
  </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-8">
  <div class="mx-auto max-w-6xl px-4">

    <!-- Mission Statement -->
    <div class="mb-8 rounded-lg border border-gh-accent/30 bg-gradient-to-br from-gh-accent/10 to-gh-success/10 p-6 text-center">
      <i class="bi bi-heart-fill text-5xl text-red-500 mb-4"></i>
      <h2 class="mb-3 text-2xl font-bold text-white">Connecting People, Creating Possibilities</h2>
      <p class="text-gh-muted leading-relaxed mx-auto max-w-3xl">
        trubl is more than just a classifieds platform—it's a community where real people make genuine connections. Whether you're seeking friendship, romance, or something casual, we provide a safe and authentic space to find what you're looking for.
      </p>
    </div>

    <!-- Our Story -->
    <div class="mb-8 rounded-lg border border-gh-border bg-gh-panel p-6">
      <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
        <i class="bi bi-book mr-3 text-blue-500"></i>
        Our Story
      </h2>
      <div class="space-y-4 text-gh-muted leading-relaxed">
        <p>
          Founded in <?php echo date('Y'); ?>, trubl was created out of a simple belief: everyone deserves a platform where they can be themselves and connect with others authentically. We saw too many dating and personal sites that felt impersonal, unsafe, or filled with fake profiles.
        </p>
        <p>
          We set out to build something different—a community-first platform that prioritizes real connections, user safety, and genuine interactions. Today, trubl serves thousands of users who trust us to help them find what they're looking for.
        </p>
      </div>
    </div>

    <!-- Core Values -->
    <div class="mb-8">
      <h2 class="mb-4 text-2xl font-bold text-white text-center">
        <i class="bi bi-bullseye mr-2 text-green-500"></i>
        Our Core Values
      </h2>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 text-2xl text-white shadow-lg">
            <i class="bi bi-shield-check"></i>
          </div>
          <h3 class="mb-2 text-lg font-bold text-white">Safety First</h3>
          <p class="text-sm text-gh-muted">Your safety is our top priority. We implement strict verification measures, content moderation, and reporting tools.</p>
        </div>

        <div class="rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-cyan-600 text-2xl text-white shadow-lg">
            <i class="bi bi-person-check"></i>
          </div>
          <h3 class="mb-2 text-lg font-bold text-white">Authenticity</h3>
          <p class="text-sm text-gh-muted">We encourage real profiles, honest communication, and genuine connections. No bots, no catfishing, just real people.</p>
        </div>

        <div class="rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 text-2xl text-white shadow-lg">
            <i class="bi bi-people"></i>
          </div>
          <h3 class="mb-2 text-lg font-bold text-white">Inclusivity</h3>
          <p class="text-sm text-gh-muted">Everyone is welcome on trubl. We celebrate diversity and create a judgment-free space for all.</p>
        </div>

        <div class="rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-yellow-500 to-orange-600 text-2xl text-white shadow-lg">
            <i class="bi bi-lock"></i>
          </div>
          <h3 class="mb-2 text-lg font-bold text-white">Privacy</h3>
          <p class="text-sm text-gh-muted">Your personal information stays private. We never sell your data, and you control what you share.</p>
        </div>

        <div class="rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-pink-500 to-red-600 text-2xl text-white shadow-lg">
            <i class="bi bi-chat-heart"></i>
          </div>
          <h3 class="mb-2 text-lg font-bold text-white">Respect</h3>
          <p class="text-sm text-gh-muted">We foster a culture of mutual respect where boundaries are honored and consent is paramount.</p>
        </div>

        <div class="rounded-lg border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-2xl text-white shadow-lg">
            <i class="bi bi-graph-up"></i>
          </div>
          <h3 class="mb-2 text-lg font-bold text-white">Innovation</h3>
          <p class="text-sm text-gh-muted">We continuously improve our platform with new features based on user feedback.</p>
        </div>
      </div>
    </div>

    <!-- How It Works -->
    <div class="mb-8 rounded-lg border border-gh-border bg-gh-panel p-6">
      <h2 class="mb-6 text-2xl font-bold text-white text-center">
        <i class="bi bi-gear mr-2 text-purple-500"></i>
        How It Works
      </h2>

      <div class="grid gap-6 md:grid-cols-4">
        <div class="text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 text-2xl font-bold text-white shadow-lg">
            1
          </div>
          <h4 class="font-bold text-white mb-1">Create Profile</h4>
          <p class="text-sm text-gh-muted">Sign up in minutes with photos and bio</p>
        </div>

        <div class="text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-2xl font-bold text-white shadow-lg">
            2
          </div>
          <h4 class="font-bold text-white mb-1">Browse & Connect</h4>
          <p class="text-sm text-gh-muted">Search personals in your area</p>
        </div>

        <div class="text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-pink-600 text-2xl font-bold text-white shadow-lg">
            3
          </div>
          <h4 class="font-bold text-white mb-1">Chat Safely</h4>
          <p class="text-sm text-gh-muted">Message through our secure platform</p>
        </div>

        <div class="text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 to-red-600 text-2xl font-bold text-white shadow-lg">
            4
          </div>
          <h4 class="font-bold text-white mb-1">Meet Up</h4>
          <p class="text-sm text-gh-muted">Take it offline when ready</p>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="mb-8 rounded-lg border border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2 p-6">
      <h2 class="mb-6 text-2xl font-bold text-white text-center">trubl by the Numbers</h2>
      <div class="grid gap-6 sm:grid-cols-3">
        <div class="text-center">
          <div class="text-4xl font-bold text-blue-500 mb-2">50K+</div>
          <div class="text-sm text-gh-muted">Active Members</div>
        </div>
        <div class="text-center">
          <div class="text-4xl font-bold text-green-500 mb-2">100K+</div>
          <div class="text-sm text-gh-muted">Monthly Connections</div>
        </div>
        <div class="text-center">
          <div class="text-4xl font-bold text-purple-500 mb-2">98%</div>
          <div class="text-sm text-gh-muted">Safety Rating</div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="rounded-lg border border-gh-accent/30 bg-gradient-to-br from-gh-accent/10 to-gh-success/10 p-8 text-center">
      <h2 class="mb-2 text-2xl font-bold text-white">Ready to Get Started?</h2>
      <p class="mb-6 text-gh-muted">Join thousands of members connecting every day</p>
      <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
        <a href="register.php" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-8 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110">
          <i class="bi bi-person-plus-fill"></i>
          Create Free Account
        </a>
        <a href="browse.php" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-8 py-3 font-bold text-white transition-all hover:border-gh-accent">
          <i class="bi bi-search"></i>
          Browse Personals
        </a>
      </div>
    </div>

  </div>
</div>

<?php include 'views/footer.php'; ?>