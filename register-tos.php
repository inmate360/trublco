<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CSRF.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// If already logged in, redirect
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

// Handle TOS acceptance
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif(isset($_POST['accept_tos']) && isset($_POST['accept_safety']) && isset($_POST['accept_privacy'])) {
        // Store TOS acceptance in session
        $_SESSION['tos_accepted'] = true;
        $_SESSION['tos_accepted_at'] = date('Y-m-d H:i:s');
        
        // Destroy CSRF token
        CSRF::destroyToken();
        
        // Redirect to registration
        header('Location: register.php');
        exit();
    } else {
        $error = 'You must accept all terms to continue';
    }
}

// Generate CSRF token
$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<div class="mx-auto max-w-4xl px-4 py-12">
  
  <!-- Header -->
  <div class="mb-8 text-center">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl border-2 border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2 text-xl font-black shadow-lg">
      <span class="bg-gradient-to-br from-gh-accent to-gh-success bg-clip-text text-transparent">TP</span>
    </div>
    <h1 class="text-3xl font-extrabold tracking-tight">Terms of Service</h1>
    <p class="mt-2 text-sm text-gh-muted">Please read carefully before creating your account</p>
  </div>

  <!-- Error Alert -->
  <?php if(!empty($error)): ?>
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-lg text-gh-danger"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-danger">Error:</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Terms Card -->
  <div class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
    
    <!-- Important Notice Banner -->
    <div class="border-b border-gh-border bg-gh-panel2 p-6">
      <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border-2 border-gh-warning bg-gh-warning/10">
          <i class="bi bi-exclamation-triangle-fill text-2xl text-gh-warning"></i>
        </div>
        <div>
          <h2 class="text-lg font-bold">18+ Adult Platform</h2>
          <p class="mt-2 text-sm text-gh-muted">
            <strong>Turnpage is a personal classifieds platform for adults seeking consensual connections.</strong>
          </p>
          <p class="mt-2 text-sm text-gh-muted">
            By accessing and using Turnpage ("the Platform", "we", "us", or "our"), you agree to be bound by these Terms of Service. 
            If you do not agree to these terms, you may not use our services.
          </p>
        </div>
      </div>
    </div>

    <!-- Age Requirement -->
    <div class="border-b border-gh-border bg-gh-danger/5 p-6">
      <div class="flex items-center gap-3">
        <i class="bi bi-shield-fill-exclamation text-2xl text-gh-danger"></i>
        <div>
          <div class="font-bold text-gh-danger">Age Requirement</div>
          <div class="text-sm text-gh-muted">
            You must be at least <strong class="text-gh-fg">18 years of age</strong> to use this platform. 
            By registering, you represent and warrant that you are of legal age.
          </div>
        </div>
      </div>
    </div>

    <!-- Terms Content -->
    <div class="space-y-6 p-6">
      
      <!-- Section 1 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-1-circle-fill text-gh-accent"></i>
          Acceptance of Terms
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>By creating an account, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service and our Privacy Policy.</p>
        </div>
      </div>

      <!-- Section 2 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-2-circle-fill text-gh-accent"></i>
          Eligibility
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>To use Turnpage, you must:</p>
          <ul class="ml-5 list-disc space-y-1">
            <li>Be at least 18 years of age or the age of majority in your jurisdiction</li>
            <li>Have the legal capacity to enter into a binding contract</li>
            <li>Not be prohibited from using the service under applicable laws</li>
            <li>Provide accurate and truthful information during registration</li>
          </ul>
        </div>
      </div>

      <!-- Section 3 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-3-circle-fill text-gh-accent"></i>
          User Conduct
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>You agree NOT to:</p>
          <ul class="ml-5 list-disc space-y-1">
            <li>Post illegal, fraudulent, or misleading content</li>
            <li>Engage in harassment, hate speech, or threatening behavior</li>
            <li>Impersonate others or create fake accounts</li>
            <li>Spam, solicit money, or conduct commercial activities without authorization</li>
            <li>Post content involving minors or non-consensual activities</li>
            <li>Share personal contact information publicly (use our messaging system)</li>
            <li>Scrape, data mine, or use automated tools to access the platform</li>
          </ul>
        </div>
      </div>

      <!-- Section 4 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-4-circle-fill text-gh-accent"></i>
          Content & Privacy
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>You retain ownership of your content, but grant us a license to display it on our platform. You are responsible for the content you post.</p>
          <p>We respect your privacy and handle your data according to our Privacy Policy. However, you acknowledge that online platforms cannot guarantee 100% security.</p>
        </div>
      </div>

      <!-- Section 5 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-5-circle-fill text-gh-accent"></i>
          Safety & Meetings
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>If you choose to meet someone in person:</p>
          <ul class="ml-5 list-disc space-y-1">
            <li>Always meet in public places first</li>
            <li>Tell a friend or family member where you're going</li>
            <li>Trust your instincts - if something feels wrong, leave</li>
            <li>Never share financial information or send money</li>
            <li>Practice safe interactions and respect boundaries</li>
          </ul>
          <p class="mt-2 font-semibold text-gh-warning">Turnpage is not responsible for offline interactions or meetings between users.</p>
        </div>
      </div>

      <!-- Section 6 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-6-circle-fill text-gh-accent"></i>
          Account Termination
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>We reserve the right to suspend or terminate accounts that violate these terms, without prior notice. Repeat offenders will be permanently banned.</p>
        </div>
      </div>

      <!-- Section 7 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-7-circle-fill text-gh-accent"></i>
          Disclaimer
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>Turnpage is provided "AS IS" without warranties of any kind. We do not verify user identities or screen listings. Use at your own risk.</p>
        </div>
      </div>

      <!-- Section 8 -->
      <div>
        <h3 class="mb-3 flex items-center gap-2 text-base font-bold">
          <i class="bi bi-8-circle-fill text-gh-accent"></i>
          Changes to Terms
        </h3>
        <div class="space-y-2 pl-7 text-sm text-gh-muted">
          <p>We may update these terms periodically. Continued use of the platform after changes constitutes acceptance of the updated terms.</p>
        </div>
      </div>

    </div>

    <!-- Version Info -->
    <div class="border-t border-gh-border bg-gh-panel2 px-6 py-4 text-center text-xs text-gh-muted">
      Last Updated: January 17, 2025 | Version: 1.0
    </div>

  </div>

  <!-- Acceptance Form -->
  <form method="POST" action="register-tos.php" class="mt-6">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    
    <div class="space-y-4 rounded-xl border border-gh-border bg-gh-panel p-6">
      
      <h3 class="text-lg font-bold">Required Agreements</h3>
      
      <!-- TOS Checkbox -->
      <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
        <input type="checkbox" 
               name="accept_tos" 
               required 
               class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
        <div class="flex-1">
          <div class="font-semibold">I accept the Terms of Service</div>
          <div class="text-sm text-gh-muted">I have read and agree to be bound by the above terms</div>
        </div>
      </label>

      <!-- Safety Checkbox -->
      <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
        <input type="checkbox" 
               name="accept_safety" 
               required 
               class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
        <div class="flex-1">
          <div class="font-semibold">I understand safety guidelines</div>
          <div class="text-sm text-gh-muted">I will follow safety practices when meeting others</div>
        </div>
      </label>

      <!-- Privacy Checkbox -->
      <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
        <input type="checkbox" 
               name="accept_privacy" 
               required 
               class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
        <div class="flex-1">
          <div class="font-semibold">I accept the Privacy Policy</div>
          <div class="text-sm text-gh-muted">I understand how my data will be collected and used</div>
        </div>
      </label>

      <button type="submit" 
              class="w-full rounded-lg bg-gh-accent px-4 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
        <i class="bi bi-check-circle-fill mr-2"></i>
        Accept & Continue to Registration
      </button>
    </div>
  </form>

  <!-- Back Link -->
  <div class="mt-6 text-center">
    <a href="index.php" class="text-sm text-gh-muted hover:text-gh-fg hover:underline">
      <i class="bi bi-arrow-left mr-1"></i>
      Back to home
    </a>
  </div>

</div>

<?php include 'views/footer.php'; ?>
