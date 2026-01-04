// Story Modal Rendering and Interaction
function renderStoryModal(story) {
  const content = document.getElementById('storyModalContent');
  const loggedIn = story.logged_in || false;

  const categories = {
    'hookup': 'Hookup Stories',
    'first-time': 'First Time',
    'encounter': 'Random Encounter',
    'dating': 'Dating Experience',
    'threesome': 'Group Experience',
    'casual': 'Casual Meet',
    'app': 'App Hookup',
    'other': 'Other'
  };

  const categoryLabel = categories[story.category] || 'Other';
  const avgRating = story.avg_rating ? parseFloat(story.avg_rating).toFixed(1) : 'N/A';
  const userRating = story.user_rating ? parseInt(story.user_rating) : 0;

  let html = `
    <!-- Story Header -->
    <div class="mb-6">
      <div class="mb-3 flex items-center justify-between">
        <span class="rounded-full bg-gh-accent/20 px-3 py-1.5 text-sm font-semibold text-gh-accent">
          ${escapeHtml(categoryLabel)}
        </span>
        <div class="flex items-center gap-2">
          ${story.location ? `<span class="text-sm text-gh-muted"><i class="bi bi-geo-alt-fill"></i> ${escapeHtml(story.location)}</span>` : ''}
        </div>
      </div>

      <h2 class="mb-3 text-2xl font-extrabold text-white">${escapeHtml(story.title)}</h2>

      <div class="flex flex-wrap items-center gap-4 text-sm text-gh-muted">
        <span><i class="bi bi-person-circle"></i> ${escapeHtml(story.author_name)}</span>
        ${story.age ? `<span><i class="bi bi-calendar"></i> ${story.age} years old</span>` : ''}
        ${story.gender ? `<span><i class="bi bi-gender-ambiguous"></i> ${getGenderLabel(story.gender)}</span>` : ''}
        <span><i class="bi bi-clock"></i> ${formatDate(story.created_at)}</span>
        <span><i class="bi bi-eye-fill"></i> ${parseInt(story.views)} views</span>
      </div>
    </div>

    <!-- Story Content -->
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel2 p-6">
      <div class="prose prose-invert max-w-none whitespace-pre-wrap text-gh-fg">
        ${escapeHtml(story.content)}
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="mb-6 flex flex-wrap items-center gap-3">

      <!-- Like Button -->
      <button onclick="toggleLike(${story.id})" 
              id="likeBtn-${story.id}"
              class="flex items-center gap-2 rounded-lg ${story.user_liked ? 'bg-red-500/20 text-red-500' : 'bg-gh-panel text-gh-fg'} border border-gh-border px-4 py-2 text-sm font-semibold transition-all hover:bg-gh-panel2">
        <i class="bi ${story.user_liked ? 'bi-heart-fill' : 'bi-heart'}"></i>
        <span id="likeCount-${story.id}">${parseInt(story.like_count)}</span>
      </button>

      <!-- Rating -->
      <div class="flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2">
        <span class="text-sm font-semibold text-gh-muted">Rate:</span>
        <div class="flex gap-1" id="ratingStars-${story.id}">
          ${generateRatingStars(story.id, userRating)}
        </div>
        <span class="ml-2 text-sm text-yellow-500">${avgRating}</span>
      </div>

      ${loggedIn ? `
      <!-- Message Author Button -->
      <button onclick="messageAuthor(${story.id}, '${escapeHtml(story.author_name)}')" 
              class="flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-gh-fg transition-all hover:bg-gh-panel2">
        <i class="bi bi-envelope-fill"></i>
        Message Author
      </button>
      ` : ''}
    </div>

    <!-- Comments Section -->
    <div class="border-t border-gh-border pt-6">
      <h3 class="mb-4 text-lg font-bold text-white">
        Comments (${parseInt(story.comment_count)})
      </h3>

      ${loggedIn ? `
      <!-- Add Comment Form -->
      <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel2 p-4">
        <textarea id="commentInput-${story.id}" 
                  placeholder="Share your thoughts..." 
                  rows="3"
                  class="w-full rounded-lg border border-gh-border bg-gh-panel px-3 py-2 text-sm text-gh-fg placeholder-gh-muted focus:border-gh-accent focus:outline-none"></textarea>
        <button onclick="postComment(${story.id})" 
                class="mt-2 rounded-lg bg-gh-accent px-4 py-2 text-sm font-semibold text-white transition-all hover:brightness-110">
          <i class="bi bi-send-fill mr-1"></i> Post Comment
        </button>
      </div>
      ` : `
      <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel2 p-4 text-center">
        <p class="text-sm text-gh-muted">
          <a href="login.php" class="text-gh-accent hover:underline">Login</a> to comment
        </p>
      </div>
      `}

      <!-- Comments List -->
      <div id="commentsList-${story.id}" class="space-y-3">
        ${renderComments(story.comments)}
      </div>
    </div>
  `;

  content.innerHTML = html;
}

function generateRatingStars(storyId, currentRating) {
  let html = '';
  for(let i = 1; i <= 5; i++) {
    const filled = i <= currentRating;
    html += `
      <button onclick="rateStory(${storyId}, ${i})" 
              class="text-lg ${filled ? 'text-yellow-500' : 'text-gh-muted'} hover:text-yellow-500 transition-colors">
        <i class="bi ${filled ? 'bi-star-fill' : 'bi-star'}"></i>
      </button>
    `;
  }
  return html;
}

function renderComments(comments) {
  if(!comments || comments.length === 0) {
    return '<p class="text-center text-sm text-gh-muted py-4">No comments yet. Be the first!</p>';
  }

  return comments.map(comment => {
    const avatar = comment.avatar 
      ? `<img src="uploads/avatars/${escapeHtml(comment.avatar)}" class="h-8 w-8 rounded-full object-cover">`
      : `<div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 text-xs font-bold text-white">
           ${escapeHtml(comment.username).charAt(0).toUpperCase()}
         </div>`;

    return `
      <div class="flex gap-3 rounded-lg border border-gh-border bg-gh-panel p-3">
        ${avatar}
        <div class="flex-1">
          <div class="mb-1 flex items-center gap-2">
            <span class="text-sm font-semibold text-white">${escapeHtml(comment.username || 'User')}</span>
            <span class="text-xs text-gh-muted">${formatDate(comment.created_at)}</span>
          </div>
          <p class="text-sm text-gh-fg">${escapeHtml(comment.comment)}</p>
        </div>
      </div>
    `;
  }).join('');
}

async function toggleLike(storyId) {
  try {
    const response = await fetch(`story-like.php?id=${storyId}`);
    const data = await response.json();

    if(data.success) {
      const btn = document.getElementById(`likeBtn-${storyId}`);
      const count = document.getElementById(`likeCount-${storyId}`);
      const icon = btn.querySelector('i');

      if(data.liked) {
        btn.classList.add('bg-red-500/20', 'text-red-500');
        btn.classList.remove('bg-gh-panel', 'text-gh-fg');
        icon.classList.remove('bi-heart');
        icon.classList.add('bi-heart-fill');
      } else {
        btn.classList.remove('bg-red-500/20', 'text-red-500');
        btn.classList.add('bg-gh-panel', 'text-gh-fg');
        icon.classList.add('bi-heart');
        icon.classList.remove('bi-heart-fill');
      }

      count.textContent = data.like_count;
    }
  } catch(error) {
    console.error('Error:', error);
  }
}

async function rateStory(storyId, rating) {
  try {
    const formData = new FormData();
    formData.append('story_id', storyId);
    formData.append('rating', rating);

    const response = await fetch('ajax/rate-story.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if(data.success) {
      // Update stars
      const container = document.getElementById(`ratingStars-${storyId}`);
      container.innerHTML = generateRatingStars(storyId, rating);

      // Show success message briefly
      const btn = container.parentElement;
      const originalBg = btn.className;
      btn.classList.add('bg-green-500/20');
      setTimeout(() => {
        btn.className = originalBg;
      }, 1000);
    }
  } catch(error) {
    console.error('Error:', error);
  }
}

async function postComment(storyId) {
  const input = document.getElementById(`commentInput-${storyId}`);
  const comment = input.value.trim();

  if(!comment) {
    alert('Please enter a comment');
    return;
  }

  try {
    const response = await fetch('story-comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ story_id: storyId, comment: comment })
    });

    const data = await response.json();

    if(data.success) {
      input.value = '';
      // Reload story to show new comment
      openStoryModal(storyId);
    } else {
      alert(data.message || 'Failed to post comment');
    }
  } catch(error) {
    console.error('Error:', error);
    alert('Failed to post comment');
  }
}

function messageAuthor(storyId, authorName) {
  // Redirect to compose message page
  window.location.href = `messages-compose.php?story=${storyId}&author=${encodeURIComponent(authorName)}`;
}

function getGenderLabel(gender) {
  const labels = {
    'M': 'Male',
    'F': 'Female',
    'NB': 'Non-binary',
    'O': 'Other'
  };
  return labels[gender] || gender;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diff = now - date;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));

  if(days === 0) return 'Today';
  if(days === 1) return 'Yesterday';
  if(days < 7) return `${days} days ago`;

  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}
