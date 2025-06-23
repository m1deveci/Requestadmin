<?php
function renderSidebar($currentPage = '', $userRole = '') {
    $userRole = $userRole ?: $_SESSION['user_role'];
    $userName = $_SESSION['user_name'] ?? 'User';
    $locationName = $_SESSION['location_name'] ?? 'Merkez';
    
    $navigation = [
        'admin' => [
            'title' => 'Admin Panel',
            'items' => [
                ['href' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
                ['href' => 'companies.php', 'icon' => 'fas fa-building', 'text' => 'Firmalar'],
                ['href' => 'locations.php', 'icon' => 'fas fa-map-marker-alt', 'text' => 'Lokasyonlar'],
                ['href' => 'users.php', 'icon' => 'fas fa-users', 'text' => 'Kullanıcılar'],
                ['href' => 'requests.php', 'icon' => 'fas fa-tasks', 'text' => 'Talepler'],
                ['href' => 'categories.php', 'icon' => 'fas fa-tags', 'text' => 'Kategoriler'],
                ['href' => 'settings.php', 'icon' => 'fas fa-cog', 'text' => 'Ayarlar'],
                ['href' => 'logs.php', 'icon' => 'fas fa-file-alt', 'text' => 'Loglar']
            ]
        ],
        'hr' => [
            'title' => 'İdari İşler',
            'items' => [
                ['href' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
                ['href' => 'requests.php', 'icon' => 'fas fa-tasks', 'text' => 'Talepler'],
                ['href' => 'employees.php', 'icon' => 'fas fa-users', 'text' => 'Çalışanlar'],
                ['href' => 'reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Raporlar'],
                ['href' => 'profile.php', 'icon' => 'fas fa-user', 'text' => 'Profil']
            ]
        ],
        'employee' => [
            'title' => 'Çalışan Paneli',
            'items' => [
                ['href' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
                ['href' => 'requests.php', 'icon' => 'fas fa-tasks', 'text' => 'Taleplerim'],
                ['href' => 'new_request.php', 'icon' => 'fas fa-plus', 'text' => 'Yeni Talep'],
                ['href' => 'profile.php', 'icon' => 'fas fa-user', 'text' => 'Profil']
            ]
        ]
    ];
    
    $nav = $navigation[$userRole] ?? $navigation['employee'];
    
    echo '<nav class="col-md-3 col-lg-2 d-md-block sidebar" id="sidebar">';
    echo '<div class="position-sticky pt-3">';
    echo '<div class="text-center mb-4">';
    echo '<h5 class="text-white">' . htmlspecialchars($nav['title']) . '</h5>';
    echo '<small class="text-light">' . htmlspecialchars($userName) . '</small>';
    echo '<small class="text-light d-block">' . htmlspecialchars($locationName) . '</small>';
    echo '</div>';
    
    echo '<ul class="nav flex-column">';
    foreach ($nav['items'] as $item) {
        $isActive = (basename($_SERVER['PHP_SELF']) === $item['href']) ? 'active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link ' . $isActive . '" href="' . $item['href'] . '">';
        echo '<i class="' . $item['icon'] . '"></i> ' . $item['text'];
        echo '</a>';
        echo '</li>';
    }
    echo '<li class="nav-item mt-3">';
    echo '<a class="nav-link text-danger" href="../auth/logout.php">';
    echo '<i class="fas fa-sign-out-alt"></i> Çıkış';
    echo '</a>';
    echo '</li>';
    echo '</ul>';
    echo '</div>';
    echo '</nav>';
}
?>
