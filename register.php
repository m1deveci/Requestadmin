<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name']);
    $phone = trim($_POST['phone']);
    $authorizedPerson = trim($_POST['authorized_person']);
    $taxNumber = trim($_POST['tax_number']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($companyName)) $errors[] = 'Firma adı gereklidir.';
    if (empty($phone)) $errors[] = 'Telefon gereklidir.';
    if (empty($authorizedPerson)) $errors[] = 'Firma yetkilisi gereklidir.';
    if (empty($taxNumber)) $errors[] = 'Vergi numarası gereklidir.';
    if (empty($address)) $errors[] = 'Adres gereklidir.';
    if (empty($email)) $errors[] = 'E-posta gereklidir.';
    if (empty($password)) $errors[] = 'Parola gereklidir.';
    if ($password !== $confirmPassword) $errors[] = 'Parolalar eşleşmiyor.';
    if (strlen($password) < 6) $errors[] = 'Parola en az 6 karakter olmalıdır.';
    
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id FROM companies WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            $logoPath = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['logo'], 'uploads/logos/');
                if ($uploadResult['success']) {
                    $logoPath = $uploadResult['filename'];
                } else {
                    $errors[] = $uploadResult['message'];
                }
            }
            
            if (empty($errors)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO companies (company_name, phone, authorized_person, tax_number, address, logo, email, password) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($query);
                if ($stmt->execute([$companyName, $phone, $authorizedPerson, $taxNumber, $address, $logoPath, $email, $hashedPassword])) {
                    $_SESSION['success'] = 'Firma kaydınız başarıyla oluşturuldu. Onay beklemektedir.';
                    header('Location: index.php');
                    exit;
                } else {
                    $errors[] = 'Kayıt sırasında bir hata oluştu.';
                }
            }
        }
    }
    
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Kayıt - Request Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Request Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Ana Sayfa</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Firma Kayıt Formu</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['errors'])): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($_SESSION['errors'] as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php unset($_SESSION['errors']); ?>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Firma Adı *</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['company_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Telefon *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="authorized_person" class="form-label">Firma Yetkilisi *</label>
                                        <input type="text" class="form-control" id="authorized_person" name="authorized_person" 
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['authorized_person'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tax_number" class="form-label">Vergi Numarası *</label>
                                        <input type="text" class="form-control" id="tax_number" name="tax_number" 
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['tax_number'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Adres *</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($_SESSION['form_data']['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="logo" class="form-label">Firma Logosu</label>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                <div class="form-text">JPG, PNG veya GIF formatında, maksimum 2MB</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">E-posta *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Parola *</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="form-text">En az 6 karakter</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Parola Tekrar *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    Kullanım şartlarını ve gizlilik politikasını kabul ediyorum *
                                </label>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">İptal</a>
                                <button type="submit" class="btn btn-primary">Kayıt Ol</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php unset($_SESSION['form_data']); ?>
