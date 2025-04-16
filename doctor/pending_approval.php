<?php
session_start();
if (!isset($_SESSION['pending_doctor'])) {
    header("Location: auth.php");
    exit();
}
include '../header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body text-center p-5">
                    <i class="fas fa-clock fa-5x text-warning mb-4"></i>
                    <h2>Your Doctor Account is Pending Approval</h2>
                    <p class="lead">Our admin team is reviewing your application. You'll receive an email once your
                        account is approved.</p>
                    <a href="logout.php" class="btn btn-primary mt-3">Return to Homepage</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>