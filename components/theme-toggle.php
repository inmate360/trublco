<?php
// Get user's current theme
$current_theme = 'dark';
if(isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../classes/ThemeSwitcher.php';
    $themeSwitcher = new ThemeSwitcher($db);
    $current_theme = $themeSwitcher->getUserTheme($_SESSION['user_id']);
}
?>

<div class="theme-toggle-wrapper" id="themeToggle">
    <input type="checkbox" class="theme-checkbox" id="themeCheckbox" <?php echo $current_theme === 'light' ? 'checked' : ''; ?>>
    <label for="themeCheckbox" class="theme-label">
        <span class="theme-ball"></span>
    </label>
</div>

<style>
.theme-toggle-wrapper {
    position: fixed;
    bottom: 100px;
    right: 20px;
    z-index: 999;
}

.theme-checkbox {
    opacity: 0;
    position: absolute;
}

.theme-label {
    background-color: #1e293b;
    border-radius: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px;
    position: relative;
    height: 26px;
    width: 50px;
    transform: scale(1.5);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    transition: background-color 0.3s;
}

.theme-label .theme-ball {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 2px;
    height: 22px;
    width: 22px;
    transform: translateX(0px);
    transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 2px 8px rgba(66, 103, 245, 0.5);
}

.theme-checkbox:checked + .theme-label {
    background-color: #f1f5f9;
}

.theme-checkbox:checked + .theme-label .theme-ball {
    transform: translateX(24px);
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.5);
}

.theme-label:hover {
    transform: scale(1.6);
}

.theme-label:active .theme-ball {
    width: 28px;
}

/* Sun and Moon Icons */
.theme-label::before {
    content: '🌙';
    font-size: 14px;
    position: absolute;
    left: 6px;
    top: 50%;
    transform: translateY(-50%);
    transition: opacity 0.3s;
}

.theme-label::after {
    content: '☀️';
    font-size: 14px;
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0;
    transition: opacity 0.3s;
}

.theme-checkbox:checked + .theme-label::before {
    opacity: 0;
}

.theme-checkbox:checked + .theme-label::after {
    opacity: 1;
}

@media (max-width: 768px) {
    .theme-toggle-wrapper {
        bottom: 90px;
        right: 15px;
    }
    
    .theme-label {
        transform: scale(1.3);
    }
    
    .theme-label:hover {
        transform: scale(1.4);
    }
}
</style>

<script>
// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = '<?php echo $current_theme; ?>';
    applyTheme(savedTheme);
    
    // Add event listener to checkbox
    const checkbox = document.getElementById('themeCheckbox');
    if(checkbox) {
        checkbox.addEventListener('change', function() {
            const newTheme = this.checked ? 'light' : 'dark';
            applyTheme(newTheme);
            
            // Save to server
            <?php if(isset($_SESSION['user_id'])): ?>
            saveThemePreference(newTheme);
            <?php else: ?>
            // Save to localStorage for non-logged-in users
            localStorage.setItem('theme', newTheme);
            <?php endif; ?>
        });
    }
});

function applyTheme(theme) {
    const root = document.documentElement;
    root.setAttribute('data-theme', theme);
    
    const checkbox = document.getElementById('themeCheckbox');
    if(checkbox) {
        checkbox.checked = (theme === 'light');
    }
    
    // Animate the transition
    document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
}

<?php if(isset($_SESSION['user_id'])): ?>
function saveThemePreference(theme) {
    fetch('/api/theme.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_theme&theme=' + theme
    })
    .then(response => response.json())
    .then(data => {
        if(!data.success) {
            console.error('Failed to save theme preference');
        }
    })
    .catch(error => {
        console.error('Error saving theme:', error);
    });
}
<?php endif; ?>
</script>