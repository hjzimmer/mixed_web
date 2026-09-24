# Mannschaftsverwaltung

Responsive PHP-Webanwendung zur Pflege einer Mannschaft, von Spieltagen und Spielereinsätzen.

## Voraussetzungen

- PHP 8.1 oder neuer
- Schreibrechte für das Verzeichnis `data/`

## Start lokal

```sh
php -S localhost:8080
```

Danach `http://localhost:8080` im Browser öffnen. Die Laufzeitdaten werden in `data/data.json` gespeichert. Diese Datei regelmäßig sichern.

## Datenhaltung und Punkte

- **Server:** Alle dauerhaften Daten liegen auf dem Server in `data/data.json`: Mannschaft, Spieler, Spieltage, Spiele, Sätze, Einsätze und Punkte. Der Ordner `data/` darf nicht öffentlich zum Download erreichbar sein.
- **Browser/Client:** Der Browser hält keine Anwendungsdaten dauerhaft. Es gibt weder `localStorage` noch eine Browser-Datenbank. Die Seite und die Formulare werden vom Server geladen.
- **Punkte zählen:** Die `+`- und `-`-Schaltflächen verändern zunächst nur das Punkte-Eingabefeld im Browser. Diese kleine Komfortfunktion läuft in `public/app.js`.
- **Speichern:** Erst beim Absenden von „Satz speichern“ sendet der Browser die ausgewählten Spieler und Punktwerte per `POST` an den Server. Der Server prüft die Werte und schreibt sie in `data/data.json`.
- **Verbindlicher Stand:** Maßgeblich sind immer die vom Server geprüften Werte in der JSON-Datei. Beim nächsten Laden werden die dort gespeicherten Werte wieder angezeigt.
- **Kein automatisches Zwischenspeichern:** Wird das Browserfenster geschlossen oder die Seite verlassen, bevor ein Satz gespeichert wurde, gehen die nicht abgesendeten Änderungen verloren.

Die Anwendung ist für einen lokalen Einzelbenutzerbetrieb ohne Anmeldung ausgelegt. Für einen öffentlichen Betrieb müssen insbesondere Authentifizierung, CSRF-Schutz und strengere Serverrechte ergänzt werden.