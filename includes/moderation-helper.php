<?php
/**
 * Content Moderation Integration Helper
 * Include this file in content submission endpoints
 */

require_once __DIR__ . '/../classes/ContentModerator.php';

function moderateListing($db, $listingId, $userId, $title, $description) {
    try {
        $moderator = new ContentModerator($db);
        $content = "Title: {$title}\n\nDescription: {$description}";
        $result = $moderator->moderateText($content, 'listing', $listingId, $userId);

        if ($result['risk_level'] === 'high') {
            $stmt = $db->prepare("UPDATE listings SET status = 'pending', moderation_status = 'flagged' WHERE id = :id");
            $stmt->execute(['id' => $listingId]);
            return ['success' => false, 'message' => 'Your listing has been flagged for review.'];
        }

        return ['success' => true, 'message' => 'Content approved'];
    } catch (Exception $e) {
        error_log("Moderation error: " . $e->getMessage());
        return ['success' => true, 'message' => 'Posted successfully'];
    }
}

function moderateMarketplaceListing($db, $listingId, $userId, $title, $description, $tags = '') {
    try {
        $moderator = new ContentModerator($db);
        $content = "Title: {$title}\n\nDescription: {$description}\n\nTags: {$tags}";
        $result = $moderator->moderateText($content, 'marketplace_listing', $listingId, $userId);

        if ($result['risk_level'] === 'high') {
            $stmt = $db->prepare("UPDATE creator_listings SET status = 'pending', moderation_status = 'flagged' WHERE id = :id");
            $stmt->execute(['id' => $listingId]);
            return ['success' => false, 'message' => 'Your listing requires review before publishing.'];
        }

        return ['success' => true, 'message' => 'Content approved'];
    } catch (Exception $e) {
        error_log("Moderation error: " . $e->getMessage());
        return ['success' => true, 'message' => 'Posted successfully'];
    }
}

function moderateForumThread($db, $threadId, $userId, $title, $content) {
    try {
        $moderator = new ContentModerator($db);
        $fullContent = "Title: {$title}\n\nContent: {$content}";
        $result = $moderator->moderateText($fullContent, 'forum_thread', $threadId, $userId);

        if ($result['risk_level'] === 'high') {
            $stmt = $db->prepare("UPDATE forum_threads SET moderation_status = 'flagged' WHERE id = :id");
            $stmt->execute(['id' => $threadId]);
            return ['success' => false, 'message' => 'Your post has been flagged for review.'];
        }

        return ['success' => true, 'message' => 'Content approved'];
    } catch (Exception $e) {
        error_log("Moderation error: " . $e->getMessage());
        return ['success' => true, 'message' => 'Posted successfully'];
    }
}

function moderateForumPost($db, $postId, $userId, $content) {
    try {
        $moderator = new ContentModerator($db);
        $result = $moderator->moderateText($content, 'forum_post', $postId, $userId);

        if ($result['risk_level'] === 'high') {
            $stmt = $db->prepare("UPDATE forum_posts SET moderation_status = 'flagged', is_deleted = 1 WHERE id = :id");
            $stmt->execute(['id' => $postId]);
            return ['success' => false, 'message' => 'Your post has been flagged for review.'];
        }

        return ['success' => true, 'message' => 'Content approved'];
    } catch (Exception $e) {
        error_log("Moderation error: " . $e->getMessage());
        return ['success' => true, 'message' => 'Posted successfully'];
    }
}

function moderateProfile($db, $userId, $bio, $interests = '') {
    try {
        $moderator = new ContentModerator($db);
        $content = "Bio: {$bio}\n\nInterests: {$interests}";
        $result = $moderator->moderateText($content, 'profile', $userId, $userId);

        if ($result['risk_level'] === 'high') {
            return ['success' => true, 'message' => 'Profile updated. Some content may require review.'];
        }

        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch (Exception $e) {
        error_log("Moderation error: " . $e->getMessage());
        return ['success' => true, 'message' => 'Profile updated'];
    }
}

function moderateMessage($db, $messageId, $userId, $content) {
    try {
        $moderator = new ContentModerator($db);
        $result = $moderator->moderateText($content, 'message', $messageId, $userId);

        if ($result['risk_level'] === 'high' && $result['confidence'] > 0.8) {
            return ['success' => false, 'message' => 'Message flagged for policy violations.'];
        }

        return ['success' => true, 'message' => 'Message sent'];
    } catch (Exception $e) {
        error_log("Moderation error: " . $e->getMessage());
        return ['success' => true, 'message' => 'Message sent'];
    }
}
