<?php
// voorraad.php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tire'])) {
    $qr_id = trim($_POST['qr_id']);
    $price = (float)$_POST['price'];
    $location_id = trim($_POST['location_id']);
    
    $location_id = $location_id === '' ? null : $location_id;
    
    try {
        $stmt = $pdo->prepare("UPDATE tires SET price = ?, location_id = ? WHERE qr_id = ?");
        $stmt->execute([$price, $location_id, $qr_id]);
        
        $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$qr_id, $_SESSION['user_id'], "Gewijzigd via Voorraadbeheer (Prijs/Locatie)"]);
        
        $msg = "Band " . htmlspecialchars($qr_id) . " is succesvol bijgewerkt!";
    } catch (Exception $e) {
        $error = "Fout bij bijwerken: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tire'])) {
    $qr_id = trim($_POST['qr_id']);
    
    try {
        $stmtCheck = $pdo->prepare("SELECT status FROM tires WHERE qr_id = ?");
        $stmtCheck->execute([$qr_id]);
        $currentStatus = $stmtCheck->fetchColumn();
        
        if ($currentStatus === 'voorraad') {
            $stmt = $pdo->prepare("UPDATE tires SET status = 'uitgeboekt', location_id = NULL WHERE qr_id = ?");
            $stmt->execute([$qr_id]);
            
            $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$qr_id, $_SESSION['user_id'], "Handmatig uitgeboekt via Voorraadbeheer"]);
            
            $msg = "Band " . htmlspecialchars($qr_id) . " is veilig uitgeboekt.";
        } else {
            $error = "Deze band kan niet worden uitgeboekt omdat de status '$currentStatus' is.";
        }
    } catch (Exception $e) {
        $error = "Fout bij uitboeken: " . $e->getMessage();
    }
}

$stat_voorraad = $pdo->query("SELECT COUNT(*) FROM tires WHERE status = 'voorraad'")->fetchColumn();
$stat_gereserveerd = $pdo->query("SELECT COUNT(*) FROM tires WHERE status IN ('gereserveerd', 'gemonteerd')")->fetchColumn();
$stat_waarde = $pdo->query("SELECT SUM(price) FROM tires WHERE status = 'voorraad'")->fetchColumn();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'voorraad';

$sql = "SELECT * FROM tires WHERE 1=1";
$params = [];

if ($status_filter !== 'alle') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (qr_id LIKE ? OR brand LIKE ? OR model LIKE ? OR CONCAT(width, '/', ratio, 'R', rim) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tires = $stmt->fetchAll();

$pageTitle = "Voorraadbeheer Dashboard";
include 'header.php';
?>

<main class="w-full max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 w-full min-w-0">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Voorraadbeheer</h1>
            <p class="text-slate-500 text-sm mt-1">Beheer je magazijn, wijzig locaties en bekijk voorraadstatistieken.</p>
        </div>
        <div class="flex gap-3 w-full md:w-auto">
            <a href="#" class="w-full md:w-auto bg-slate-800 hover:bg-slate-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-sm transition-colors text-sm flex justify-center items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Nieuwe Band
            </a>
            <a href="#" class="w-full md:w-auto bg-blue-100 hover:bg-blue-200 text-blue-800 font-bold py-2.5 px-4 rounded-lg shadow-sm transition-colors text-sm flex justify-center items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                CSV Import
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 mb-6 rounded-r-lg shadow-sm">
            <p class="text-emerald-800 font-bold text-sm flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <?php echo $msg; ?>
            </p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg shadow-sm">
            <p class="text-red-800 font-bold text-sm"><?php echo $error; ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 w-full min-w-0">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-center gap-4 min-w-0">
            <div class="bg-blue-100 p-4 rounded-xl text-blue-600 flex-shrink-0">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 truncate">Vrij op voorraad</p>
                <p class="text-3xl font-black text-slate-800"><?php echo number_format($stat_voorraad, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-center gap-4 min-w-0">
            <div class="bg-amber-100 p-4 rounded-xl text-amber-600 flex-shrink-0">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 truncate">Gereserveerd / Kassa</p>
                <p class="text-3xl font-black text-slate-800"><?php echo number_format($stat_gereserveerd, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-center gap-4 min-w-0">
            <div class="bg-emerald-100 p-4 rounded-xl text-emerald-600 flex-shrink-0">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 truncate">Waarde Voorraad</p>
                <p class="text-3xl font-black text-slate-800">&euro;<?php echo number_format($stat_waarde, 2, ',', '.'); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-t-2xl shadow-sm border-t border-l border-r border-slate-200 p-5 flex flex-col md:flex-row gap-4 justify-between items-center w-full min-w-0">
        <form method="GET" class="w-full flex flex-col md:flex-row gap-4 flex-grow max-w-3xl">
            <div class="relative flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Zoek op QR, merk, of maat (bijv. 205/55R16)..." class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm font-medium text-slate-800">
            </div>
            <select name="status" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="voorraad" <?php if($status_filter == 'voorraad') echo 'selected'; ?>>Voorraad</option>
                <option value="gereserveerd" <?php if($status_filter == 'gereserveerd') echo 'selected'; ?>>Gereserveerd</option>
                <option value="verkocht" <?php if($status_filter == 'verkocht') echo 'selected'; ?>>Verkocht / Historie</option>
                <option value="alle" <?php if($status_filter == 'alle') echo 'selected'; ?>>Alles tonen</option>
            </select>
        </form>
    </div>

    <div class="bg-white shadow-sm border border-slate-200 rounded-b-2xl overflow-hidden w-full min-w-0">
        <div class="w-full max-w-full overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider whitespace-nowrap">QR Code</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider whitespace-nowrap">Maat & Merk</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider whitespace-nowrap">Staat</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-wider whitespace-nowrap">Locatie</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-wider whitespace-nowrap">Prijs</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-wider whitespace-nowrap">Acties</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    <?php if (empty($tires)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500 font-medium">
                                Geen banden gevonden die voldoen aan deze filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($tires as $t): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-bold text-slate-700 bg-slate-100 px-2 py-1 rounded border border-slate-200">
                                    <?php echo htmlspecialchars($t['qr_id']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <div class="text-sm font-black text-slate-800"><?php echo $t['width'].'/'.$t['ratio'].' R'.$t['rim']; ?></div>
                                <div class="text-xs text-slate-500 font-medium"><?php echo htmlspecialchars($t['brand'] . ' ' . $t['model']); ?></div>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <?php if($t['is_new']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        Nieuw
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        <?php echo $t['tread_depth']; ?> mm
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <?php if(!empty($t['location_id'])): ?>
                                    <span class="text-sm font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded border border-blue-100">
                                        <?php echo htmlspecialchars($t['location_id']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400 italic">Geen</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-black text-slate-900">
                                &force;&euro;<?php echo number_format($t['price'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                                <button type="button" 
                                    onclick="openEditModal('<?php echo htmlspecialchars($t['qr_id']); ?>', '<?php echo $t['price']; ?>', '<?php echo htmlspecialchars($t['location_id'] ?? ''); ?>')" 
                                    class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 p-1.5 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                                
                                <?php if ($t['status'] === 'voorraad'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Weet je zeker dat je band <?php echo htmlspecialchars($t['qr_id']); ?> wilt uitboeken?');">
                                    <input type="hidden" name="qr_id" value="<?php echo htmlspecialchars($t['qr_id']); ?>">
                                    <button type="submit" name="delete_tire" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 p-1.5 rounded transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 text-xs text-slate-500 font-medium text-center">
                * Weergave beperkt tot de laatste 100 resultaten. Gebruik de zoekbalk voor specifieke banden.
            </div>
        </div>
    </div>

</main>

<div id="editModal" class="hidden fixed inset-0 bg-slate-900 bg-opacity-50 flex justify-center items-center z-50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-black text-slate-800">Band Bewerken</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="qr_id" id="modal_qr_id">
            <div class="mb-5">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">QR Code / Referentie</label>
                <div id="modal_display_qr" class="font-mono text-sm font-bold text-slate-800 bg-slate-100 p-3 rounded border border-slate-200"></div>
            </div>
            <div class="mb-5">
                <label for="modal_price" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Verkoopprijs (&euro;)</label>
                <input type="number" step="0.01" name="price" id="modal_price" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 font-bold text-slate-800 focus:outline-none">
            </div>
            <div class="mb-6">
                <label for="modal_location" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Magazijnlocatie (Optioneel)</label>
                <input type="text" name="location_id" id="modal_location" placeholder="Bijv. 5B10" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 font-bold uppercase text-slate-800 focus:outline-none">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg transition-colors">Annuleren</button>
                <button type="submit" name="edit_tire" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors shadow-sm">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(qr, price, location) {
    document.getElementById('modal_qr_id').value = qr;
    document.getElementById('modal_display_qr').innerText = qr;
    document.getElementById('modal_price').value = price;
    document.getElementById('modal_location').value = location;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include 'footer.php'; ?>