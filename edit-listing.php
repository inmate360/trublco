<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$listing_id) {
    header('Location: my-listings.php');
    exit();
}

// Get listing and verify ownership
$query = "SELECT * FROM listings WHERE id = :id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$listing = $stmt->fetch();

if(!$listing) {
    header('Location: my-listings.php');
    exit();
}

// Get categories
$query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC";
$categories = $db->query($query)->fetchAll();

// Get cities
$query = "SELECT c.*, s.abbreviation as state_abbr 
          FROM cities c 
          LEFT JOIN states s ON c.state_id = s.id 
          WHERE c.is_active = 1 
          ORDER BY c.name ASC";
$cities = $db->query($query)->fetchAll();

$error = '';
$success = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $city_id = (int)$_POST['city_id'];
    
    if(empty($title) || empty($description)) {
        $error = 'Title and description are required';
    } else {
        $query = "UPDATE listings 
                  SET title = :title, 
                      description = :description, 
                      category_id = :category_id, 
                      city_id = :city_id,
                      updated_at = NOW()
                  WHERE id = :id AND user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':city_id', $city_id);
        $stmt->bindParam(':id', $listing_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if($stmt->execute()) {
            $success = 'Listing updated successfully!';
            // Refresh listing data
            $listing = array_merge($listing, $_POST);
        } else {
            $error = 'Failed to update listing';
        }
    }
}

include 'views/header.php';
?>

<div class="mx-auto max-w-4xl px-4 py-8">
  
  <!-- Header -->
  <div class="mb-6 flex items-center justify-between">
    <div>
      <h1 class="mb-2 flex items-center gap-3 text-3xl font-extrabold tracking-tight">
        <i class="bi bi-pencil-square text-gh-accent"></i>
        Edit Listing
      </h1>
      <p class="text-sm text-gh-muted">Update your listing details</p>
    </div>
    <a href="listing.php?id=<?php echo $listing_id; ?>" 
       class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
      <i class="bi bi-arrow-left"></i> Back to Listing
    </a>
  </div>

  <!-- Success Message -->
  <?php if(!empty($success)): ?>
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-lg text-gh-success"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-success">Success!</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($success); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Error Message -->
  <?php if(!empty($error)): ?>
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-lg text-gh-danger"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-danger">Error:</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Edit Form -->
  <form method="POST" class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
    <div class="space-y-6 p-6">
      
      <!-- Title -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Listing Title <span class="text-gh-danger">*</span>
        </label>
        <input type="text" 
               name="title" 
               value="<?php echo htmlspecialchars($listing['title']); ?>"
               required 
               maxlength="200"
               placeholder="Enter a catchy title"
               class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
      </div>

      <!-- Category -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Category <span class="text-gh-danger">*</span>
        </label>
        <select name="category_id" 
                required
                class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50">
          <option value="">Select a category</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $listing['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Location -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Location <span class="text-gh-danger">*</span>
        </label>
        <select name="city_id" 
                required
                class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50">
          <option value="">Select a city</option>
          <?php foreach($cities as $city): ?>
            <option value="<?php echo $city['id']; ?>" <?php echo $listing['city_id'] == $city['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($city['name']); ?>, <?php echo $city['state_abbr']; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Description -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Description <span class="text-gh-danger">*</span>
        </label>
        <textarea name="description" 
                  rows="10" 
                  required 
                  maxlength="5000"
                  placeholder="Describe your listing in detail..."
                  class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50"><?php echo htmlspecialchars($listing['description']); ?></textarea>
        <p class="mt-1.5 text-xs text-gh-muted">Maximum 5000 characters</p>
      </div>

    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between border-t border-gh-border bg-gh-panel2 px-6 py-4">
      <a href="listing.php?id=<?php echo $listing_id; ?>" 
         class="text-sm font-semibold text-gh-muted hover:text-gh-fg hover:underline">
        Cancel
      </a>
      <button type="submit" 
              class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
        <i class="bi bi-check-circle-fill"></i>
        Save Changes
      </button>
    </div>
  </form>

  <!-- Danger Zone -->
  <div class="mt-8 rounded-xl border border-gh-danger bg-gh-danger/5 p-6">
    <h3 class="mb-2 flex items-center gap-2 text-lg font-bold text-gh-danger">
      <i class="bi bi-exclamation-triangle-fill"></i>
      Danger Zone
    </h3>
    <p class="mb-4 text-sm text-gh-muted">Once you delete a listing, there is no going back. Please be certain.</p>
    <a href="delete-listing.php?id=<?php echo $listing_id; ?>" 
       onclick="return confirm('Are you sure you want to delete this listing? This action cannot be undone!')"
       class="inline-flex items-center gap-2 rounded-lg border border-gh-danger bg-gh-danger/10 px-4 py-2 text-sm font-semibold text-gh-danger transition-colors hover:bg-gh-danger/20">
      <i class="bi bi-trash"></i>
      Delete Listing
    </a>
  </div>

</div>

<?php include 'views/footer.php'; ?>
