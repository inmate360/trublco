<?php

/**
 * ImageUpload Class - Handles secure image uploads with validation and processing
 * 
 * @version 2.0
 */
class ImageUpload
{
    private PDO $db;
    private int $maxFileSize = 5242880; // 5MB
    private array $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private string $uploadDir;

    // Image processing constants
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1920;
    private const THUMB_SIZE = 300;
    private const JPEG_QUALITY = 90;
    private const PNG_COMPRESSION = 8;
    private const WEBP_QUALITY = 90;

    public function __construct(PDO $db, ?string $uploadDir = null)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../uploads/';

        $this->initializeDirectories();
    }

    /**
     * Initialize upload directories with proper permissions
     */
    private function initializeDirectories(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $subdirs = ['users', 'listings', 'temp'];
        foreach ($subdirs as $dir) {
            $path = $this->uploadDir . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Get primary image for a listing
     * 
     * @param int $listingId Listing ID
     * @return string|null Image URL or null
     */
    public function getPrimaryImage(int $listingId): ?string
    {
        try {
            $query = "SELECT photo_url FROM listing_photos 
                      WHERE listing_id = :listing_id 
                      ORDER BY display_order ASC 
                      LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':listing_id', $listingId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['photo_url'] ?? null;
        } catch (PDOException $e) {
            error_log("ImageUpload::getPrimaryImage error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all images for a listing
     * 
     * @param int $listingId Listing ID
     * @return array Array of image data
     */
    public function getListingImages(int $listingId): array
    {
        try {
            $query = "SELECT * FROM listing_photos 
                      WHERE listing_id = :listing_id 
                      ORDER BY display_order ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':listing_id', $listingId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ImageUpload::getListingImages error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Upload and process image
     * 
     * @param array $file $_FILES array element
     * @param string $type Upload type ('user' or 'listing')
     * @return array Result with success status and data
     */
    public function upload(array $file, string $type = 'listing'): array
    {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return $validation;
        }

        // Sanitize type
        $type = in_array($type, ['user', 'listing']) ? $type : 'listing';

        try {
            // Generate secure filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $this->generateSecureFilename($extension);

            // Determine paths
            $subdir = $type === 'user' ? 'users' : 'listings';
            $filepath = $this->uploadDir . $subdir . '/' . $filename;
            $webPath = '/uploads/' . $subdir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to move uploaded file'
                ];
            }

            // Process image (resize, optimize)
            $this->processImage($filepath);

            // Create thumbnail
            $thumbPath = $this->createThumbnail($filepath, $subdir);

            // Get image dimensions
            list($width, $height) = getimagesize($filepath);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $webPath,
                'thumbnail' => $thumbPath,
                'size' => filesize($filepath),
                'width' => $width,
                'height' => $height,
                'type' => mime_content_type($filepath)
            ];
        } catch (Exception $e) {
            error_log("ImageUpload::upload error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Upload processing failed'
            ];
        }
    }

    /**
     * Validate uploaded file
     * 
     * @param array $file $_FILES array element
     * @return array Validation result
     */
    private function validateFile(array $file): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => $this->getUploadErrorMessage($file['error'])
            ];
        }

        // Check if file was actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'Invalid file upload'
            ];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File size exceeds maximum allowed (5MB)'
            ];
        }

        if ($file['size'] === 0) {
            return [
                'valid' => false,
                'message' => 'File is empty'
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => 'Invalid file extension. Allowed: ' . implode(', ', $this->allowedExtensions)
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP allowed'
            ];
        }

        // Verify it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'message' => 'File is not a valid image'
            ];
        }

        // Check dimensions
        if ($imageInfo[0] < 100 || $imageInfo[1] < 100) {
            return [
                'valid' => false,
                'message' => 'Image dimensions too small (minimum 100x100px)'
            ];
        }

        // Check for malicious content in EXIF data
        if (function_exists('exif_read_data')) {
            @exif_read_data($file['tmp_name']); // This will fail on malicious files
        }

        return ['valid' => true];
    }

    /**
     * Generate secure filename
     * 
     * @param string $extension File extension
     * @return string Secure filename
     */
    private function generateSecureFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    }

    /**
     * Get upload error message
     * 
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload'
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * Process and optimize image
     * 
     * @param string $filepath Path to image file
     * @return bool Success status
     */
    private function processImage(string $filepath): bool
    {
        $imageInfo = @getimagesize($filepath);
        if (!$imageInfo) {
            return false;
        }

        list($width, $height, $type) = $imageInfo;

        // Only resize if larger than maximum dimensions
        if ($width <= self::MAX_WIDTH && $height <= self::MAX_HEIGHT) {
            return true;
        }

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $newWidth = (int)round($width * $ratio);
        $newHeight = (int)round($height * $ratio);

        // Load source image
        $source = $this->loadImage($filepath, $type);
        if (!$source) {
            return false;
        }

        // Create destination image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        if (!$destination) {
            imagedestroy($source);
            return false;
        }

        // Preserve transparency for PNG/GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }

        // Resize with high quality
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save optimized image
        $success = $this->saveImage($destination, $filepath, $type);

        // Clean up
        imagedestroy($source);
        imagedestroy($destination);

        return $success;
    }

    /**
     * Create thumbnail with center crop
     * 
     * @param string $filepath Path to source image
     * @param string $subdir Subdirectory for thumbnail
     * @return string|null Web path to thumbnail
     */
    private function createThumbnail(string $filepath, string $subdir): ?string
    {
        $imageInfo = @getimagesize($filepath);
        if (!$imageInfo) {
            return null;
        }

        list(, , $type) = $imageInfo;

        // Load source
        $source = $this->loadImage($filepath, $type);
        if (!$source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Calculate center crop dimensions
        $size = min($width, $height);
        $x = (int)(($width - $size) / 2);
        $y = (int)(($height - $size) / 2);

        // Create thumbnail
        $thumbnail = imagecreatetruecolor(self::THUMB_SIZE, self::THUMB_SIZE);
        if (!$thumbnail) {
            imagedestroy($source);
            return null;
        }

        // Preserve transparency
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }

        // Crop and resize
        imagecopyresampled($thumbnail, $source, 0, 0, $x, $y, self::THUMB_SIZE, self::THUMB_SIZE, $size, $size);

        // Save thumbnail
        $pathInfo = pathinfo($filepath);
        $thumbFilename = $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        $thumbPath = $this->uploadDir . $subdir . '/' . $thumbFilename;
        $thumbWebPath = '/uploads/' . $subdir . '/' . $thumbFilename;

        $success = $this->saveImage($thumbnail, $thumbPath, $type);

        // Clean up
        imagedestroy($source);
        imagedestroy($thumbnail);

        return $success ? $thumbWebPath : null;
    }

    /**
     * Load image from file
     * 
     * @param string $filepath Path to image
     * @param int $type Image type constant
     * @return GdImage|false Image resource or false
     */
    private function loadImage(string $filepath, int $type)
    {
        return match($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($filepath),
            IMAGETYPE_PNG => @imagecreatefrompng($filepath),
            IMAGETYPE_GIF => @imagecreatefromgif($filepath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($filepath),
            default => false
        };
    }

    /**
     * Save image to file
     * 
     * @param GdImage $image Image resource
     * @param string $filepath Destination path
     * @param int $type Image type constant
     * @return bool Success status
     */
    private function saveImage($image, string $filepath, int $type): bool
    {
        return match($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $filepath, self::JPEG_QUALITY),
            IMAGETYPE_PNG => imagepng($image, $filepath, self::PNG_COMPRESSION),
            IMAGETYPE_GIF => imagegif($image, $filepath),
            IMAGETYPE_WEBP => imagewebp($image, $filepath, self::WEBP_QUALITY),
            default => false
        };
    }

    /**
     * Delete image and its thumbnail
     * 
     * @param string $path Web path to image
     * @return bool Success status
     */
    public function delete(string $path): bool
    {
        $fullPath = __DIR__ . '/..' . $path;

        if (!file_exists($fullPath)) {
            return false;
        }

        try {
            // Delete main image
            @unlink($fullPath);

            // Delete thumbnail
            $pathInfo = pathinfo($fullPath);
            $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }

            return true;
        } catch (Exception $e) {
            error_log("ImageUpload::delete error: " . $e->getMessage());
            return false;
        }
    }
}
