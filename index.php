<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Admin - İdari İşler Talep Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Request Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Hakkımızda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">İletişim</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Firma Kayıt</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#loginModal">Giriş Yap</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-primary">İdari İşler Talep Yönetim Sistemi</h1>
                    <p class="lead">Firmanızın idari işler süreçlerini dijitalleştirin. Talepleri kolayca yönetin, takip edin ve raporlayın.</p>
                    <div class="d-flex gap-3">
                        <a href="register.php" class="btn btn-primary btn-lg">Firma Kaydı</a>
                        <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">Giriş Yap</button>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/hero-image.jpg" alt="Request Admin" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="fw-bold">Sistem Özellikleri</h2>
                    <p class="text-muted">Modern ve kullanıcı dostu arayüz ile idari işlerinizi kolaylaştırın</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-primary text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5>Çoklu Kullanıcı Yönetimi</h5>
                            <p class="text-muted">Admin, İdari İşler ve Çalışan rolleri ile kapsamlı yetkilendirme</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-success text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h5>Lokasyon Bazlı Yönetim</h5>
                            <p class="text-muted">Farklı lokasyonlar için ayrı yetkilendirme ve talep yönetimi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-info text-white rounded-circle mx-auto mb-3">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h5>Talep Takip Sistemi</h5>
                            <p class="text-muted">Taleplerinizi kategorize edin, takip edin ve raporlayın</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center">
                    <h2 class="fw-bold mb-4">İletişim</h2>
                    <p class="text-muted">Sorularınız için bizimle iletişime geçin</p>
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-4">
                            <div class="contact-info">
                                <i class="fas fa-envelope fa-2x text-primary mb-3"></i>
                                <h5>E-posta</h5>
                                <p>info@requestadmin.com</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="contact-info">
                                <i class="fas fa-phone fa-2x text-primary mb-3"></i>
                                <h5>Telefon</h5>
                                <p>+90 (212) 555 0123</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Giriş Yap</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="auth/login.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Parola</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Beni hatırla
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Giriş Yap</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
</body>
</html>
</code>
