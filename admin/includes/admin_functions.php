<?

function adminOnly()
{
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Unauthorized access";
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}

?>