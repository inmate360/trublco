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

include 'views/header.php';
?>

<div class="min-h-screen py-10">
  <div class="mx-auto max-w-4xl px-4">

    <div class="mb-8">
      <a href="choose-location.php" class="inline-flex items-center gap-2 text-sm text-gh-muted hover:text-gh-fg">
        <i class="bi bi-arrow-left"></i>
        Back to location select
      </a>
    </div>

    <div class="mb-8 text-center">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2">
        <i class="bi bi-megaphone-fill text-2xl text-gh-accent"></i>
      </div>
      <h1 class="text-3xl font-bold">Announcements</h1>
      <p class="mt-2 text-gh-muted">Stay updated with the latest features and improvements</p>
    </div>

    <div class="space-y-4">

      <!-- Announcement Card -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="rounded bg-yellow-400 px-3 py-1 text-xs font-bold uppercase text-black">NEW</span>
            <span class="text-sm text-gh-muted">December 25, 2025</span>
          </div>
        </div>
        <h2 class="mb-3 text-xl font-bold">Creator Marketplace Now Live!</h2>
        <p class="mb-4 text-gh-muted">
          We're excited to announce the launch of our Creator Marketplace. Now you can monetize your content and grow your personal brand on Turnpage. Whether you're offering premium content, exclusive experiences, or personalized services, the Creator Marketplace makes it easy to connect with your audience.
        </p>
        <ul class="mb-4 space-y-2 text-sm text-gh-muted">
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Set your own prices and subscription tiers</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Direct payments with secure processing</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Analytics dashboard to track your performance</span>
          </li>
        </ul>
        <a href="marketplace.php" class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-4 py-2 text-sm font-semibold text-white transition-all hover:brightness-110">
          Explore Creator Marketplace
          <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <!-- Announcement Card -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="rounded bg-blue-500 px-3 py-1 text-xs font-bold uppercase text-white">UPDATE</span>
            <span class="text-sm text-gh-muted">December 20, 2025</span>
          </div>
        </div>
        <h2 class="mb-3 text-xl font-bold">Enhanced Search & Filtering</h2>
        <p class="text-gh-muted">
          We've completely redesigned our search experience. Find exactly what you're looking for with our new autocomplete city search, improved filters, and faster load times. The new search remembers your preferences and suggests popular locations in your area.
        </p>
      </div>

      <!-- Announcement Card -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="rounded bg-green-500 px-3 py-1 text-xs font-bold uppercase text-white">FEATURE</span>
            <span class="text-sm text-gh-muted">December 15, 2025</span>
          </div>
        </div>
        <h2 class="mb-3 text-xl font-bold">Verified Profiles Badge</h2>
        <p class="text-gh-muted">
          Safety first! We've introduced verified profile badges to help you identify genuine users. Get your profile verified by completing a simple identity check. Verified users get priority placement in search results and increased trust from the community.
        </p>
      </div>

      <!-- Announcement Card -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="rounded bg-purple-500 px-3 py-1 text-xs font-bold uppercase text-white">IMPROVEMENT</span>
            <span class="text-sm text-gh-muted">December 10, 2025</span>
          </div>
        </div>
        <h2 class="mb-3 text-xl font-bold">Mobile App Performance</h2>
        <p class="text-gh-muted">
          We've optimized the mobile experience with faster page loads, smoother animations, and better touch interactions. The app now uses 40% less data and loads pages 3x faster on slower connections.
        </p>
      </div>

    </div>

    <div class="mt-8 rounded-xl border border-gh-border bg-gh-panel2 p-6 text-center">
      <h3 class="mb-2 font-bold">Want to stay updated?</h3>
      <p class="mb-4 text-sm text-gh-muted">Follow us on social media for real-time updates and announcements</p>
      <div class="flex justify-center gap-4">
        <a href="#" class="text-gh-muted transition-colors hover:text-gh-accent">
          <i class="bi bi-twitter text-2xl"></i>
        </a>
        <a href="#" class="text-gh-muted transition-colors hover:text-gh-accent">
          <i class="bi bi-instagram text-2xl"></i>
        </a>
        <a href="#" class="text-gh-muted transition-colors hover:text-gh-accent">
          <i class="bi bi-facebook text-2xl"></i>
        </a>
      </div>
    </div>

  </div>
</div>

<?php include 'views/footer.php'; ?>
