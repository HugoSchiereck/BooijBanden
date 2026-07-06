<?php
// index.php (De publieke verkooppagina / landingspagina)

session_start();
require_once 'db.php';

$tire = null;
$set_count = 1;

// Als er een ID in de URL zit (bijv. ingescand via QR), haal de band op
if (isset($_GET['id'])) {
    $qr_id = $_GET['id'];
    
    // Alleen banden tonen die in voorraad of gemonteerd zijn
    $stmt = $pdo->prepare("SELECT * FROM tires WHERE qr_id = ? AND status != 'uitgeboekt'");
    $stmt->execute([$qr_id]);
    $tire = $stmt->fetch();
    
    // Kijk of de band onderdeel is van een set
    if ($tire && $tire['set_id']) {
        $stmtSet = $pdo->prepare("SELECT COUNT(*) FROM tires WHERE set_id = ?");
        $stmtSet->execute([$tire['set_id']]);
        $set_count = $stmtSet->fetchColumn();
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
                                <?php 
                                    if($tire['season'] == 'Zomer') echo '☀️ Zomer';
                                    elseif($tire['season'] == 'Winter') echo '❄️ Winter';
                                    else echo '🌦️ All Season';
                                ?>
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
                    <a href="tel:0641595931" class="bg-white text-blue-700 hover:bg-blue-50 font-black py-3 px-8 rounded-lg transition-colors shadow-sm text-lg">
                        📞 Bel direct
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        
        <div class="space-y-12 sm:space-y-16 py-4">
            
            <div class="bg-slate-900 rounded-3xl p-10 sm:p-16 text-center text-white shadow-2xl relative overflow-hidden">
                <div class="relative z-10">
                    <h1 class="text-4xl sm:text-6xl font-black mb-6 tracking-tight">Welkom bij Booij Banden</h1>
                    <p class="text-lg sm:text-xl text-slate-300 max-w-2xl mx-auto mb-10 leading-relaxed">
                        Al meer dan 25 jaar dé specialist in Culemborg. Met ruim 3.000 nieuwe en jong-gebruikte banden op voorraad vind je bij ons altijd de perfecte set.
                    </p>
                    <a href="tel:0641595931" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-black py-4 px-10 rounded-full transition-colors text-lg shadow-lg">
                        📞 06 41 59 59 31
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="text-5xl mb-6">🔧</div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">Gratis Montage & Balanceren</h3>
                    <p class="text-slate-500 leading-relaxed">Bij aanschaf van onze banden is de montage en het balanceren helemaal gratis. Zo ben je snel en veilig weer op weg.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="text-5xl mb-6">🔎</div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">Streng Gecontroleerd</h3>
                    <p class="text-slate-500 leading-relaxed">Al onze banden worden zorgvuldig nagemeten en getest op onze testmachine. Gegarandeerd géén bulten of scheuren.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="text-5xl mb-6">🏆</div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">Topmerken (6 tot 8 mm)</h3>
                    <p class="text-slate-500 leading-relaxed">Van Michelin en Pirelli tot Continental. Wij leveren premium A-merken met uitstekend profiel tegen zeer scherpe prijzen.</p>
                </div>
            </div>

            <div class="bg-white rounded-3xl overflow-hidden shadow-md border border-slate-100">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    
                    <div class="bg-slate-50 p-10 sm:p-12">
                        <h2 class="text-2xl font-black text-slate-800 mb-8">Contact & Locatie</h2>
                        <ul class="space-y-6 text-slate-600 text-lg">
                            <li class="flex items-start">
                                <span class="text-2xl mr-4">📍</span>
                                <span>
                                    <strong class="text-slate-800 block mb-1">Booij Banden B.V.</strong>
                                    Plantijnweg 30<br>
                                    4104 BB Culemborg
                                </span>
                            </li>
                            <li class="flex items-center pt-2">
                                <span class="text-2xl mr-4">📞</span>
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

<?php include 'footer.php'; ?>