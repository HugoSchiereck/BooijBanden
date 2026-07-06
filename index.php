<?php
// index.php (De publieke verkooppagina / landingspagina)

session_start();
require_once 'db.php';

$tire = null;
$set_count = 1;

// --- 1. Als er een ID in de URL zit (QR-code scan) ---
if (isset($_GET['id'])) {
    $qr_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM tires WHERE qr_id = ? AND status != 'uitgeboekt'");
    $stmt->execute([$qr_id]);
    $tire = $stmt->fetch();
    
    if ($tire && $tire['set_id']) {
        $stmtSet = $pdo->prepare("SELECT COUNT(*) FROM tires WHERE set_id = ?");
        $stmtSet->execute([$tire['set_id']]);
        $set_count = $stmtSet->fetchColumn();
    }
}

// --- 2. Voorraadzoeker Logica ---
$widths = [];
$ratios = [];
$rims = [];
$searchResults = null;

if (!$tire) {
    // Haal de unieke maten op die momenteel op voorraad zijn voor de dropdowns
    try {
        $widths = $pdo->query("SELECT DISTINCT width FROM tires WHERE status = 'voorraad' ORDER BY width ASC")->fetchAll(PDO::FETCH_COLUMN);
        $ratios = $pdo->query("SELECT DISTINCT ratio FROM tires WHERE status = 'voorraad' ORDER BY ratio ASC")->fetchAll(PDO::FETCH_COLUMN);
        $rims   = $pdo->query("SELECT DISTINCT rim FROM tires WHERE status = 'voorraad' ORDER BY rim ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    // Als de klant het zoekformulier heeft gebruikt
    if (isset($_GET['search_tires'])) {
        $w = $_GET['w'] ?? '';
        $r = $_GET['r'] ?? '';
        $i = $_GET['i'] ?? '';
        
        // We groeperen identieke banden, zodat de klant "4 stuks" ziet in plaats van 4 losse regels
        $sql = "SELECT brand, model, season, width, ratio, rim, is_new, tread_depth, price, COUNT(*) as available_count 
                FROM tires 
                WHERE status = 'voorraad'";
        $params = [];
        
        if (!empty($w)) { $sql .= " AND width = ?"; $params[] = $w; }
        if (!empty($r)) { $sql .= " AND ratio = ?"; $params[] = $r; }
        if (!empty($i)) { $sql .= " AND rim = ?"; $params[] = $i; }
        
        $sql .= " GROUP BY width, ratio, rim, brand, model, is_new, price, tread_depth ORDER BY rim ASC, width ASC, price ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll();
    }
}

$pageTitle = $tire ? "Specificaties: " . $tire['brand'] . " " . $tire['model'] : "Welkom bij Booij Banden";
include 'header.php';
?>

<main class="max-w-5xl mx-auto py-12 px-4 sm:px-6 lg:px-8">

    <?php if ($tire): ?>
        
        <div class="mb-8 text-center">
            <h1 class="text-4xl sm:text-5xl font-black text-slate-800 mb-2 uppercase tracking-tight">
                <?php echo htmlspecialchars($tire['brand']); ?>
            </h1>
            <h2 class="text-2xl text-slate-500 font-semibold mb-6">
                <?php echo htmlspecialchars($tire['model']); ?>
            </h2>
            
            <?php if ($set_count > 1): ?>
                <span class="inline-block bg-blue-100 text-blue-800 font-black px-6 py-2 rounded-full text-lg border-2 border-blue-200 mb-6 shadow-sm">
                    Verkocht als Set van <?php echo $set_count; ?> Banden
                </span>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                
                <div class="p-8 sm:p-10 flex flex-col justify-center items-center text-center bg-slate-50">
                    <p class="text-slate-400 font-bold tracking-widest text-sm mb-2 uppercase">Velgmaat</p>
                    <p class="text-5xl font-black text-slate-900 mb-2">
                        <?php echo $tire['width'].'/'.$tire['ratio'].' R'.$tire['rim']; ?>
                    </p>
                    <p class="text-slate-500 font-medium">Inch: <strong><?php echo $tire['rim']; ?>"</strong></p>
                </div>
                
                <div class="p-8 sm:p-10">
                    <ul class="space-y-4">
                        <li class="flex justify-between items-center pb-4 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Seizoen</span>
                            <span class="font-bold text-lg text-slate-900 flex items-center gap-2">
                                <?php echo htmlspecialchars($tire['season']); ?>
                            </span>
                        </li>
                        <li class="flex justify-between items-center pb-4 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Staat</span>
                            <span class="font-bold text-lg text-slate-900">
                                <?php if ($tire['is_new']): ?>
                                    <span class="text-emerald-600">Nieuw</span>
                                <?php else: ?>
                                    <span class="text-slate-700">Profiel: <?php echo $tire['tread_depth']; ?> mm</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li class="flex justify-between items-center pb-4 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Load / Speed Index</span>
                            <span class="font-bold text-lg text-slate-900"><?php echo htmlspecialchars($tire['load_index'] . ' ' . $tire['speed_index']); ?></span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Referentie Code</span>
                            <span class="font-mono font-bold text-sm text-slate-400"><?php echo htmlspecialchars($tire['qr_id']); ?></span>
                        </li>
                    </ul>
                </div>
                
            </div>
        </div>
        
        <div class="bg-blue-600 rounded-2xl shadow-lg p-8 text-center text-white relative overflow-hidden">
            <div class="relative z-10">
                <h3 class="text-2xl font-bold mb-2">Interesse in deze band<?php echo $set_count > 1 ? 'en' : ''; ?>?</h3>
                <p class="text-blue-100 mb-6">Neem direct contact met ons op voor de actuele prijs en montagemogelijkheden. Bel ons of kom langs!</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="tel:0641595931" class="bg-white text-blue-700 hover:bg-blue-50 font-black py-3 px-8 rounded-lg transition-colors shadow-sm text-lg flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        Bel direct
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        
        <div class="space-y-4 py-4">
            
            <div class="bg-slate-900 rounded-3xl p-10 sm:p-16 text-center text-white shadow-2xl relative overflow-hidden pb-20">
                <div class="relative z-10">
                    <h1 class="text-4xl sm:text-6xl font-black mb-6 tracking-tight">Welkom bij Booij Banden</h1>
                    <p class="text-lg sm:text-xl text-slate-300 max-w-2xl mx-auto mb-10 leading-relaxed">
                        Al meer dan 25 jaar dé specialist in Culemborg. Met ruim 3.000 nieuwe en jong-gebruikte banden op voorraad vind je bij ons altijd de perfecte set.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-8 sm:p-10 shadow-xl border border-slate-100 relative z-20 -mt-16 mx-4 sm:mx-8 mb-16">
                <h2 class="text-2xl font-black text-slate-800 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    Zoek direct in onze voorraad
                </h2>
                <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="search_tires" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Breedte</label>
                        <select name="w" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none appearance-none">
                            <option value="">Alle</option>
                            <?php foreach($widths as $w): ?>
                                <option value="<?php echo $w; ?>" <?php if(isset($_GET['w']) && $_GET['w'] == $w) echo 'selected'; ?>><?php echo htmlspecialchars($w); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Hoogte</label>
                        <select name="r" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none appearance-none">
                            <option value="">Alle</option>
                            <?php foreach($ratios as $r): ?>
                                <option value="<?php echo $r; ?>" <?php if(isset($_GET['r']) && $_GET['r'] == $r) echo 'selected'; ?>><?php echo htmlspecialchars($r); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Inch (Velg)</label>
                        <select name="i" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none appearance-none">
                            <option value="">Alle</option>
                            <?php foreach($rims as $i): ?>
                                <option value="<?php echo $i; ?>" <?php if(isset($_GET['i']) && $_GET['i'] == $i) echo 'selected'; ?>><?php echo htmlspecialchars($i); ?> Inch</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 px-6 rounded-xl shadow-md transition-colors uppercase tracking-wider h-[46px] flex items-center justify-center">
                            Zoeken
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($searchResults !== null): ?>
                <div class="mb-16" id="resultaten">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-black text-slate-800">Resultaten (<?php echo count($searchResults); ?> producten gevonden)</h3>
                        <?php if(count($searchResults) > 0): ?>
                            <a href="index.php" class="text-blue-600 font-bold text-sm hover:underline">Wis filters</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(count($searchResults) === 0): ?>
                        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center text-amber-800">
                            <svg class="w-12 h-12 mx-auto mb-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <h4 class="text-xl font-bold mb-2">Helaas, geen banden gevonden</h4>
                            <p>We hebben momenteel geen banden op voorraad die exact aan deze maat voldoen. Bel ons gerust, mogelijk krijgen we ze snel binnen!</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach($searchResults as $res): ?>
                                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                                    <div class="p-6 border-b border-slate-100 flex justify-between items-start">
                                        <div>
                                            <h4 class="font-black text-xl text-slate-800 uppercase"><?php echo htmlspecialchars($res['brand']); ?></h4>
                                            <p class="text-slate-500 font-medium"><?php echo htmlspecialchars($res['model']); ?></p>
                                        </div>
                                        <span class="bg-slate-100 text-slate-800 font-black px-3 py-1 rounded text-sm whitespace-nowrap">
                                            <?php echo $res['width'].'/'.$res['ratio'].' R'.$res['rim']; ?>
                                        </span>
                                    </div>
                                    <div class="p-6 bg-slate-50 flex-grow grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="block text-slate-400 font-bold uppercase tracking-wider text-xs mb-1">Seizoen</span>
                                            <span class="font-bold text-slate-800"><?php echo htmlspecialchars($res['season']); ?></span>
                                        </div>
                                        <div>
                                            <span class="block text-slate-400 font-bold uppercase tracking-wider text-xs mb-1">Staat</span>
                                            <span class="font-bold text-slate-800">
                                                <?php echo $res['is_new'] ? '<span class="text-emerald-600">Nieuw</span>' : $res['tread_depth'] . ' mm'; ?>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="block text-slate-400 font-bold uppercase tracking-wider text-xs mb-1">Op Voorraad</span>
                                            <span class="inline-block bg-blue-100 text-blue-800 font-bold px-2 py-0.5 rounded">
                                                <?php echo $res['available_count']; ?> stuks
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-6 border-t border-slate-100 flex justify-between items-center">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Prijs (incl. montage)</span>
                                            <span class="text-2xl font-black text-slate-900">&euro;<?php echo number_format($res['price'], 2, ',', '.'); ?></span>
                                        </div>
                                        <a href="tel:0641595931" class="bg-slate-900 hover:bg-slate-800 text-white p-3 rounded-xl transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16 mt-8">
                <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <svg class="w-12 h-12 text-slate-800 mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <h3 class="text-xl font-black text-slate-800 mb-3">Gratis Montage & Balanceren</h3>
                    <p class="text-slate-500 leading-relaxed">Bij aanschaf van onze banden is de montage en het balanceren helemaal gratis. Zo ben je snel en veilig weer op weg.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <svg class="w-12 h-12 text-slate-800 mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    <h3 class="text-xl font-black text-slate-800 mb-3">Streng Gecontroleerd</h3>
                    <p class="text-slate-500 leading-relaxed">Al onze banden worden zorgvuldig nagemeten en getest op onze testmachine. Gegarandeerd géén bulten of scheuren.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <svg class="w-12 h-12 text-slate-800 mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                    <h3 class="text-xl font-black text-slate-800 mb-3">Topmerken (6 tot 8 mm)</h3>
                    <p class="text-slate-500 leading-relaxed">Van Michelin en Pirelli tot Continental. Wij leveren premium A-merken met uitstekend profiel tegen zeer scherpe prijzen.</p>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-10 sm:p-12 shadow-md border border-slate-100 mb-16">
                <h2 class="text-2xl font-black text-slate-800 mb-6">Meer over ons</h2>
                <div class="text-lg text-slate-600 leading-relaxed space-y-6">
                    <p class="text-xl text-slate-800 font-medium leading-snug">
                        Bij Booij Banden B.V. staan we al jarenlang bekend om onze vakkennis, klantvriendelijkheid en betrouwbare service. Of het nu gaat om nieuwe banden of voordelige demo banden — wij helpen u veilig op weg.
                    </p>
                    <p>
                        Wij zijn een <strong>familiebedrijf met passie</strong> voor banden en techniek. Vanuit onze werkplaats in Culemborg bedienen we zowel particuliere als zakelijke klanten met deskundige bandenservice.
                    </p>
                    <div class="bg-slate-50 p-6 sm:p-8 rounded-xl border border-slate-100 mt-4">
                        <h3 class="font-bold text-slate-800 mb-4 text-lg">Wat ons onderscheidt:</h3>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-3 mt-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span><strong>Complete service:</strong> We monteren, balanceren en repareren lekke of beschadigde banden.</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-3 mt-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span><strong>Eerlijk advies:</strong> Persoonlijk contact en een snelle, vakkundige aanpak waarbij we altijd met u meedenken.</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-3 mt-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span><strong>Scherpe tarieven:</strong> Zorgeloos en veilig de weg op, tegen een eerlijke prijs.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl overflow-hidden shadow-md border border-slate-100">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="bg-slate-50 p-10 sm:p-12 border-b md:border-b-0 md:border-r border-slate-100">
                        <h2 class="text-2xl font-black text-slate-800 mb-8">Contact & Locatie</h2>
                        <ul class="space-y-6 text-slate-600 text-lg">
                            <li class="flex items-start">
                                <svg class="w-6 h-6 mr-4 text-slate-400 mt-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <span>
                                    <strong class="text-slate-800 block mb-1">Booij Banden B.V.</strong>
                                    Plantijnweg 30<br>
                                    4104 BB Culemborg
                                </span>
                            </li>
                            <li class="flex items-center pt-2">
                                <svg class="w-6 h-6 mr-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                <a href="tel:0641595931" class="font-bold text-blue-600 hover:text-blue-800 transition-colors">06 41 59 59 31</a>
                            </li>
                        </ul>
                    </div>

                    <div class="p-10 sm:p-12">
                        <h2 class="text-2xl font-black text-slate-800 mb-8">Openingstijden</h2>
                        <ul class="space-y-4 text-slate-600">
                            <li class="flex justify-between items-center border-b border-slate-100 pb-4">
                                <span>Maandag - Vrijdag</span> 
                                <span class="font-bold text-slate-800 bg-slate-100 px-3 py-1 rounded-md">09:00 - 17:30</span>
                            </li>
                            <li class="flex justify-between items-center border-b border-slate-100 pb-4">
                                <span>Zaterdag</span> 
                                <span class="font-bold text-slate-800 bg-slate-100 px-3 py-1 rounded-md">09:00 - 16:00</span>
                            </li>
                            <li class="flex justify-between items-center pt-2">
                                <span>Zondag</span> 
                                <span class="font-bold text-red-400 bg-red-50 px-3 py-1 rounded-md">Gesloten</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="text-center pt-4">
                <p class="text-slate-400 text-sm">Beheerder of medewerker? <a href="login.php" class="text-slate-500 hover:text-blue-600 hover:underline font-semibold transition-colors">Log hier in</a>.</p>
            </div>
            
        </div>
        
    <?php endif; ?>

</main>

<?php 
// Scrol automatisch naar resultaten na het zoeken
if (isset($_GET['search_tires'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('resultaten').scrollIntoView({ behavior: 'smooth' });
        });
    </script>";
}
include 'footer.php'; 
?>