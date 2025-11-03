<?php
// Database configuration
$host = $_ENV['PGHOST'];
$port = $_ENV['PGPORT'];
$dbname = $_ENV['PGDATABASE'];
$username = $_ENV['PGUSER'];
$password = $_ENV['PGPASSWORD'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to handle image uploads
function handleImageUpload($imageFile) {
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($imageFile["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

    // Check if file is an actual image
    $check = getimagesize($imageFile["tmp_name"]);
    if ($check === false) {
        throw new Exception("File is not an image.");
    }

    // Check file size (5MB limit)
    if ($imageFile["size"] > 5000000) {
        throw new Exception("File is too large. Maximum size is 5MB.");
    }

    // Allow certain file formats
    $allowedTypes = array("jpg", "jpeg", "png", "gif");
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed.");
    }

    // Upload file
    if (move_uploaded_file($imageFile["tmp_name"], $targetFilePath)) {
        return $fileName;
    } else {
        throw new Exception("Error uploading file.");
    }
}

// Get the action from URL parameter
$action = $_GET['action'] ?? 'register';
$message = '';
$error = '';

// Handle form submissions and actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($action == 'register') {
        // Handle registration
        try {
            // Validate required fields
            $requiredFields = ['name', 'dob', 'gender', 'email', 'mobile', 'address', 'state', 'education'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field '$field' is required.");
                }
            }

            // Validate email format
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            // Validate mobile number
            if (!preg_match("/^[0-9]{10}$/", $_POST['mobile'])) {
                throw new Exception("Mobile number must be 10 digits.");
            }

            // Handle image upload
            $imageFileName = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageFileName = handleImageUpload($_FILES['image']);
            } else {
                throw new Exception("Image upload is required.");
            }

            // Insert into database
            $sql = "INSERT INTO user_registrations (full_name, date_of_birth, gender, email, mobile, address, state, education, image_filename) 
                    VALUES (:name, :dob, :gender, :email, :mobile, :address, :state, :education, :image)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $_POST['name'],
                ':dob' => $_POST['dob'],
                ':gender' => $_POST['gender'],
                ':email' => $_POST['email'],
                ':mobile' => $_POST['mobile'],
                ':address' => $_POST['address'],
                ':state' => $_POST['state'],
                ':education' => $_POST['education'],
                ':image' => $imageFileName
            ]);

            $message = "Registration successful!";

        } catch (Exception $e) {
            // If there was an error and image was uploaded, delete it
            if (isset($imageFileName) && $imageFileName && file_exists("uploads/" . $imageFileName)) {
                unlink("uploads/" . $imageFileName);
            }
            $error = $e->getMessage();
        }
    } elseif ($action == 'update') {
        // Handle update
        $id = (int)$_POST['id'];
        try {
            // Validate required fields
            $requiredFields = ['name', 'dob', 'gender', 'email', 'mobile', 'address', 'state', 'education'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field '$field' is required.");
                }
            }

            // Validate email format
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            // Validate mobile number
            if (!preg_match("/^[0-9]{10}$/", $_POST['mobile'])) {
                throw new Exception("Mobile number must be 10 digits.");
            }

            // Get current registration data
            $sql = "SELECT image_filename FROM user_registrations WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $currentData = $stmt->fetch();

            $imageFileName = $currentData['image_filename']; // Keep existing image by default
            $oldImageToDelete = null;

            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Upload new image first
                $targetDir = "uploads/";
                $fileName = time() . '_' . basename($_FILES["image"]["name"]);
                $targetFilePath = $targetDir . $fileName;
                $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

                // Validate image
                $check = getimagesize($_FILES["image"]["tmp_name"]);
                if ($check === false) {
                    throw new Exception("File is not an image.");
                }

                if ($_FILES["image"]["size"] > 5000000) {
                    throw new Exception("File is too large. Maximum size is 5MB.");
                }

                $allowedTypes = array("jpg", "jpeg", "png", "gif");
                if (!in_array(strtolower($fileType), $allowedTypes)) {
                    throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed.");
                }

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                    $oldImageToDelete = $imageFileName;
                    $imageFileName = $fileName;
                } else {
                    throw new Exception("Error uploading file.");
                }
            }

            // Update database
            $sql = "UPDATE user_registrations SET 
                        full_name = :name, 
                        date_of_birth = :dob, 
                        gender = :gender, 
                        email = :email, 
                        mobile = :mobile, 
                        address = :address, 
                        state = :state, 
                        education = :education, 
                        image_filename = :image,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $_POST['name'],
                ':dob' => $_POST['dob'],
                ':gender' => $_POST['gender'],
                ':email' => $_POST['email'],
                ':mobile' => $_POST['mobile'],
                ':address' => $_POST['address'],
                ':state' => $_POST['state'],
                ':education' => $_POST['education'],
                ':image' => $imageFileName,
                ':id' => $id
            ]);

            // Delete old image only after successful update
            if ($oldImageToDelete && file_exists("uploads/" . $oldImageToDelete)) {
                unlink("uploads/" . $oldImageToDelete);
            }

            header("Location: ?action=view&success=updated");
            exit();

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action == 'delete' && isset($_GET['id'])) {
    // Handle delete
    $id = (int)$_GET['id'];
    try {
        // First, fetch the registration to get the image filename
        $sql = "SELECT image_filename FROM user_registrations WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $registration = $stmt->fetch();
        
        if ($registration) {
            // Delete the registration from database
            $sql = "DELETE FROM user_registrations WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            // Delete the associated image file if it exists
            if ($registration['image_filename'] && file_exists('uploads/' . $registration['image_filename'])) {
                unlink('uploads/' . $registration['image_filename']);
            }
            
            header("Location: ?action=view&success=deleted");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ?action=view&error=Failed to delete registration");
        exit();
    }
}

// Fetch data for view and edit actions
if ($action == 'view') {
    try {
        $sql = "SELECT * FROM user_registrations ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $registrations = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error fetching data: " . $e->getMessage();
    }
} elseif ($action == 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $sql = "SELECT * FROM user_registrations WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $registration = $stmt->fetch();
        
        if (!$registration) {
            header("Location: ?action=view&error=Registration not found");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ?action=view&error=Database error");
        exit();
    }
}

// Handle success/error messages from URL
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'updated') {
        $message = "Registration updated successfully!";
    } elseif ($_GET['success'] == 'deleted') {
        $message = "Registration deleted successfully!";
    }
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>User Registration System</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: url('https://static.vecteezy.com/system/resources/thumbnails/029/921/379/small_2x/megaphone-label-with-register-now-megaphone-banner-web-design-stock-illustration-vector.jpg') center top no-repeat;
            background-size: cover;
            min-height: 100vh;
        }
        .form-overlay {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background-color: rgba(255,255,255,0.6);
            border-radius: 8px;
            backdrop-filter: blur(4px);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        form table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            border: 1px solid #555;
            padding: 8px 10px;
            vertical-align: middle;
            background-color: rgba(255,255,255,0.8);
        }
        td label {
            display: inline-block;
            width: 120px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
            border: 1px solid #aaa;
            border-radius: 4px;
            background-color: rgba(255,255,255,1);
        }
        .gender-options label {
            font-weight: normal;
            margin-right: 10px;
        }
        .gender-options input[value="male"] + label {
            font-weight: bold;
        }
        .submit-row {
            text-align: center;
        }
        button, .btn {
            padding: 10px 20px;
            background-color: #d32f2f;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        button:hover, .btn:hover {
            background-color: #b71c1c;
        }
        .btn-view {
            background-color: #1976d2;
        }
        .btn-view:hover {
            background-color: #1565c0;
        }
        .btn-edit {
            background-color: #007bff;
            padding: 5px 10px;
            font-size: 12px;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
        .btn-delete {
            background-color: #dc3545;
            padding: 5px 10px;
            font-size: 12px;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .btn-cancel {
            background-color: #666;
        }
        .btn-cancel:hover {
            background-color: #555;
        }
        .message {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, .view-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .image-cell {
            text-align: center;
        }
        .profile-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        .current-image {
            text-align: center;
            margin: 10px 0;
        }
        .current-image img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        .actions {
            text-align: center;
        }
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
    </style>
    <script>
        function confirmDelete(id, name) {
            if (confirm('Are you sure you want to delete the registration for "' + name + '"?')) {
                window.location.href = '?action=delete&id=' + id;
            }
        }
    </script>
</head>
<body>

<?php if ($action == 'register'): ?>
    <!-- Registration Form -->
    <div class="form-overlay">
        <h2>User Registration</h2>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="?action=register" method="post" enctype="multipart/form-data">
            <table>
                <tr>
                    <td><label for="name">Full Name:</label></td>
                    <td><input type="text" id="name" name="name" required></td>
                </tr>
                <tr>
                    <td><label for="dob">Date of Birth:</label></td>
                    <td><input type="date" id="dob" name="dob" required></td>
                </tr>
                <tr>
                    <td><label>Gender:</label></td>
                    <td class="gender-options">
                        <input type="radio" id="male" name="gender" value="male" required>
                        <label for="male">Male</label>
                        <input type="radio" id="female" name="gender" value="female">
                        <label for="female">Female</label>
                        <input type="radio" id="other" name="gender" value="other">
                        <label for="other">Other</label>
                    </td>
                </tr>
                <tr>
                    <td><label for="email">Email ID:</label></td>
                    <td><input type="email" id="email" name="email" required></td>
                </tr>
                <tr>
                    <td><label for="mobile">Mobile No.:</label></td>
                    <td><input type="tel" id="mobile" name="mobile" pattern="[0-9]{10}" required></td>
                </tr>
                <tr>
                    <td><label for="address">Address:</label></td>
                    <td><textarea id="address" name="address" rows="3" required></textarea></td>
                </tr>
                <tr>
                    <td><label for="state">State:</label></td>
                    <td>
                        <select id="state" name="state" required>
                            <option value="">Select State</option>
                            <option value="gujarat">Gujarat</option>
                            <option value="maharashtra">Maharashtra</option>
                            <option value="delhi">Delhi</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label for="education">Education:</label></td>
                    <td>
                        <select id="education" name="education" required>
                            <option value="">Select Education</option>
                            <option value="highschool">High School</option>
                            <option value="bachelor">Bachelor's Degree</option>
                            <option value="master">Master's Degree</option>
                            <option value="phd">PhD</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label for="image">Upload Image:</label></td>
                    <td><input type="file" id="image" name="image" accept="image/*" required></td>
                </tr>
                <tr>
                    <td colspan="2" class="submit-row">
                        <button type="submit">Register</button>
                        <a href="?action=view" class="btn btn-view">View All Registrations</a>
                    </td>
                </tr>
            </table>
        </form>
    </div>

<?php elseif ($action == 'view'): ?>
    <!-- View All Registrations -->
    <div class="container">
        <a href="?action=register" class="btn">← Add New Registration</a>
        
        <h2>All User Registrations</h2>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (empty($registrations)): ?>
            <div class="no-data">
                <p>No registrations found. <a href="?action=register">Add the first registration</a></p>
            </div>
        <?php else: ?>
            <table class="view-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>Date of Birth</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Address</th>
                        <th>State</th>
                        <th>Education</th>
                        <th>Registered On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td class="image-cell">
                                <?php if ($row['image_filename'] && file_exists('uploads/' . $row['image_filename'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($row['image_filename']); ?>" 
                                         alt="Profile" class="profile-image">
                                <?php else: ?>
                                    <span style="color: #666;">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['gender'])); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['state'])); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($row['education'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td class="actions">
                                <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                                <button onclick="confirmDelete(<?php echo $row['id']; ?>, <?php echo json_encode($row['full_name']); ?>)" 
                                        class="btn btn-delete">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($action == 'edit' && isset($registration)): ?>
    <!-- Edit Registration Form -->
    <div class="form-overlay">
        <h2>Update Registration</h2>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="?action=update" method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $registration['id']; ?>">
            <table>
                <tr>
                    <td><label for="name">Full Name:</label></td>
                    <td><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($registration['full_name']); ?>" required></td>
                </tr>
                <tr>
                    <td><label for="dob">Date of Birth:</label></td>
                    <td><input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($registration['date_of_birth']); ?>" required></td>
                </tr>
                <tr>
                    <td><label>Gender:</label></td>
                    <td class="gender-options">
                        <input type="radio" id="male" name="gender" value="male" <?php echo ($registration['gender'] == 'male') ? 'checked' : ''; ?> required>
                        <label for="male">Male</label>
                        <input type="radio" id="female" name="gender" value="female" <?php echo ($registration['gender'] == 'female') ? 'checked' : ''; ?>>
                        <label for="female">Female</label>
                        <input type="radio" id="other" name="gender" value="other" <?php echo ($registration['gender'] == 'other') ? 'checked' : ''; ?>>
                        <label for="other">Other</label>
                    </td>
                </tr>
                <tr>
                    <td><label for="email">Email ID:</label></td>
                    <td><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($registration['email']); ?>" required></td>
                </tr>
                <tr>
                    <td><label for="mobile">Mobile No.:</label></td>
                    <td><input type="tel" id="mobile" name="mobile" pattern="[0-9]{10}" value="<?php echo htmlspecialchars($registration['mobile']); ?>" required></td>
                </tr>
                <tr>
                    <td><label for="address">Address:</label></td>
                    <td><textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($registration['address']); ?></textarea></td>
                </tr>
                <tr>
                    <td><label for="state">State:</label></td>
                    <td>
                        <select id="state" name="state" required>
                            <option value="">Select State</option>
                            <option value="gujarat" <?php echo ($registration['state'] == 'gujarat') ? 'selected' : ''; ?>>Gujarat</option>
                            <option value="maharashtra" <?php echo ($registration['state'] == 'maharashtra') ? 'selected' : ''; ?>>Maharashtra</option>
                            <option value="delhi" <?php echo ($registration['state'] == 'delhi') ? 'selected' : ''; ?>>Delhi</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label for="education">Education:</label></td>
                    <td>
                        <select id="education" name="education" required>
                            <option value="">Select Education</option>
                            <option value="highschool" <?php echo ($registration['education'] == 'highschool') ? 'selected' : ''; ?>>High School</option>
                            <option value="bachelor" <?php echo ($registration['education'] == 'bachelor') ? 'selected' : ''; ?>>Bachelor's Degree</option>
                            <option value="master" <?php echo ($registration['education'] == 'master') ? 'selected' : ''; ?>>Master's Degree</option>
                            <option value="phd" <?php echo ($registration['education'] == 'phd') ? 'selected' : ''; ?>>PhD</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label>Current Image:</label></td>
                    <td>
                        <?php if ($registration['image_filename'] && file_exists('uploads/' . $registration['image_filename'])): ?>
                            <div class="current-image">
                                <img src="uploads/<?php echo htmlspecialchars($registration['image_filename']); ?>" alt="Current Profile">
                                <br><small>Current image (leave empty to keep this image)</small>
                            </div>
                        <?php else: ?>
                            <p style="color: #666; font-style: italic;">No current image</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><label for="image">Upload New Image:</label></td>
                    <td><input type="file" id="image" name="image" accept="image/*"></td>
                </tr>
                <tr>
                    <td colspan="2" class="submit-row">
                        <button type="submit">Update Registration</button>
                        <a href="?action=view" class="btn btn-cancel">Cancel</a>
                    </td>
                </tr>
            </table>
        </form>
    </div>

<?php endif; ?>

</body>
</html>