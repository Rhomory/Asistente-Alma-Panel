# Seguridad de la información — Panel Asistente Alma

Medidas aplicadas en este proyecto (coherentes con el punto 4.6 "Seguridad de accesos"
del Proyecto de Mejora):

1. **BD local, fuera de todo webroot público.** `database/database.sqlite` no es
   accesible por HTTP; la app solo escucha en `127.0.0.1` (`php artisan serve`).
   No se expone a la red ni a internet.
2. **Sin credenciales en la BD.** Solo se guardan nombres y URLs de referencia.
   Las contraseñas de aplicación de WordPress y los tokens de Figma viven
   únicamente en la configuración del MCP, nunca aquí ni en el código.
3. **Sin datos personales de clientes.** Los proyectos usan razones sociales
   referenciales; en la demo, clientes ficticios (Arequipa, Perú).
4. **Integridad:** SQLite en modo WAL (`config/database.php`), que protege la BD
   ante cortes de energía o cierres bruscos.
5. **Respaldos automáticos:** `scripts/backup.sh` verifica integridad
   (`PRAGMA integrity_check`), crea un snapshot consistente (`VACUUM INTO`),
   lo comprime con fecha en `~/backups/asistente-alma/` y deja una copia espejo
   en `E:\backups\asistente-alma\` (fuera de WSL). Retención: 14 días.
   Programado a diario vía cron (21:30).
6. **Control de versiones limpio:** `.env` y `database.sqlite` están en
   `.gitignore` (default de Laravel); el repositorio no contiene secretos.

Restaurar un respaldo:
```bash
gunzip -k ~/backups/asistente-alma/registro-AAAAMMDD-HHMM.sqlite.gz
cp ~/backups/asistente-alma/registro-AAAAMMDD-HHMM.sqlite \
   ~/proyectos/asistente-alma-panel/database/database.sqlite
```
