<?php
/**
 * Scrolling Announcement Helper
 * Displays scrolling marquee-style announcements above the header
 */

function getScrollingAnnouncement($db) {
    try {
        $query = "SELECT * FROM site_announcements 
                  WHERE is_active = TRUE
                  AND is_scrolling = TRUE
                  AND (start_date IS NULL OR start_date <= NOW())
                  AND (end_date IS NULL OR end_date >= NOW())
                  ORDER BY priority DESC, created_at DESC 
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching scrolling announcement: " . $e->getMessage());
        return null;
    }
}

function displayScrollingAnnouncement($db) {
    $announcement = getScrollingAnnouncement($db);
    
    if(!$announcement) {
        return '';
    }
    
    $bg_color = htmlspecialchars($announcement['background_color'] ?? '#4267f5');
    $text_color = htmlspecialchars($announcement['text_color'] ?? '#ffffff');
    $scroll_speed = intval($announcement['scroll_speed'] ?? 50);
    $message = htmlspecialchars($announcement['message']);
    $title = htmlspecialchars($announcement['title']);
    
    // Calculate animation duration based on scroll speed
    // Higher speed = faster animation (lower duration)
    $duration = max(10, 100 - $scroll_speed);
    
    $output = <<<HTML
<div class="scrolling-announcement-banner" style="
    position: sticky;
    top: 0;
    z-index: 9999;
    background: {$bg_color};
    color: {$text_color};
    overflow: hidden;
    white-space: nowrap;
    padding: 0.75rem 0;
    font-weight: 500;
    font-size: 0.95rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
">
    <div class="scrolling-text" style="
        display: inline-block;
        padding-left: 100%;
        animation: scroll-left {$duration}s linear infinite;
    ">
        <strong>{$title}</strong> &nbsp;&nbsp;•&nbsp;&nbsp; {$message} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    </div>
</div>

<style>
@keyframes scroll-left {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-100%);
    }
}

.scrolling-announcement-banner:hover .scrolling-text {
    animation-play-state: paused;
}

/* Ensure announcement stays above everything */
.scrolling-announcement-banner {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
}

/* Adjust header position when announcement is present */
body.has-scrolling-announcement header {
    margin-top: 50px;
}

body.has-scrolling-announcement {
    padding-top: 50px;
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .scrolling-announcement-banner {
        font-size: 0.85rem;
        padding: 0.6rem 0;
    }
    
    body.has-scrolling-announcement header {
        margin-top: 45px;
    }
    
    body.has-scrolling-announcement {
        padding-top: 45px;
    }
}
</style>

<script>
// Add class to body when scrolling announcement is present
document.addEventListener('DOMContentLoaded', function() {
    if(document.querySelector('.scrolling-announcement-banner')) {
        document.body.classList.add('has-scrolling-announcement');
    }
});
</script>
HTML;
    
    return $output;
}
?>
