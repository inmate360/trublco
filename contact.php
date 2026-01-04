<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if(empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        try {
            // Create contact_messages table if it doesn't exist
            $create_table = "CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('new', 'read', 'replied') DEFAULT 'new',
                INDEX idx_status (status),
                INDEX idx_created (created_at DESC)
            )";
            $db->exec($create_table);
            
            // Insert contact message
            $query = "INSERT INTO contact_messages (name, email, subject, message) 
                      VALUES (:name, :email, :subject, :message)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            
            if($stmt->execute()) {
                $success = 'Your message has been sent! We\'ll get back to you soon.';
                
                // Optional: Send email notification to admin
                // mail('support@trubl.co', 'New Contact Form: ' . $subject, $message, 'From: ' . $email);
            } else {
                $error = 'Failed to send message. Please try again.';
            }
        } catch(PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            error_log('Contact form error: ' . $e->getMessage());
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
            <i class="bi bi-envelope-heart-fill text-pink-300"></i>
        </div>
        <h1 class="mb-3 bg-gradient-to-r from-white via-pink-200 to-white bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
            Get in Touch
        </h1>
        <p class="text-base text-pink-200">Have a question or feedback? We'd love to hear from you!</p>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gh-bg py-12">
    <div class="mx-auto max-w-6xl px-4">
        
        <div class="grid gap-8 lg:grid-cols-3">
            
            <!-- Contact Info Sidebar -->
            <div class="space-y-4 lg:col-span-1">
                
                <!-- Live Chat Card -->
                <div class="group rounded-lg border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-cyan-600 text-xl text-white shadow-lg">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Live Chat</h3>
                    <p class="mb-3 text-sm text-gh-muted">Chat with our support team in real-time</p>
                    <button class="flex items-center gap-2 text-sm font-semibold text-gh-accent transition-colors hover:text-gh-success">
                        Start Chat
                        <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </button>
                </div>

                <!-- Email Card -->
                <div class="group rounded-lg border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-pink-500 to-purple-600 text-xl text-white shadow-lg">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Email Support</h3>
                    <a href="mailto:support@trubl.co" class="mb-2 block text-sm text-gh-accent hover:underline">
                        support@trubl.co
                    </a>
                    <p class="text-xs text-gh-muted">Usually within 24 hours</p>
                </div>

                <!-- Response Time Card -->
                <div class="rounded-lg border border-yellow-500/30 bg-gradient-to-br from-yellow-600/10 to-orange-600/10 p-6">
                    <div class="mb-3 flex items-center gap-2">
                        <i class="bi bi-clock-fill text-yellow-500"></i>
                        <h3 class="font-bold text-white">Response Time</h3>
                    </div>
                    <ul class="space-y-2 text-sm text-gh-muted">
                        <li class="flex items-center gap-2">
                            <i class="bi bi-check-circle-fill text-green-500"></i>
                            <span>Live Chat: Instant</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="bi bi-check-circle-fill text-green-500"></i>
                            <span>Email: 24-48 hours</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="bi bi-check-circle-fill text-green-500"></i>
                            <span>Form: 1-2 business days</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="lg:col-span-2">
                <div class="rounded-lg border border-gh-border bg-gh-panel p-8">
                    <h2 class="mb-6 text-2xl font-bold text-white">
                        <i class="bi bi-send-fill mr-2 text-gh-accent"></i>
                        Send Us a Message
                    </h2>

                    <?php if($success): ?>
                    <div class="mb-6 flex items-start gap-3 rounded-lg border border-green-500/30 bg-green-500/10 p-4">
                        <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                        <div>
                            <p class="font-semibold text-green-400">Success!</p>
                            <p class="text-sm text-green-300"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($error): ?>
                    <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-4">
                        <i class="bi bi-exclamation-circle-fill mt-0.5 text-red-500"></i>
                        <div>
                            <p class="font-semibold text-red-400">Error</p>
                            <p class="text-sm text-red-300"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
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
                                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                                    placeholder="John Doe"
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
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                                    placeholder="john@example.com"
                                >
                            </div>
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
                                value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>"
                                class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                                placeholder="What can we help you with?"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-white">
                                <i class="bi bi-pen-fill mr-1 text-gh-accent"></i>
                                Message
                            </label>
                            <textarea 
                                name="message" 
                                rows="6" 
                                required
                                class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-3 text-white transition-colors focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/20"
                                placeholder="Tell us more about your inquiry..."
                            ><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>

                        <button 
                            type="submit"
                            class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110"
                        >
                            <i class="bi bi-send-fill"></i>
                            Send Message
                            <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                        </button>
                    </form>
                </div>

                <!-- Quick Links -->
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <a href="faq.php" class="group flex items-center gap-3 rounded-lg border border-gh-border bg-gh-panel p-4 transition-all hover:border-gh-accent">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 text-white">
                            <i class="bi bi-question-circle-fill"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-white">FAQ</h3>
                            <p class="text-xs text-gh-muted">Find quick answers</p>
                        </div>
                        <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
                    </a>

                    <a href="forum.php" class="group flex items-center gap-3 rounded-lg border border-gh-border bg-gh-panel p-4 transition-all hover:border-gh-accent">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-yellow-500 to-orange-600 text-white">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-white">Community</h3>
                            <p class="text-xs text-gh-muted">Join discussions</p>
                        </div>
                        <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
