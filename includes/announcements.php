<?php
function getActiveAnnouncements($db, $page_type = 'all') {
    $query = "SELECT * FROM site_announcements 
              WHERE is_active = TRUE
              AND (start_date IS NULL OR start_date <= NOW())
              AND (end_date IS NULL OR end_date >= NOW())";
    
    if($page_type == 'homepage') {
        $query .= " AND show_on_homepage = TRUE";
    } elseif($page_type == 'all') {
        $query .= " AND (show_on_all_pages = TRUE OR show_on_homepage = TRUE)";
    }
    
    $query .= " ORDER BY priority DESC, created_at DESC LIMIT 3";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function displayAnnouncements($announcements) {
    if(empty($announcements)) {
        return '';
    }
    
    $output = '<div class="announcements-container">';
    
    foreach($announcements as $announcement) {
        $type_colors = [
            'info' => 'var(--info-cyan)',
            'success' => 'var(--success-green)',
            'warning' => 'var(--warning-orange)',
            'danger' => 'var(--danger-red)'
        ];
        
        $color = $type_colors[$announcement['type']] ?? 'var(--info-cyan)';
        
        $output .= '<div class="announcement-item" style="background: rgba(66, 103, 245, 0.1); border-left: 4px solid ' . $color . '; padding: 1rem 1.5rem; margin-bottom: 1rem; border-radius: 8px;">';
        $output .= '<div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">';
        $output .= '<div style="flex: 1;">';
        $output .= '<strong style="color: ' . $color . '; display: block; margin-bottom: 0.5rem;">' . htmlspecialchars($announcement['title']) . '</strong>';
        $output .= '<p style="color: var(--text-gray); margin: 0; line-height: 1.6;">' . nl2br(htmlspecialchars($announcement['message'])) . '</p>';
        $output .= '</div>';
        $output .= '<button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: var(--text-gray); cursor: pointer; font-size: 1.2rem; padding: 0; line-height: 1;">×</button>';
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
?>