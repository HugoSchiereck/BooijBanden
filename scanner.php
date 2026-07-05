<?php
// scanner.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$pageTitle = "QR Scanner";
include 'header.php';
?>

<main class="max-w-lg mx-auto py-8 px-4 sm:px-6">
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-slate-800">Banden Scanner</h1>
        <p class="text-slate-500 mt-1">Scan het etiket om de band te picken of af te melden.</p>
    </div>

    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden p-2">
        <!-- De HTML5 QRCode scanner container -->
        <div id="reader" width="100%"></div>
    </div>
</main>

<!-- Inladen van de betrouwbare HTML5-QRCode bibliotheek -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    function onScanSuccess(decodedText, decodedResult) {
        // Stop de scanner zodra we een code hebben
        html5QrcodeScanner.clear();
        
        // Omdat onze QR code in print.php een URL is (bijv. forward.nl/booij/detail.php?id=TEST001)
        // en we soms alleen ID's scannen, doen we een slimme check:
        let redirectUrl = decodedText;
        if (!decodedText.includes('detail.php')) {
            redirectUrl = "detail.php?id=" + encodeURIComponent(decodedText);
        }
        
        // Stuur de browser naar de band
        window.location.href = redirectUrl;
    }

    function onScanFailure(error) {
        // Negeren, hij scant continu tot hij beet heeft
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader",
        { fps: 10, qrbox: {width: 250, height: 250}, aspectRatio: 1.0 },
        /* verbose= */ false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>

<?php include 'footer.php'; ?>