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

$page_title = "Safety Tips";
include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-emerald-900 via-green-900 to-teal-900 py-12">
  <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>

  <div class="relative mx-auto max-w-6xl px-4 text-center">
    <i class="bi bi-shield-check text-5xl text-emerald-300 mb-4"></i>
    <h1 class="mb-2 text-4xl font-bold text-white md:text-5xl">Safety Tips</h1>
    <p class="text-green-200">Your safety is our top priority. Follow these tips for a secure experience.</p>
  </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-8">
  <div class="mx-auto max-w-4xl px-4">

    <!-- Emergency Banner -->
    <div class="mb-6 rounded-lg border border-red-500/50 bg-gradient-to-br from-red-600/20 to-red-800/20 p-6">
      <div class="flex items-start gap-4">
        <i class="bi bi-exclamation-triangle-fill text-3xl text-red-500 flex-shrink-0"></i>
        <div>
          <h3 class="text-xl font-bold text-white mb-2">In Case of Emergency</h3>
          <p class="text-gh-muted mb-3">If you feel you are in immediate danger, contact local authorities:</p>
          <div class="flex flex-wrap gap-3">
            <div class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2">
              <span class="text-sm text-gh-muted">US Emergency: </span>
              <span class="font-bold text-white">911</span>
            </div>
            <div class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2">
              <span class="text-sm text-gh-muted">Crisis Hotline: </span>
              <span class="font-bold text-white">988</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Safety Tips Content -->
    <div class="space-y-6">

      <!-- Protect Personal Information -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-person-lock mr-3 text-blue-500"></i>
          Protect Your Personal Information
        </h2>
        <div class="space-y-4">
          <p class="text-gh-muted">Never share sensitive personal details with strangers online:</p>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-4">
              <h4 class="font-bold text-white mb-2 flex items-center">
                <i class="bi bi-x-circle text-red-500 mr-2"></i>Never Share
              </h4>
              <ul class="space-y-1 text-sm text-gh-muted">
                <li>• Full name or address</li>
                <li>• Phone number (initially)</li>
                <li>• Financial information</li>
                <li>• Social Security Number</li>
                <li>• Passwords</li>
              </ul>
            </div>

            <div class="rounded-lg border border-green-500/30 bg-green-500/10 p-4">
              <h4 class="font-bold text-white mb-2 flex items-center">
                <i class="bi bi-check-circle text-green-500 mr-2"></i>Safe to Share
              </h4>
              <ul class="space-y-1 text-sm text-gh-muted">
                <li>• First name or nickname</li>
                <li>• General location (city)</li>
                <li>• Interests and hobbies</li>
                <li>• Age and preferences</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Meet Safely -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-geo-alt mr-3 text-orange-500"></i>
          Meet Safely
        </h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <i class="bi bi-people text-2xl text-blue-500 mb-2"></i>
            <h4 class="font-bold text-white mb-1">Public Places</h4>
            <p class="text-sm text-gh-muted">Meet in busy, well-lit locations like cafes or restaurants</p>
          </div>

          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <i class="bi bi-chat-dots text-2xl text-green-500 mb-2"></i>
            <h4 class="font-bold text-white mb-1">Tell Someone</h4>
            <p class="text-sm text-gh-muted">Inform a friend where you're going and when you'll return</p>
          </div>

          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <i class="bi bi-car-front text-2xl text-purple-500 mb-2"></i>
            <h4 class="font-bold text-white mb-1">Own Transportation</h4>
            <p class="text-sm text-gh-muted">Drive yourself or use a rideshare service</p>
          </div>

          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <i class="bi bi-phone text-2xl text-yellow-500 mb-2"></i>
            <h4 class="font-bold text-white mb-1">Charged Phone</h4>
            <p class="text-sm text-gh-muted">Keep your phone fully charged with emergency contacts ready</p>
          </div>
        </div>
      </div>

      <!-- Report Suspicious Behavior -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-flag mr-3 text-red-500"></i>
          Report Suspicious Behavior
        </h2>
        <div class="space-y-3 text-gh-muted">
          <p>Help us keep the community safe by reporting violations:</p>
          <ul class="list-disc list-inside space-y-1 ml-4 text-sm">
            <li>Harassment or threatening behavior</li>
            <li>Fake profiles or catfishing</li>
            <li>Scams or financial requests</li>
            <li>Inappropriate content</li>
          </ul>
          <div class="mt-4">
            <a href="report.php" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-6 py-3 font-bold text-white transition-all hover:bg-red-700">
              <i class="bi bi-flag-fill"></i>
              Report Abuse
            </a>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

<?php include 'views/footer.php'; ?>