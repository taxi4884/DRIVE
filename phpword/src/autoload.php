spl_autoload_register(function ($class) {
    // Debug: Zeige die angeforderte Klasse
    echo "Autoloader aufgerufen für Klasse: $class<br>";

    // Prüfen, ob die Klasse zu PhpOffice\PhpWord gehört
    if (strpos($class, 'PhpOffice\\PhpWord') === 0) {
        echo "Klasse gehört zum Namespace PhpOffice\\PhpWord<br>";

        // Namespace in Dateipfad umwandeln
        $classPath = str_replace(['PhpOffice\\PhpWord', '\\'], ['PhpWord', '/'], $class);
        $file = __DIR__ . '/PhpWord/' . $classPath . '.php'; // __DIR__ zeigt auf phpword/src

        // Debugging-Ausgabe
        echo "Erzeugter Pfad: $file<br>";

        // Datei prüfen und laden
        if (file_exists($file)) {
            echo "Datei existiert: $file<br>";
            require_once $file;
        } else {
            echo "Datei fehlt: $file<br>";
            throw new Exception("Klasse $class konnte nicht geladen werden. Datei $file fehlt.");
        }
    } else {
        echo "Klasse $class gehört nicht zum Namespace PhpOffice\\PhpWord<br>";
    }
});
