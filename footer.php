</main>

<!-- Enhanced Dark Footer with Tailwind -->
<footer class="relative mt-20 bg-gh-panel text-gh-fg">
  
  <!-- Subtle Top Border -->
  <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gh-border to-transparent"></div>
  
  <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    
    <!-- Compact Newsletter Section -->
    <div class="mb-12 rounded-xl bg-gh-panel2 p-6 backdrop-blur-sm border border-gh-border">
      <div class="flex flex-col items-center justify-between gap-4 lg:flex-row">
        <div class="text-center lg:text-left">
          <h3 class="mb-1 text-lg font-bold">Stay Updated</h3>
          <p class="text-sm text-gh-muted">Get the latest personals delivered to your inbox</p>
        </div>
        <div class="w-full lg:w-auto">
          <form class="flex flex-col gap-2 sm:flex-row">
            <input 
              type="email" 
              placeholder="Enter your email" 
              class="min-w-[260px] rounded-lg border border-gh-border bg-gh-panel px-4 py-2.5 text-sm text-gh-fg placeholder-gh-muted transition-all focus:border-gh-muted focus:outline-none focus:ring-2 focus:ring-gh-border"
              required
            >
            <button 
              type="submit" 
              class="whitespace-nowrap rounded-lg bg-gh-panel2 border border-gh-border px-6 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:bg-gh-panel active:scale-95"
            >
              Subscribe
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Main Footer Grid -->
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
      
      <!-- Brand Column -->
      <div>
        <div class="mb-5">
          <h2 class="text-xl font-bold text-gh-fg">
            trubl.co
          </h2>
          <p class="mt-3 text-sm leading-relaxed text-gh-muted">
            Your trusted platform for authentic personals. Find what your looking for.
          </p>
        </div>
        
        <!-- Social Media Icons -->
        <div class="flex flex-wrap gap-2.5">
          <a href="#" class="group flex h-9 w-9 items-center justify-center rounded-lg bg-gh-panel2 border border-gh-border transition-all hover:bg-gh-panel" aria-label="Facebook">
            <i class="bi bi-facebook text-base"></i>
          </a>
          <a href="#" class="group flex h-9 w-9 items-center justify-center rounded-lg bg-gh-panel2 border border-gh-border transition-all hover:bg-gh-panel" aria-label="Twitter">
            <i class="bi bi-twitter text-base"></i>
          </a>
          <a href="#" class="group flex h-9 w-9 items-center justify-center rounded-lg bg-gh-panel2 border border-gh-border transition-all hover:bg-gh-panel" aria-label="Instagram">
            <i class="bi bi-instagram text-base"></i>
          </a>
          <a href="#" class="group flex h-9 w-9 items-center justify-center rounded-lg bg-gh-panel2 border border-gh-border transition-all hover:bg-gh-panel" aria-label="YouTube">
            <i class="bi bi-youtube text-base"></i>
          </a>
          <a href="#" class="group flex h-9 w-9 items-center justify-center rounded-lg bg-gh-panel2 border border-gh-border transition-all hover:bg-gh-panel" aria-label="Discord">
            <i class="bi bi-discord text-base"></i>
          </a>
        </div>
      </div>

      <!-- Resources Column -->
      <div>
        <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gh-fg">Resources</h4>
        <ul class="space-y-2.5 text-sm">
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="how-it-works.php">
              <i class="bi bi-info-circle mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              How trubl Works
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="safety.php">
              <i class="bi bi-shield-check mr-2 text-gh-success group-hover:text-gh-success"></i>
              Safety Tips
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="blog.php">
              <i class="bi bi-journal-text mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              Blog
            </a>
          </li>
        </ul>
      </div>

      <!-- Support Column -->
      <div>
        <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gh-fg">Support Center</h4>
        <ul class="space-y-2.5 text-sm">
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="contact.php">
              <i class="bi bi-envelope mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              Contact Us
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="report.php">
              <i class="bi bi-flag-fill mr-2 text-red-500 group-hover:text-red-400"></i>
              Report Content
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="faq.php">
              <i class="bi bi-question-circle mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              FAQ
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="terms.php">
              <i class="bi bi-file-text mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              Terms of Service
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="privacy.php">
              <i class="bi bi-lock mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              Privacy Policy
            </a>
          </li>
        </ul>
      </div>

      <!-- Community Column -->
      <div>
        <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gh-fg">Community</h4>
        <ul class="space-y-2.5 text-sm">
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="forum.php">
              <i class="bi bi-chat-square-text-fill mr-2 text-blue-500 group-hover:text-blue-400"></i>
              Forum
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="guidelines.php">
              <i class="bi bi-book mr-2 text-gh-muted group-hover:text-gh-fg"></i>
              Guidelines
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="membership.php">
              <i class="bi bi-gem mr-2 text-purple-500 group-hover:text-purple-400"></i>
              Premium membership
            </a>
          </li>
          <li>
            <a class="group inline-flex items-center text-gh-muted transition-all hover:text-gh-fg" href="bitcoin-wallet.php">
              <i class="bi bi-currency-bitcoin mr-2 text-yellow-500 group-hover:text-yellow-400"></i>
              Bitcoin wallet
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="mt-12 border-t border-gh-border pt-8">
      <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
        
        <!-- Copyright -->
        <div class="text-center text-sm text-gh-muted md:text-left">
          <p>&copy; <?php echo date('Y'); ?> trubl. All rights reserved.</p>
        </div>
        
        <!-- Quick Links -->
        <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm">
          <a class="inline-flex items-center text-gh-muted transition-colors hover:text-gh-fg" href="sitemap.php">
            <i class="bi bi-diagram-3 mr-1.5"></i>Sitemap
          </a>
          <span class="text-gh-border">|</span>
          <a class="inline-flex items-center text-gh-muted transition-colors hover:text-gh-fg" href="choose-location.php">
            <i class="bi bi-geo-alt mr-1.5"></i>Browse locations
          </a>
          <span class="text-gh-border">|</span>
          <a class="inline-flex items-center text-gh-muted transition-colors hover:text-gh-fg" href="api-docs.php">
            <i class="bi bi-code-square mr-1.5"></i>API docs
          </a>
        </div>
      </div>

      <!-- Trust Badge -->
      <div class="mt-6 text-center">
        <div class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-xs">
          <i class="bi bi-shield-check text-gh-success"></i>
          <span class="text-gh-muted">Encrypted & Secure Platform</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Scroll to Top Button -->
  <button 
    id="scrollToTop" 
    class="fixed bottom-15 right-8 hidden h-11 w-11 items-center justify-center rounded-lg bg-gh-panel2 border border-gh-border text-gh-fg shadow-xl transition-all hover:bg-gh-panel active:scale-95"
    aria-label="Scroll to top"
  >
    <i class="bi bi-arrow-up text-lg"></i>
  </button>
</footer>

<script>
  // Mobile menu toggle
  const mobileBtn = document.getElementById('mobileBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  if(mobileBtn && mobileMenu) {
    mobileBtn.addEventListener('click', () => {
      mobileMenu.classList.toggle('hidden');
    });
  }

  // User dropdown
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userMenu = document.getElementById('userMenu');
  if(userMenuBtn && userMenu) {
    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = userMenu.dataset.open === 'true';
      userMenu.dataset.open = isOpen ? 'false' : 'true';
    });
    
    document.addEventListener('click', (e) => {
      if(!userMenu.contains(e.target)) {
        userMenu.dataset.open = 'false';
      }
    });
  }

  // Scroll to Top Button
  const scrollToTopBtn = document.getElementById('scrollToTop');
  if(scrollToTopBtn) {
    window.addEventListener('scroll', () => {
      if(window.scrollY > 300) {
        scrollToTopBtn.classList.remove('hidden');
        scrollToTopBtn.classList.add('flex');
      } else {
        scrollToTopBtn.classList.add('hidden');
        scrollToTopBtn.classList.remove('flex');
      }
    });
    
    scrollToTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Enable location helper
  function enableLocationFromBanner() {
    if(!navigator.geolocation) {
      alert('Geolocation is not supported by your browser');
      return;
    }
    
    navigator.geolocation.getCurrentPosition(
      (position) => {
        updateLocationSilent(position.coords.latitude, position.coords.longitude, () => {
          location.reload();
        });
      },
      (error) => {
        console.error('Geolocation error:', error);
        alert('Unable to get your location. Please enable location access.');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  }

  function updateLocationSilent(latitude, longitude, callback) {
    const formData = new FormData();
    formData.append('action', 'update_location');
    formData.append('latitude', latitude);
    formData.append('longitude', longitude);
    formData.append('autodetected', 'true');
    
    fetch('api/location.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if(data.success && callback) callback();
    })
    .catch(error => console.error('Error updating location:', error));
  }

  // Theme toggle
  function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.classList.toggle('dark');
    html.dataset.theme = newTheme;
    
    fetch('api/update-theme.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme: newTheme })
    });
  }
</script>
</body>
</html>
