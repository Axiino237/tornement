<?php
session_start();
require_once '../config/database.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$success = '';
$error = '';

// Handle Add/Edit Game
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_game') {
                $game_name = trim($_POST['game_name']);
                $image_url = '';

                // Handle File Upload
                if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] == 0) {
                    $target_dir = "../assets/images/games/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    $file_ext = strtolower(pathinfo($_FILES["game_image"]["name"], PATHINFO_EXTENSION));
                    $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
                    
                    if (in_array($file_ext, $allowed_extensions)) {
                        $new_filename = uniqid() . '.' . $file_ext;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES["game_image"]["tmp_name"], $target_file)) {
                            $image_url = "assets/images/games/" . $new_filename;
                        } else {
                            $error = "Failed to upload image file.";
                        }
                    } else {
                        $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
                    }
                } else {
                    $error = "Please select an image file.";
                }

                if (empty($error) && !empty($game_name) && !empty($image_url)) {
                    $stmt = $db->prepare("INSERT INTO games (game_name, image_url) VALUES (?, ?)");
                    $stmt->execute([$game_name, $image_url]);
                    $success = "Game added successfully!";
                    log_audit($db, $_SESSION['user_id'], 'ADD_GAME', "Added game: $game_name");
                }
            } elseif ($_POST['action'] === 'edit_game') {
                $game_id = (int)$_POST['game_id'];
                $game_name = trim($_POST['game_name']);
                
                // Get current data
                $stmt = $db->prepare("SELECT image_url FROM games WHERE game_id = ?");
                $stmt->execute([$game_id]);
                $current_game = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_game) {
                    $error = "Game not found.";
                } else {
                    $image_url = $current_game['image_url'];

                    // Handle New Image Upload if provided
                    if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] == 0) {
                        $target_dir = "../assets/images/games/";
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        
                        $file_ext = strtolower(pathinfo($_FILES["game_image"]["name"], PATHINFO_EXTENSION));
                        $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
                        
                        if (in_array($file_ext, $allowed_extensions)) {
                            $new_filename = uniqid() . '.' . $file_ext;
                            $target_file = $target_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES["game_image"]["tmp_name"], $target_file)) {
                                // Delete old image if it exists and is local
                                if ($image_url && strpos($image_url, 'http') === false) {
                                    $old_file_path = "../" . $image_url;
                                    if (file_exists($old_file_path)) {
                                        unlink($old_file_path);
                                    }
                                }
                                $image_url = "assets/images/games/" . $new_filename;
                            } else {
                                $error = "Failed to upload new image file.";
                            }
                        } else {
                            $error = "Invalid file type for image.";
                        }
                    }

                    if (empty($error) && !empty($game_name)) {
                        $stmt = $db->prepare("UPDATE games SET game_name = ?, image_url = ? WHERE game_id = ?");
                        if ($stmt->execute([$game_name, $image_url, $game_id])) {
                            $success = "Game updated successfully!";
                            log_audit($db, $_SESSION['user_id'], 'EDIT_GAME', "Updated game ID $game_id: $game_name");
                        } else {
                            $error = "Failed to update database record.";
                        }
                    }
                }
            } elseif ($_POST['action'] === 'delete_game') {
                $game_id = $_POST['game_id'];
                
                // Get image path to delete file
                $stmt = $db->prepare("SELECT image_url FROM games WHERE game_id = ?");
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($game && !empty($game['image_url']) && strpos($game['image_url'], 'http') === false) {
                    $file_path = "../" . $game['image_url'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                $stmt = $db->prepare("DELETE FROM games WHERE game_id = ?");
                $stmt->execute([$game_id]);
                $success = "Game deleted successfully!";
                log_audit($db, $_SESSION['user_id'], 'DELETE_GAME', "Deleted game ID: $game_id");
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$stmt = $db->query("SELECT * FROM games ORDER BY game_name ASC");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once '../includes/header.php'; ?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4"><i class="fas fa-gamepad text-warning me-2"></i>Manage Games</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card bg-dark text-light border-secondary mb-5">
            <div class="card-header bg-secondary bg-opacity-25 border-bottom border-secondary">
                <h5 class="mb-0"><i class="fas fa-plus-circle text-info me-2"></i>Add New Game</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_game">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3 mb-md-0">
                            <label class="form-label">Game Name</label>
                            <input type="text" name="game_name" class="form-control" placeholder="e.g. Free Fire, BGMI" required>
                        </div>
                        <div class="col-md-5 mb-3 mb-md-0">
                            <label class="form-label">Game Poster/Logo</label>
                            <input type="file" name="game_image" class="form-control" accept="image/*" required onchange="previewImage(this, 'add')">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload me-1"></i>Add Game</button>
                        </div>
                    </div>
                    <div id="image_preview_container_add" class="mt-3" style="display: none;">
                        <p class="small text-muted mb-1">Preview:</p>
                        <img id="image_preview_add" src="" alt="Preview" class="rounded border border-secondary" style="max-height: 120px; display: block; background: #222;">
                    </div>
                </form>
            </div>
        </div>

        <div class="card bg-dark text-light border-secondary">
            <div class="card-header border-bottom border-secondary">
                <h5 class="mb-0"><i class="fas fa-list text-warning me-2"></i>Existing Games</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr class="border-bottom border-secondary">
                                <th class="px-4">Logo</th>
                                <th>Game Name</th>
                                <th class="text-end px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $g): ?>
                                <tr class="align-middle">
                                    <td class="px-4 py-3">
                                        <?php 
                                        $img_src = (strpos($g['image_url'], 'http') === 0) ? $g['image_url'] : "../" . $g['image_url'];
                                        ?>
                                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Game" class="rounded border border-secondary shadow-sm" style="height: 50px; width: 50px; object-fit: cover;">
                                    </td>
                                    <td class="fw-bold fs-5"><?php echo htmlspecialchars($g['game_name']); ?></td>
                                    <td class="text-end px-4">
                                        <button type="button" class="btn btn-sm btn-outline-info me-1 edit-game-btn" 
                                                data-id="<?php echo $g['game_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($g['game_name'], ENT_QUOTES); ?>" 
                                                data-img="<?php echo htmlspecialchars($img_src, ENT_QUOTES); ?>">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this game?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="game_id" value="<?php echo $g['game_id']; ?>">
                                            <button type="submit" name="action" value="delete_game" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt me-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($games)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">No games added yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Game Modal -->
<div class="modal fade" id="editGameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-edit text-info me-2"></i>Edit Game</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="edit_game">
                    <input type="hidden" name="game_id" id="edit_game_id">
                    
                    <div class="mb-3">
                        <label class="form-label text-light">Game Name</label>
                        <input type="text" name="game_name" id="edit_game_name" class="form-control bg-dark text-light border-secondary" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-light">Update Poster/Logo (Optional)</label>
                        <input type="file" name="game_image" class="form-control bg-dark text-light border-secondary mb-2" accept="image/*" onchange="previewImage(this, 'edit')">
                        <p class="small text-muted">Leave empty to keep current image.</p>
                    </div>
                    
                    <div id="image_preview_container_edit" class="mt-3">
                        <p class="small text-muted mb-1">Current/New Preview:</p>
                        <img id="image_preview_edit" src="" alt="Preview" class="rounded border border-secondary" style="max-height: 150px; display: block; background: #222; margin: 0 auto;">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewImage(input, type) {
    const preview = document.getElementById('image_preview_' + type);
    const container = document.getElementById('image_preview_container_' + type);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else if (type === 'add') {
        container.style.display = 'none';
    }
}

// Single modal instance
let editModal = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal
    editModal = new bootstrap.Modal(document.getElementById('editGameModal'));

    // Handle Edit Button Clicks
    document.querySelectorAll('.edit-game-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const imgSrc = this.dataset.img;

            document.getElementById('edit_game_id').value = id;
            document.getElementById('edit_game_name').value = name;
            document.getElementById('image_preview_edit').src = imgSrc;
            document.getElementById('image_preview_container_edit').style.display = 'block';
            
            editModal.show();
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
