<?php
class AdSenseHelper {
    private $db;
    private $adManager;
    
    public function __construct($db) {
        $this->db = $db;
        $this->adManager = new AdvertisingManager($db);
    }
    
    /**
     * Render AdSense ad unit
     */
    public function renderAdSense($location, $position) {
        $unit = $this->adManager->getAdSenseUnit($location, $position);
        
        if(!$unit) {
            return '';
        }
        
        $testMode = $unit['test_mode'] ? 'data-adtest="on"' : '';
        $format = $unit['responsive'] ? 'auto' : $unit['ad_format'];
        
        return "
        <div class='adsense-container' style='text-align: center; margin: 20px 0;'>
            <ins class='adsbygoogle'
                 style='display:block'
                 data-ad-client='{$unit['client_id']}'
                 data-ad-slot='{$unit['slot_id']}'
                 data-ad-format='{$format}'
                 {$testMode}
                 data-full-width-responsive='true'></ins>
            <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
            </script>
        </div>";
    }
    
    /**
     * Render banner ad
     */
    public function renderBanner($location, $position) {
        $banner = $this->adManager->getBannerAd($location, $position);
        
        if(!$banner) {
            return '';
        }
        
        return "
        <div class='banner-ad' style='text-align: center; margin: 20px 0;'>
            <a href='/ad-click.php?type=banner&id={$banner['id']}' target='_blank' rel='noopener'>
                <img src='{$banner['banner_image']}' 
                     alt='{$banner['alt_text']}' 
                     style='max-width: 100%; height: auto; border-radius: 8px;'>
            </a>
        </div>";
    }
    
    /**
     * Render native ad as listing card
     */
    public function renderNativeAd($ad) {
        return "
        <div class='listing-card native-ad' data-ad-type='native' data-ad-id='{$ad['id']}'>
            <div class='sponsored-badge'>Sponsored</div>
            " . ($ad['image_url'] ? "<img src='{$ad['image_url']}' class='listing-image' alt='Ad'>" : "") . "
            <div class='listing-content'>
                <h3>
                    <a href='/ad-click.php?type=native&id={$ad['id']}' target='_blank' rel='noopener'>
                        {$ad['title']}
                    </a>
                </h3>
                <p>" . substr($ad['description'], 0, 150) . "...</p>
                <div class='listing-meta'>
                    <span style='color: var(--primary-blue);'>Sponsored Content</span>
                </div>
            </div>
        </div>";
    }
    
    /**
     * Mix native ads with regular listings
     */
    public function mixNativeAds($listings, $category_id = null, $city_id = null) {
        $nativeAds = $this->adManager->getNativeAds($category_id, $city_id, 3);
        
        if(empty($nativeAds)) {
            return $listings;
        }
        
        $mixed = [];
        $adIndex = 0;
        $adFrequency = ceil(count($listings) / count($nativeAds));
        
        foreach($listings as $index => $listing) {
            $mixed[] = $listing;
            
            // Insert native ad every N listings
            if(($index + 1) % $adFrequency == 0 && $adIndex < count($nativeAds)) {
                $mixed[] = ['is_native_ad' => true, 'ad_data' => $nativeAds[$adIndex]];
                $adIndex++;
            }
        }
        
        return $mixed;
    }
    
    /**
     * Load AdSense script
     */
    public static function loadAdSenseScript() {
        return "<script async src='https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX' crossorigin='anonymous'></script>";
    }
}
?>