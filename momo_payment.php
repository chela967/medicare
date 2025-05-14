<?php
session_start();
require 'config.php';

// 1. SECURITY AND SESSION CHECK
if (!isset($_SESSION['user']['id'])) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

// 2. MTN MOMO HELPER FUNCTIONS
function getMomoToken()
{
    $api_user = "dea774d6-e45c-4c16-ac4c-3fd057dd50d6";
    $api_key = "fd34cc0ab076429daff400e78b2da75e";
    $subscription_key = "d3e96597ba824f40b940845ff0f87067";

    $headers = [
        "Authorization: Basic " . base64_encode("$api_user:$api_key"),
        "Ocp-Apim-Subscription-Key: $subscription_key",
        "Cache-Control: no-cache"
    ];

    $ch = curl_init("https://sandbox.momodeveloper.mtn.com/collection/token/");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // Disable for testing only
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("CURL Error: " . curl_error($ch));
        return null;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function checkPaymentStatus($transactionId)
{
    $token = getMomoToken();
    if (!$token) {
        throw new Exception("Failed to get access token");
    }

    $headers = [
        "X-Target-Environment: sandbox",
        "Ocp-Apim-Subscription-Key: d3e96597ba824f40b940845ff0f87067",
        "Authorization: Bearer $token"
    ];

    $url = "https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/$transactionId";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_SSL_VERIFYPEER => false // Disable for testing only
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        throw new Exception("API request failed with code $httpCode");
    }

    return json_decode($response, true);
}

// 3. HANDLE PAYMENT STATUS CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    try {
        $transactionId = $_POST['transaction_id'];
        $status = checkPaymentStatus($transactionId);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $status
        ]);
        exit();

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Check Payment Status</h2>
                        <form id="statusForm">
                            <div class="mb-3">
                                <label class="form-label">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control"
                                    value="0a1dff6c-a427-493a-bc6f-fdfc87b46f9e" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                Check Status
                            </button>
                        </form>
                        <div id="result" class="mt-4"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('statusForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF'] ?>', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-success">
                            <pre>${JSON.stringify(data.data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-danger">
                            ${data.message}
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-danger">
                        Network error occurred
                    </div>
                `;
            }
        });
    </script>
</body>

</html>