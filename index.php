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

<main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">

    <?php if ($tire): ?>
        
        <!-- Band Detailweergave voor Klanten -->
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
        
        <!-- Call To Action -->
        <div class="bg-blue-600 rounded-2xl shadow-lg p-8 text-center text-white relative overflow-hidden">
            <div class="relative z-10">
                <h3 class="text-2xl font-bold mb-2">Interesse in deze band<?php echo $set_count > 1 ? 'en' : ''; ?>?</h3>
                <p class="text-blue-100 mb-6">Neem direct contact met ons op voor de actuele prijs en montagemogelijkheden. Bel ons of kom langs!</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="tel:0123456789" class="bg-white text-blue-700 hover:bg-blue-50 font-black py-3 px-8 rounded-lg transition-colors shadow-sm text-lg">
                        📞 Bel direct
                    </a>
                </div>
            </div>
            <!-- Subtiele achtergrond decoratie -->
            <svg class="absolute opacity-10 w-64 h-64 -right-10 -bottom-20 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12,2C6.48,2,2,6.48,2,12s4.48,10,10,10s10-4.48,10-10S17.52,2,12,2z M12,20c-4.41,0-8-3.59-8-8s3.59-8,8-8s8,3.59,8,8 S16.41,20,12,20z M12.5,7H11v6l5.25,3.15l0.75-1.23l-4.5-2.67V7z"/></svg>
        </div>

    <?php else: ?>
        
        <!-- Algemene Landingspagina (Zonder Scan) -->
        <div class="text-center py-16">
            <h1 class="text-5xl font-black text-slate-800 mb-6 tracking-tight">Welkom bij Booij Banden</h1>
            <p class="text-xl text-slate-600 max-w-2xl mx-auto mb-10">Dé specialist in verkoop en montage van kwaliteitsbanden. We hebben een ruime voorraad nieuwe en nauwelijks gebruikte topmerken.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <div class="text-4xl mb-4">🔧</div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Vakkundige Montage</h3>
                    <p class="text-slate-500">Wij monteren en balanceren je nieuwe banden terwijl je wacht. Snel en veilig de weg weer op.</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <div class="text-4xl mb-4">🏆</div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Top Merken</h3>
                    <p class="text-slate-500">Van Michelin tot Vredestein. We leveren A-merken tegen zeer scherpe prijzen.</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <div class="text-4xl mb-4">🔎</div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Zorgvuldig Gecontroleerd</h3>
                    <p class="text-slate-500">Al onze banden worden streng gecontroleerd en nagemeten op profieldiepte en kwaliteit.</p>
                </div>
            </div>
            
            <div class="mt-12 pt-8 border-t border-slate-200">
                <p class="text-slate-500">Bent u medewerker? <a href="login.php" class="text-blue-600 font-bold hover:underline">Log hier in</a>.</p>
            </div>
        </div>
        
    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>