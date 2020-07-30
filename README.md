# api
 
Zugriffsschicht für die Web-Seite (Client) auf die Datenbank erfolgt mit diesen PHP-Scripts, die JSON zurückliefern. Die API-Calls sind mit JWT gesichert. Beim Login wird ein Token erstellt, das der Client bei jedem Zugriff im Bearer-Format mitsenden muss. Das Token wird vom (Angular-) Client im Browser-Storage zwischengespeichert.

Benutzt wird der native MySql-Client; alles läuft über Stored Procedures.
