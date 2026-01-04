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

$page_title = "Privacy Policy";
include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
  <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>

  <div class="relative mx-auto max-w-6xl px-4">
    <h1 class="mb-2 text-4xl font-bold text-white md:text-5xl">Privacy Policy</h1>
    <p class="text-pink-200">Last updated: <?php echo date('F j, Y'); ?></p>
  </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-8">
  <div class="mx-auto max-w-4xl px-4">

    <!-- Intro -->
    <div class="mb-6 rounded-lg border border-gh-accent/30 bg-gradient-to-br from-gh-accent/10 to-gh-success/10 p-6">
      <p class="text-gh-muted leading-relaxed">
        At trubl, we take your privacy seriously. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our platform.
      </p>
    </div>

    <!-- Content -->
    <div class="space-y-6">

      <!-- Section 1 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-collection mr-3 text-blue-500"></i>
          1. Information We Collect
        </h2>
        <div class="space-y-4 text-gh-muted leading-relaxed">
          <div>
            <h3 class="text-lg font-semibold text-white mb-2">Personal Information</h3>
            <ul class="list-disc list-inside space-y-1 ml-4 text-sm">
              <li>Email address</li>
              <li>Username and password</li>
              <li>Profile information (photos, bio, preferences)</li>
              <li>Location data (with your permission)</li>
            </ul>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-white mb-2">Automatically Collected</h3>
            <ul class="list-disc list-inside space-y-1 ml-4 text-sm">
              <li>IP address and device information</li>
              <li>Browser type and version</li>
              <li>Usage data and analytics</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Section 2 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-gear mr-3 text-green-500"></i>
          2. How We Use Your Information
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>We use the information we collect to:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li>Provide, maintain, and improve our services</li>
            <li>Create and manage your account</li>
            <li>Process transactions and send notifications</li>
            <li>Personalize your experience</li>
            <li>Detect and prevent fraud and abuse</li>
          </ul>
        </div>
      </div>

      <!-- Section 3 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-share mr-3 text-purple-500"></i>
          3. Information Sharing
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>We may share your information in the following circumstances:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li><strong class="text-white">With other users:</strong> Your profile information is visible to other users</li>
            <li><strong class="text-white">Service providers:</strong> Third-party vendors who assist us</li>
            <li><strong class="text-white">Legal requirements:</strong> When required by law</li>
          </ul>
          <p class="mt-4 font-bold text-white">We never sell your personal information to third parties.</p>
        </div>
      </div>

      <!-- Section 4 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-person-check-fill mr-3 text-blue-400"></i>
          4. Your Privacy Rights
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>You have the following rights:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li><strong class="text-white">Access:</strong> Request a copy of your personal data</li>
            <li><strong class="text-white">Correction:</strong> Request correction of inaccurate data</li>
            <li><strong class="text-white">Deletion:</strong> Request deletion of your personal data</li>
            <li><strong class="text-white">Opt-out:</strong> Unsubscribe from marketing communications</li>
          </ul>
          <p class="mt-4">To exercise these rights, contact privacy@trubl.co</p>
        </div>
      </div>

    </div>

  </div>
</div>

<?php include 'views/footer.php'; ?>