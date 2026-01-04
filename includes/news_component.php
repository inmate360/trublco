<?php
/**
 * News & Announcements Component
 * Displays scrolling marquee and static news items
 */

function getActiveNews($db) {
    try {
        $query = "SELECT * FROM site_news 
                  WHERE is_active = TRUE
                  AND (start_date IS NULL OR start_date <= NOW())
                  AND (end_date IS NULL OR end_date >= NOW())
                  ORDER BY priority DESC, created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching news: " . $e->getMessage());
        return [];
    }
}

function displayNewsSystem($db) {
    $news_items = getActiveNews($db);
    
    if(empty($news_items)) {
        return '';
    }
    
    $scrolling_news = null;
    $static_news = [];
    
    foreach($news_items as $item) {
        if($item['is_scrolling'] && !$scrolling_news) {
            $scrolling_news = $item;
        } else {
            $static_news[] = $item;
        }
    }
    
    $output = '';
    
    // 1. Render Scrolling Marquee (if any)
    if($scrolling_news) {
        $bg = htmlspecialchars($scrolling_news['bg_color']);
        $text = htmlspecialchars($scrolling_news['text_color']);
        $speed = intval($scrolling_news['scroll_speed']);
        $duration = max(10, 100 - $speed);
        $title = htmlspecialchars($scrolling_news['title']);
        $content = htmlspecialchars($scrolling_news['content']);
        
        $output .= <<<HTML
<div class="news-marquee-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: {$bg};
    color: {$text};
    overflow: hidden;
    white-space: nowrap;
    padding: 0.6rem 0;
    font-weight: 600;
    font-size: 0.9rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
">
    <div class="news-marquee-text" style="
        display: inline-block;
        padding-left: 100%;
        animation: news-scroll {$duration}s linear infinite;
    ">
        <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; margin-right: 10px;">{$title}</span>
        {$content} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    </div>
</div>

<style>
@keyframes news-scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-100%); }
}
.news-marquee-banner:hover .news-marquee-text {
    animation-play-state: paused;
}
body.has-news-marquee {
    padding-top: 40px !important;
}
body.has-news-marquee header {
    margin-top: 40px !important;
}
@media (max-width: 768px) {
    body.has-news-marquee { padding-top: 36px !important; }
    body.has-news-marquee header { margin-top: 36px !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('has-news-marquee');
});
</script>
HTML;
    }
    
    // 2. Render Static News (if any)
    if(!empty($static_news)) {
        $output .= '<div class="mx-auto max-w-7xl px-4 mt-4 space-y-3">';
        foreach($static_news as $item) {
            $type_class = '';
            $icon = 'bi-info-circle-fill';
            switch($item['type']) {
                case 'success': $type_class = 'border-green-500/30 bg-green-500/10 text-green-500'; $icon = 'bi-check-circle-fill'; break;
                case 'warning': $type_class = 'border-yellow-500/30 bg-yellow-500/10 text-yellow-500'; $icon = 'bi-exclamation-triangle-fill'; break;
                case 'danger': $type_class = 'border-red-500/30 bg-red-500/10 text-red-500'; $icon = 'bi-slash-circle-fill'; break;
                default: $type_class = 'border-blue-500/30 bg-blue-500/10 text-blue-500';
            }
            
            $output .= sprintf(
                '<div class="relative flex items-start gap-3 rounded-lg border p-4 %s">
                    <i class="bi %s text-xl"></i>
                    <div class="flex-1">
                        <h4 class="font-bold">%s</h4>
                        <p class="text-sm opacity-90">%s</p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-current opacity-50 hover:opacity-100 transition-opacity">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>',
                $type_class,
                $icon,
                htmlspecialchars($item['title']),
                nl2br(htmlspecialchars($item['content']))
            );
        }
        $output .= '</div>';
    }
    
    return $output;
}
?>
