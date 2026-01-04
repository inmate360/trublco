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

$success = '';
$error = '';

// Create feedback table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('bug', 'feature', 'improvement', 'complaint', 'other') DEFAULT 'other',
        status ENUM('new', 'reviewed', 'resolved') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_created (created_at DESC)
    )";
    $db->exec($create_table);
} catch(PDOException $e) {
    // Table might already exist
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $type = $_POST['type'] ?? 'other';
    
    if(empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        $query = "INSERT INTO feedback (user_id, name, email, subject, message, type) 
                  VALUES (:user_id, :name, :email, :subject, :message, :type)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        
        if($stmt->execute()) {
            $success = 'Thank you for your feedback! We\'ll review it shortly.';
        } else {
            $error = 'Failed to submit feedback. Please try again.';
        }
    }
}

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-12">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDEzNGg3djdjMCAxLjEwNS0uODk1IDItMiAyaC0zYy0xLjEwNSAwLTItLjg5NS0yLTJ2LTd6bTAtMTRoN3Y3YzAgMS4xMDUtLjg5NSAyLTIgMmgtM2MtMS4xMDUgMC0yLS44OTUtMi0ydi03eiIvPjwvZz48L2c+PC9zdmc+')] opacity-10"></div>
    
    <div class="relative mx-auto max-w-6xl px-4 text-center">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-3xl backdrop-blur-sm">
            <i class="bi bi-chat-heart-fill text-pink-300"></i>
        </div>
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            Send Feedback
        </h1>
        <p class="text-base text-pink-200">Help us improve trubl! Share bugs, ideas, or suggestions.</p>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-4xl px-4">

        <?php if($success): ?>
        <div class="mb-8 rounded-lg border border-green-500/30 bg-green-500/10 p-6 text-center">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-green-500 text-3xl text-white">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h3 class="mb-2 text-xl font-bold text-green-400">Feedback Submitted!</h3>
            <p class="text-green-300"><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <!-- Feedback Type Cards -->
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-red-500">
                <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-red-600 text-xl text-white">
                    <i class="bi bi-bug-fill"></i>
                </div>
                <h3 class="text-sm font-bold text-white">Report Bug</h3>
                <p class="text-xs text-gh-muted">Something broken?</p>
            </div>

            <div class="rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-blue-500">
                <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 text-xl text-white">
                    <i class="bi bi-lightbulb-fill"></i>
                </div>
                <h3 class="text-sm font-bold text-white">New Feature</h3>
                <p class="text-xs text-gh-muted">Got an idea?</p>
            </div>

            <div class="rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-green-500">
                <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-xl text-white">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                </div>
                <h3 class="text-sm font-bold text-white">Improvement</h3>
                <p class="text-xs text-gh-muted">Make it better</p>
            </div>

            <div class="rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-purple-500">
                <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-pink-600 text-xl text-white">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <h3 class="text-sm font-bold text-white">General</h3>
                <p class="text-xs text-gh-muted">Something else</p>
            </div>
        </div>

        <!-- Feedback Form -->
        <div class="rounded-lg border border-gh-border bg-gh-panel p-8">
            
            <?php if($error): ?>
            <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
                <i class="bi bi-exclamation-circle-fill mt-0.5 text-red-500"></i>
                <div>
                    <p class="font-semibold text-red-400">Error</p>
                    <p class="text-sm text-red-300"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-white">
                            <i class="bi bi-person-fill mr-1 text-gh-accent"></i>
                            Your Name
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            required
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                            placeholder="Your name"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-white">
                            <i class="bi bi-envelope-fill mr-1 text-gh-accent"></i>
                            Email Address
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            required
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                            placeholder="your@email.com"
                        >
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-white">
                            <i class="bi bi-tag-fill mr-1 text-gh-accent"></i>
                            Feedback Type
                        </label>
                        <select 
                            name="type" 
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                        >
                            <option value="bug">🐛 Bug Report</option>
                            <option value="feature">💡 Feature Request</option>
                            <option value="improvement">⬆️ Improvement</option>
                            <option value="complaint">😞 Complaint</option>
                            <option value="other">💬 Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-white">
                            <i class="bi bi-chat-left-text-fill mr-1 text-gh-accent"></i>
                            Subject
                        </label>
                        <input 
                            type="text" 
                            name="subject" 
                            required
                            class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                            placeholder="Brief summary"
                        >
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-white">
                        <i class="bi bi-pen-fill mr-1 text-gh-accent"></i>
                        Details
                    </label>
                    <textarea 
                        name="message" 
                        rows="6" 
                        required
                        class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                        placeholder="Tell us more... Be as detailed as possible."
                    ></textarea>
                </div>

                <button 
                    type="submit"
                    class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110"
                >
                    <i class="bi bi-send-fill"></i>
                    Submit Feedback
                    <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                </button>
            </form>
        </div>

        <!-- Additional Resources -->
        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <a href="mailto:support@trubl.co" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-envelope-fill mb-2 text-3xl text-gh-accent"></i>
                <h3 class="mb-1 font-semibold text-white">Email Us</h3>
                <p class="text-xs text-gh-muted">support@trubl.co</p>
            </a>

            <a href="faq.php" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-question-circle-fill mb-2 text-3xl text-yellow-500"></i>
                <h3 class="mb-1 font-semibold text-white">FAQ</h3>
                <p class="text-xs text-gh-muted">Quick answers</p>
            </a>

            <a href="forum.php" class="group rounded-lg border border-gh-border bg-gh-panel p-4 text-center transition-all hover:border-gh-accent">
                <i class="bi bi-people-fill mb-2 text-3xl text-blue-500"></i>
                <h3 class="mb-1 font-semibold text-white">Community</h3>
                <p class="text-xs text-gh-muted">Join discussions</p>
            </a>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
