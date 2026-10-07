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

## Zugriff und Anmeldung

Alle Seiten sind ohne Anmeldung lesbar, einschließlich Mannschaft, Spieltagsdetails und Statistiken. Formulare bleiben sichtbar und bedienbar. Zum Speichern ist jedoch ein erfolgreicher Passwort-Login erforderlich; die Berechtigung gilt für die aktuelle Sitzung bis zum Logout.

Ohne Anmeldung lehnt der Server alle `POST`-Requests mit HTTP `403` ab, bevor Daten geändert werden. Nur der Login-Request ist ausgenommen und prüft das Passwort. Im Browser erscheint die Ablehnung als modaler Hinweis: Die aktuelle Seite und ihre Eingaben bleiben erhalten. Der Hinweis lässt sich mit „Schließen“ oder Escape schließen. Das gilt für normale Formularsendungen und automatische Speicherrequests. Ohne JavaScript zeigen normale Formularsendungen weiterhin eine Fehlerseite mit Login-Möglichkeit. Abgelehnte Eingaben werden nicht gespeichert und verschwinden beim Neuladen.

## Datenhaltung und Punkte

- **Server:** Alle dauerhaften Daten liegen auf dem Server in `data/data.json`: Mannschaft, Spieler, Spieltage, Spiele, Sätze, Einsätze und Punkte. Der Ordner `data/` darf nicht öffentlich zum Download erreichbar sein.
- **Browser/Client:** Der Browser hält keine Anwendungsdaten dauerhaft. Es gibt weder `localStorage` noch eine Browser-Datenbank. Die Seite und die Formulare werden vom Server geladen.
- **Punkte zählen:** Die `+`- und `-`-Schaltflächen verändern das Punkte-Eingabefeld und senden unmittelbar einen Speicherrequest. Diese Komfortfunktion läuft in `public/app.js`.
- **Speichern:** Punkte-Schaltflächen sowie Änderungen an Einsatz-Checkboxen und Satzergebnissen senden automatisch einen `POST`-Request; alternativ kann „Satz speichern“ verwendet werden. Der Server prüft Anmeldung und Werte, bevor er sie in `data/data.json` schreibt.
- **Verbindlicher Stand:** Maßgeblich sind immer die vom Server geprüften Werte in der JSON-Datei. Beim nächsten Laden werden die dort gespeicherten Werte wieder angezeigt.
- **Kein lokales Zwischenspeichern:** Nicht abgesendete oder vom Server abgelehnte Änderungen gehen beim Verlassen oder Neuladen der Seite verloren.

Die Anwendung ist für einen lokalen Einzelbenutzerbetrieb mit einfachem Passwort-Login für Schreibzugriffe ausgelegt. Für einen öffentlichen Betrieb müssen insbesondere Passwort-Hashing, Schutz vor wiederholten Login-Versuchen, CSRF-Schutz und strengere Serverrechte ergänzt werden.