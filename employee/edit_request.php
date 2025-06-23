<?php
$requestId = $_GET['id'] ?? '';
if (!empty($requestId)) {
    header('Location: request_detail.php?id=' . $requestId . '&edit=1');
} else {
    header('Location: requests.php');
}
exit;
?>
