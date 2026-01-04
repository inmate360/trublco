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

$page_title = "Terms of Service";
include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
  <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>

  <div class="relative mx-auto max-w-6xl px-4">
    <h1 class="mb-2 text-4xl font-bold text-white md:text-5xl">Terms of Service</h1>
    <p class="text-pink-200">Last updated: <?php echo date('F j, Y'); ?></p>
  </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-8">
  <div class="mx-auto max-w-4xl px-4">

    <div class="space-y-6">

      <!-- Section 1 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-file-text mr-3 text-blue-500"></i>
          1. Acceptance of Terms
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>By accessing and using trubl ("the Service"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
          <p>We reserve the right to modify these terms at any time. Your continued use of the Service following any changes indicates your acceptance of the new terms.</p>
        </div>
      </div>

      <!-- Section 2 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-person-check mr-3 text-green-500"></i>
          2. User Eligibility
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>You must be at least 18 years of age to use this Service. By using the Service, you represent and warrant that:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li>You are at least 18 years old</li>
            <li>You have the legal capacity to enter into this agreement</li>
            <li>You will comply with all applicable local, state, and federal laws</li>
          </ul>
        </div>
      </div>

      <!-- Section 3 -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-exclamation-triangle mr-3 text-red-500"></i>
          3. Prohibited Conduct
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>You agree not to engage in any of the following prohibited activities:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li>Posting false, misleading, or fraudulent content</li>
            <li>Harassing, threatening, or intimidating other users</li>
            <li>Posting sexually explicit content involving minors</li>
            <li>Soliciting or promoting illegal activities</li>
            <li>Impersonating any person or entity</li>
          </ul>
        </div>
      </div>

      <!-- Contact -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
        <h2 class="mb-4 text-2xl font-bold text-white flex items-center">
          <i class="bi bi-envelope mr-3 text-blue-400"></i>
          Contact Information
        </h2>
        <div class="space-y-3 text-gh-muted leading-relaxed">
          <p>If you have any questions about these Terms, please contact us:</p>
          <div class="mt-4 p-4 bg-gh-panel2 rounded-lg border border-gh-border">
            <p><i class="bi bi-envelope mr-2"></i>Email: legal@trubl.co</p>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

<?php include 'views/footer.php'; ?>