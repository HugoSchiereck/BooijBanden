<?php
// invoer.php

session_start();
require_once 'db.php';

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$success_ids = [];
$error_msg = "";

// Formulier verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aantal = isset($_POST['aantal']) ? (int)$_POST['aantal'] : 1;
    
    // Verplichte velden
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $season = trim($_POST['season']);
    $width = (int)$_POST['width'];
    $ratio = (int)$_POST['ratio'];
    $rim = (int)$_POST['rim'];
    
    // Optionele velden
    $construction = !empty($_POST['construction']) ? trim($_POST['construction']) : 'R';
    $load_index = trim($_POST['load_index']);
    $speed_index = trim($_POST['speed_index']);
    $dot_code = trim($_POST['dot_code']);
    $e_mark = trim($_POST['e_mark']);
    $direction = trim($_POST['direction']);
    
    $treadwear = !empty($_POST['treadwear']) ? (int)$_POST['treadwear'] : null;
    $traction = trim($_POST['traction']);
    $temperature = trim($_POST['temperature']);
    
    $tread_depth = !empty($_POST['tread_depth']) ? (float)str_replace(',', '.', $_POST['tread_depth']) : null;
    $notes = trim($_POST['notes']);
    $price = !empty($_POST['price']) ? (float)str_replace(',', '.', $_POST['price']) : 0.00;
    
    // Bepaal of de band nieuw is (> 7.0mm of geen diepte ingevuld)
    $is_new = ($tread_depth === null || $tread_depth >= 7.0) ? 1 : 0;

    if (empty($brand) || empty($model) || empty($width) || empty($ratio) || empty($rim)) {
        $error_msg = "Vul alle verplichte basisgegevens in.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $set_id = null;
            // Maak een set aan als er 2 of 4 banden tegelijk worden ingevoerd
            if ($aantal === 2 || $aantal === 4) {
                $stmtSet = $pdo->query("INSERT INTO tire_sets () VALUES ()");
                $set_id = $pdo->lastInsertId();
            }
            
            // Bereid de insert query voor
            $stmtInsert = $pdo->prepare("
                INSERT INTO tires (
                    qr_id, set_id, brand, model, season, width, ratio, construction, rim, 
                    load_index, speed_index, dot_code, e_mark, direction, 
                    treadwear, traction, temperature, tread_depth, is_new, notes, price
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            // Loop voor het aantal banden
            for ($i = 0; $i < $aantal; $i++) {
                $qr_id = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
                
                $stmtInsert->execute([
                    $qr_id, $set_id, $brand, $model, $season, $width, $ratio, $construction, $rim,
                    $load_index, $speed_index, $dot_code, $e_mark, $direction,
                    $treadwear, $traction, $temperature, $tread_depth, $is_new, $notes, $price
                ]);
                
                $success_ids[] = $qr_id;
            }
            
            $pdo->commit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Database fout bij opslaan: " . $e->getMessage();
        }
    }
}

$pageTitle = "Nieuwe Band(en) Invoeren";
include 'header.php';
?>

<main class="max-w-4xl mx-auto py-4 sm:py-8 px-2 sm:px-6 lg:px-8">
    
    <div class="mb-4 sm:mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Banden Invoeren</h1>
        <p class="text-sm sm:text-base text-slate-500 mt-1">Registreer nieuwe banden. Adviesprijs wordt automatisch berekend.</p>
    </div>

    <?php if ($error_msg): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded shadow-sm">
            <p class="text-red-700"><?php echo htmlspecialchars($error_msg); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_ids)): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 sm:p-5 mb-6 rounded shadow-sm">
            <h3 class="text-green-800 font-bold text-lg mb-2">Succesvol opgeslagen!</h3>
            <p class="text-green-700 text-sm mb-4">De banden zijn toegevoegd met een stukprijs van €<?php echo number_format($price, 2, ',', '.'); ?>.</p>
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <?php foreach ($success_ids as $id): ?>
                    <a href="detail.php?id=<?php echo $id; ?>" class="bg-white border border-green-300 text-green-700 hover:bg-green-100 font-bold py-1.5 px-3 rounded shadow-sm transition-colors flex items-center gap-1.5 text-xs sm:text-sm">
                        🖨️ Etiket (<?php echo $id; ?>)
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 pt-3 border-t border-green-200">
                <a href="invoer.php" class="text-green-700 hover:text-green-900 font-bold underline text-sm">+ Nog een invoeren</a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden text-sm">
            
            <div class="p-4 sm:p-6 bg-slate-50 border-b border-slate-200">
                <label class="block text-slate-700 font-bold mb-2">Hoeveel banden wil je invoeren?</label>
                <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="aantal" value="1" class="w-4 h-4 text-blue-600 focus:ring-blue-500" checked>
                        <span class="ml-2 font-bold text-slate-700">1 Losse Band</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="aantal" value="2" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 font-bold text-slate-700">Set van 2</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="aantal" value="4" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 font-bold text-slate-700">Set van 4</span>
                    </label>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <!-- 1. Basisinformatie & Afmetingen -->
                <h2 class="text-lg sm:text-xl font-bold text-slate-800 mb-3 pb-2 border-b border-slate-100">1. Basisinformatie (Verplicht)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Merk *</label>
                        <input required type="text" name="brand" placeholder="bijv. Michelin" class="w-full border border-slate-300 rounded-md py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Model / Type *</label>
                        <input required type="text" name="model" placeholder="bijv. Pilot Sport 4" class="w-full border border-slate-300 rounded-md py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Seizoen *</label>
                        <select required name="season" class="w-full border border-slate-300 rounded-md py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-sm">
                            <option value="Zomer">Zomerband</option>
                            <option value="Winter">Winterband</option>
                            <option value="All Season">All Season</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-bold text-blue-700 mb-1 flex justify-between">
                            <span>Stukprijs (Excl. Montage) €</span>
                            <span class="text-xs font-normal text-blue-500" id="priceHint">Auto-berekend</span>
                        </label>
                        <input type="number" step="0.01" name="price" id="calcPrice" placeholder="bijv. 50.00" class="w-full border border-blue-300 bg-blue-50 rounded-md py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold">
                        <input type="hidden" id="userModifiedPrice" value="0">
                    </div>
                    
                    <div class="grid grid-cols-4 gap-2 md:col-span-2">
                        <div class="col-span-1">
                            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Breedte *</label>
                            <input required type="number" name="width" id="calcWidth" placeholder="225" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Hoogte *</label>
                            <input required type="number" name="ratio" placeholder="55" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Constr.</label>
                            <input type="text" name="construction" value="R" class="w-full border border-slate-300 rounded-md py-1.5 px-3 bg-slate-50 text-center text-slate-400 text-sm" readonly>
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Inch *</label>
                            <input required type="number" name="rim" id="calcRim" placeholder="18" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- 2. Fysieke Staat -->
                <h2 class="text-lg sm:text-xl font-bold text-slate-800 mb-3 pb-2 border-b border-slate-100">2. Fysieke Staat (Belangrijk voor prijs)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Profieldiepte (mm)</label>
                        <input type="number" step="0.1" name="tread_depth" id="calcTread" placeholder="bijv. 6.5 (Leeg = Nieuw)" class="w-full border border-slate-300 rounded-md py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Opmerkingen / Staat</label>
                        <input type="text" name="notes" placeholder="bijv. prop aanwezig..." class="w-full border border-slate-300 rounded-md py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>

                <!-- 3. Prestatie (Optioneel) -->
                <h2 class="text-lg sm:text-xl font-bold text-slate-800 mb-3 pb-2 border-b border-slate-100">3. Prestatie (Optioneel)</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-2">
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Load Ind.</label>
                        <input type="text" name="load_index" placeholder="98" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Speed Ind.</label>
                        <input type="text" name="speed_index" placeholder="V" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">DOT-Code</label>
                        <input type="text" name="dot_code" placeholder="4123" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Richting</label>
                        <select name="direction" class="w-full border border-slate-300 rounded-md py-1.5 px-2 text-sm bg-white">
                            <option value="">Geen</option>
                            <option value="Inside/Outside">In/Out</option>
                            <option value="Pijl">Pijl</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors">
                    Banden Opslaan
                </button>
            </div>
        </form>
    <?php endif; ?>

    <!-- JAVASCRIPT VOOR DE AUTO-PRIJS BEREKENING -->
    <script>
        const widthInput = document.getElementById('calcWidth');
        const rimInput = document.getElementById('calcRim');
        const treadInput = document.getElementById('calcTread');
        const priceInput = document.getElementById('calcPrice');
        const userModified = document.getElementById('userModifiedPrice');
        const priceHint = document.getElementById('priceHint');

        // Als de gebruiker zelf een prijs typt, stoppen we met automatisch berekenen
        priceInput.addEventListener('input', function() {
            userModified.value = "1";
            priceHint.innerText = "Handmatig";
            priceHint.classList.replace('text-blue-500', 'text-amber-500');
        });

        function autoCalculatePrice() {
            if (userModified.value === "1") return;

            let w = parseFloat(widthInput.value) || 0;
            let r = parseFloat(rimInput.value) || 0;
            let t = parseFloat(treadInput.value) || 7.0;

            if (w > 100 && r > 10) {
                // De Booij-Formule V2 (Praktijk prijzen)
                let basePrice = 45;
                
                if (r <= 15) {
                    basePrice = 45 + (w > 185 ? 5 : 0);
                } else if (r === 16) {
                    basePrice = 45 + (w > 205 ? 5 : 0);
                } else if (r === 17) {
                    basePrice = 50 + (w > 225 ? 5 : 0);
                } else if (r === 18) {
                    basePrice = 50 + (w > 235 ? 10 : 0); // Bijv 205 komt op 50
                } else if (r === 19) {
                    basePrice = 85 + (w > 245 ? 15 : 0);
                } else if (r === 20) {
                    basePrice = 140 + (w > 255 ? 10 : 0); // Bijv 265 komt op 140+10 = 150
                } else if (r >= 21) {
                    basePrice = 160 + (w > 265 ? 10 : 0); // Bijv 275 komt op 160+10 = 170
                }
                
                let finalPrice = basePrice;
                
                // Slijtage korting: pas toepassen als profiel onder de 5.5mm komt
                if (t > 0 && t < 5.5) {
                    let qualityPercentage = (t / 5.5); 
                    if(qualityPercentage < 0.4) qualityPercentage = 0.4; // Bodemprijs (Max 60% korting)
                    finalPrice = finalPrice * qualityPercentage;
                }

                // Afronden op veelvouden van €5,- (bijv 47.50 wordt 50)
                finalPrice = Math.round(finalPrice / 5) * 5; 

                if (finalPrice > 0) {
                    priceInput.value = finalPrice.toFixed(2);
                }
            }
        }

        widthInput.addEventListener('input', autoCalculatePrice);
        rimInput.addEventListener('input', autoCalculatePrice);
        treadInput.addEventListener('input', autoCalculatePrice);
    </script>
</main>

<?php include 'footer.php'; ?>