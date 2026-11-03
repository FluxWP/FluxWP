<?php header('Content-Type: application/json');
// /extras/... -> FluxWP Extras kaynagi (Flux Updater "Other sources"), digerleri ana sunucu
readfile(__DIR__ . (0 === strpos($_SERVER['REQUEST_URI'], '/extras') ? '/catalog-extras.json' : '/catalog.json'));
