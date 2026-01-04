// Real-time features and interactions - MOBILE OPTIMIZED

// Prevent zoom on double-tap for iOS
let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);

// Update online status every 2 minutes
if(typeof userId !== 'undefined' && userId) {
    setInterval(function() {
        fetch('/update-online-status.php')
            .then(response => response.json())
            .catch(error => console.log('Status update error:', error));
    }, 120000); // 2 minutes
    
    // Update on page visibility change
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            fetch('/update-online-status.php')
                .then(response => response.json())
                .catch(error => console.log('Status update error:', error));
        }
    });
}

// Notification polling (every 30 seconds)
if(typeof userId !== 'undefined' && userId) {
    setInterval(function() {
        fetch('/get-notification-count.php')
            .then(response => response.json())
            .then(data => {
                if(data.count > 0) {
                    updateNotificationBadge(data.count);
                }
            })
            .catch(error => console.log('Notification check error:', error));
    }, 30000); // 30 seconds
}

function updateNotificationBadge(count) {
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    });
}

// Smooth scroll for anchors
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if(href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            document.querySelector(href).scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Add loading animation to forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if(submitBtn && !submitBtn.disabled) {
            submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> Processing...';
            submitBtn.disabled = true;
        }
    });
});

// Image preview for file uploads
document.querySelectorAll('input[type="file"][accept*="image"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if(file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Create or update preview
                let preview = document.getElementById('image-preview');
                if(!preview) {
                    preview = document.createElement('img');
                    preview.id = 'image-preview';
                    preview.style.cssText = 'max-width: 200px; max-height: 200px; margin-top: 1rem; border-radius: 10px; display: block;';
                    input.parentElement.appendChild(preview);
                }
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
});

// Add heart animation on favorite
document.querySelectorAll('.btn-favorite, [data-action="favorite"]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        // Create floating heart
        const heart = document.createElement('div');
        heart.innerHTML = '💕';
        heart.style.cssText = `
            position: fixed;
            left: ${e.clientX}px;
            top: ${e.clientY}px;
            font-size: 2rem;
            pointer-events: none;
            animation: floatHeart 1s ease-out forwards;
            z-index: 9999;
        `;
        document.body.appendChild(heart);
        
        setTimeout(() => heart.remove(), 1000);
    });
});

// CSS animation for floating heart
const style = document.createElement('style');
style.textContent = `
    @keyframes floatHeart {
        0% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        100% {
            opacity: 0;
            transform: translateY(-100px) scale(1.5);
        }
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Toast notification system
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'var(--success-green)' : 'var(--danger-red)'};
        color: white;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
        max-width: 90%;
        word-wrap: break-word;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add toast animations
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    
    @media (max-width: 768px) {
        .toast {
            left: 10px !important;
            right: 10px !important;
            bottom: 10px !important;
        }
    }
`;
document.head.appendChild(toastStyle);

// Lazy loading for images
if('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if(entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}

// Touch-friendly improvements
document.addEventListener('DOMContentLoaded', function() {
    // Add touch class for better touch handling
    if('ontouchstart' in window) {
        document.body.classList.add('touch-device');
    }
    
    // Improve button tap experience
    document.querySelectorAll('button, .btn-primary, .btn-secondary, .btn-danger, .btn-success').forEach(btn => {
        btn.addEventListener('touchstart', function() {
            this.style.opacity = '0.7';
        });
        btn.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
    });
    
    // Prevent scroll when menu is open on mobile
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');
    
    if(menuToggle && navMenu) {
        menuToggle.addEventListener('click', function() {
            if(navMenu.classList.contains('active')) {
                document.body.style.overflow = '';
            } else {
                document.body.style.overflow = 'hidden';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if(navMenu.classList.contains('active') && 
               !navMenu.contains(e.target) && 
               !menuToggle.contains(e.target)) {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Close menu when clicking a link
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }
});

// Service Worker for offline support (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Uncomment to enable service worker
        // navigator.serviceWorker.register('/sw.js');
    });
}

// Handle orientation change
window.addEventListener('orientationchange', function() {
    // Force layout recalculation
    document.body.style.height = window.innerHeight + 'px';
    setTimeout(function() {
        document.body.style.height = '';
    }, 500);
});

// Network status indicator
window.addEventListener('online', function() {
    showToast('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showToast('No internet connection', 'error');
});

console.log('%c📋 Turnpage - Personals & Local Hookups', 'color: #4267F5; font-size: 24px; font-weight: bold;');
console.log('%cMobile-Optimized Experience 📱', 'color: #1D9BF0; font-size: 16px;');