<?php
/**
 * Admin Panel - Perplexity AI Integration
 * Configure and test Perplexity API integration
 */

session_start();

// Check admin authentication
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PerplexityAI.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = 'success';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'save_api_key':
                $api_key = trim($_POST['api_key']);

                try {
                    // Test the API key first
                    $pplx = new PerplexityAI($api_key);
                    $test_result = $pplx->testConnection();

                    if($test_result) {
                        // Save to database
                        $query = "INSERT INTO site_settings (setting_key, setting_value, updated_at) 
                                  VALUES ('perplexity_api_key', :api_key, NOW()) 
                                  ON DUPLICATE KEY UPDATE setting_value = :api_key, updated_at = NOW()";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':api_key', $api_key);
                        $stmt->execute();

                        $message = 'API key saved successfully and verified!';
                        $message_type = 'success';
                    } else {
                        $message = 'API key verification failed. Please check your key.';
                        $message_type = 'error';
                    }
                } catch(Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'test_query':
                $test_query = $_POST['test_query'];
                $model = $_POST['model'] ?? 'sonar-pro';

                try {
                    // Get API key
                    $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $result = $stmt->fetch();

                    if($result) {
                        $pplx = new PerplexityAI($result['setting_value']);
                        $response = $pplx->search($test_query, $model);
                        $formatted = $pplx->formatResponse($response);

                        $_SESSION['test_response'] = $formatted;
                        $message = 'Query executed successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Please set your API key first.';
                        $message_type = 'error';
                    }
                } catch(Exception $e) {
                    $message = 'Query failed: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'delete_api_key':
                try {
                    $query = "DELETE FROM site_settings WHERE setting_key = 'perplexity_api_key'";
                    $stmt = $db->prepare($query);
                    $stmt->execute();

                    $message = 'API key removed successfully.';
                    $message_type = 'success';
                } catch(Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get current API key (masked)
$current_api_key = '';
$api_key_exists = false;
try {
    $query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();

    if($result && !empty($result['setting_value'])) {
        $api_key_exists = true;
        $key = $result['setting_value'];
        $current_api_key = substr($key, 0, 8) . str_repeat('*', strlen($key) - 12) . substr($key, -4);
    }
} catch(Exception $e) {
    error_log('Error fetching API key: ' . $e->getMessage());
}

$page_title = 'Perplexity AI Integration';
include 'header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1>
                <i class="fas fa-brain"></i>
                Perplexity AI Integration
            </h1>
            <p class="subtitle">Configure Perplexity Sonar API for AI-powered search and Q&A</p>
        </div>
        <a href="dashboard.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- API Configuration -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-key"></i>
                    API Configuration
                </h2>
            </div>
            <div class="card-body">
                <?php if($api_key_exists): ?>
                    <div class="info-box success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>API Key Active</strong>
                            <p>Current key: <?php echo htmlspecialchars($current_api_key); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="info-box warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>No API Key Configured</strong>
                            <p>Add your Perplexity API key to enable AI features</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="form" style="margin-top: 1.5rem;">
                    <input type="hidden" name="action" value="save_api_key">

                    <div class="form-group">
                        <label for="api_key">
                            <i class="fas fa-key"></i>
                            Perplexity API Key
                        </label>
                        <input type="text" 
                               id="api_key" 
                               name="api_key" 
                               class="form-control" 
                               placeholder="pplx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                               required>
                        <small class="form-text">
                            Get your API key from 
                            <a href="https://www.perplexity.ai/settings/api" target="_blank">
                                Perplexity API Settings <i class="fas fa-external-link-alt"></i>
                            </a>
                        </small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save & Verify API Key
                        </button>
                        <?php if($api_key_exists): ?>
                            <button type="button" onclick="deleteApiKey()" class="btn btn-danger">
                                <i class="fas fa-trash"></i>
                                Remove API Key
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Available Models -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-robot"></i>
                    Available Models
                </h2>
            </div>
            <div class="card-body">
                <div class="model-list">
                    <div class="model-item">
                        <div class="model-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="model-info">
                            <h3>Sonar</h3>
                            <p>Fast, affordable, real-time web search</p>
                            <span class="badge badge-success">Lower Cost</span>
                        </div>
                    </div>

                    <div class="model-item">
                        <div class="model-icon" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="model-info">
                            <h3>Sonar Pro</h3>
                            <p>Advanced model for complex queries</p>
                            <span class="badge badge-warning">Premium</span>
                        </div>
                    </div>

                    <div class="model-item">
                        <div class="model-icon" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                            <i class="fas fa-brain"></i>
                        </div>
                        <div class="model-info">
                            <h3>Sonar Reasoning</h3>
                            <p>Deep reasoning capabilities</p>
                            <span class="badge badge-danger">Highest Quality</span>
                        </div>
                    </div>
                </div>

                <div class="info-box info" style="margin-top: 1.5rem;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Model Selection</strong>
                        <p>Choose based on your needs: Sonar for speed, Sonar Pro for quality, Sonar Reasoning for complex analysis.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Query Interface -->
    <?php if($api_key_exists): ?>
    <div class="card">
        <div class="card-header">
            <h2>
                <i class="fas fa-vial"></i>
                Test API Query
            </h2>
        </div>
        <div class="card-body">
            <form method="POST" class="form">
                <input type="hidden" name="action" value="test_query">

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="test_query">
                            <i class="fas fa-search"></i>
                            Test Query
                        </label>
                        <input type="text" 
                               id="test_query" 
                               name="test_query" 
                               class="form-control" 
                               placeholder="What are the latest developments in AI?"
                               required>
                    </div>

                    <div class="form-group" style="min-width: 200px;">
                        <label for="model">
                            <i class="fas fa-robot"></i>
                            Model
                        </label>
                        <select id="model" name="model" class="form-control">
                            <option value="sonar">Sonar (Fast)</option>
                            <option value="sonar-pro" selected>Sonar Pro (Balanced)</option>
                            <option value="sonar-reasoning">Sonar Reasoning (Best)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Send Test Query
                </button>
            </form>

            <?php if(isset($_SESSION['test_response'])): ?>
                <div class="test-response" style="margin-top: 2rem;">
                    <h3><i class="fas fa-comment-dots"></i> Response</h3>
                    <div class="response-box">
                        <div class="response-content">
                            <?php echo nl2br(htmlspecialchars($_SESSION['test_response']['content'])); ?>
                        </div>

                        <?php if(!empty($_SESSION['test_response']['citations'])): ?>
                            <div class="citations">
                                <h4><i class="fas fa-link"></i> Citations</h4>
                                <ul>
                                    <?php foreach($_SESSION['test_response']['citations'] as $citation): ?>
                                        <li>
                                            <a href="<?php echo htmlspecialchars($citation); ?>" target="_blank">
                                                <?php echo htmlspecialchars($citation); ?>
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if($_SESSION['test_response']['usage']): ?>
                            <div class="usage-stats">
                                <span><i class="fas fa-arrow-up"></i> Input: <?php echo $_SESSION['test_response']['usage']['prompt_tokens']; ?> tokens</span>
                                <span><i class="fas fa-arrow-down"></i> Output: <?php echo $_SESSION['test_response']['usage']['completion_tokens']; ?> tokens</span>
                                <span><i class="fas fa-dollar-sign"></i> Cost: $<?php echo number_format($_SESSION['test_response']['usage']['total_cost'] ?? 0, 4); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php unset($_SESSION['test_response']); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Documentation -->
    <div class="card">
        <div class="card-header">
            <h2>
                <i class="fas fa-book"></i>
                Integration Documentation
            </h2>
        </div>
        <div class="card-body">
            <div class="doc-section">
                <h3><i class="fas fa-code"></i> Usage Example</h3>
                <pre class="code-block"><code>&lt;?php
require_once 'includes/PerplexityAI.php';

// Initialize with API key from database
$query = "SELECT setting_value FROM site_settings WHERE setting_key = 'perplexity_api_key'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch();

$pplx = new PerplexityAI($result['setting_value']);

// Simple search
$response = $pplx->search("What is the weather today?");
$content = $pplx->getContent($response);

// Ask with context
$response = $pplx->ask(
    "Explain quantum computing",
    "You are a helpful science tutor"
);

// Multi-turn conversation
$history = [
    ['role' => 'user', 'content' => 'Tell me about PHP'],
    ['role' => 'assistant', 'content' => 'PHP is a server-side...']
];
$response = $pplx->continue_conversation($history, "What about its history?");
?&gt;</code></pre>
            </div>

            <div class="doc-section">
                <h3><i class="fas fa-plug"></i> API Features</h3>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Real-time web search with citations</li>
                    <li><i class="fas fa-check"></i> Multiple AI models (Sonar, Sonar Pro, Sonar Reasoning)</li>
                    <li><i class="fas fa-check"></i> Multi-turn conversations</li>
                    <li><i class="fas fa-check"></i> Customizable parameters (temperature, max_tokens)</li>
                    <li><i class="fas fa-check"></i> Usage tracking and cost monitoring</li>
                    <li><i class="fas fa-check"></i> Streaming responses support</li>
                </ul>
            </div>

            <div class="doc-section">
                <h3><i class="fas fa-link"></i> Resources</h3>
                <div class="resource-links">
                    <a href="https://docs.perplexity.ai" target="_blank" class="resource-link">
                        <i class="fas fa-book-open"></i>
                        <div>
                            <strong>Official Documentation</strong>
                            <p>Complete API reference and guides</p>
                        </div>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <a href="https://www.perplexity.ai/settings/api" target="_blank" class="resource-link">
                        <i class="fas fa-key"></i>
                        <div>
                            <strong>API Settings</strong>
                            <p>Manage your API keys and usage</p>
                        </div>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <a href="https://www.perplexity.ai/api-platform" target="_blank" class="resource-link">
                        <i class="fas fa-rocket"></i>
                        <div>
                            <strong>API Platform</strong>
                            <p>Pricing and features overview</p>
                        </div>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.model-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.model-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-tertiary);
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
}

.model-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.model-info {
    flex: 1;
}

.model-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    color: var(--text-primary);
}

.model-info p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success { background: #10b981; color: white; }
.badge-warning { background: #f59e0b; color: white; }
.badge-danger { background: #ef4444; color: white; }

.info-box {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid;
}

.info-box i {
    font-size: 1.5rem;
}

.info-box.success {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.info-box.warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.info-box.info {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.info-box div strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
}

.info-box div p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.form-row {
    display: flex;
    gap: 1rem;
}

.response-box {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 1.5rem;
}

.response-content {
    color: var(--text-primary);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.citations {
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    margin-bottom: 1rem;
}

.citations h4 {
    margin: 0 0 0.75rem 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.citations ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.citations li {
    margin-bottom: 0.5rem;
}

.citations a {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.citations a:hover {
    text-decoration: underline;
}

.usage-stats {
    display: flex;
    gap: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.usage-stats span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.code-block {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 1rem;
    overflow-x: auto;
}

.code-block code {
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    color: #10b981;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.feature-list li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    color: var(--text-primary);
}

.feature-list i {
    color: #10b981;
}

.resource-links {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.resource-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    text-decoration: none;
    transition: all 0.2s;
}

.resource-link:hover {
    border-color: var(--primary-color);
    transform: translateX(4px);
}

.resource-link > i:first-child {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.resource-link > div {
    flex: 1;
}

.resource-link strong {
    display: block;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.resource-link p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.resource-link > i:last-child {
    color: var(--text-secondary);
}

.doc-section {
    margin-bottom: 2rem;
}

.doc-section:last-child {
    margin-bottom: 0;
}

.doc-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }

    .usage-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<script>
function deleteApiKey() {
    if(confirm('Are you sure you want to remove the API key? This will disable all Perplexity AI features.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_api_key">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'footer.php'; ?>
