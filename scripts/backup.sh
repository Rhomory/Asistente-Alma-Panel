#!/usr/bin/env bash
# Respaldo diario de la BD del Asistente Alma (SQLite).
# 1) Verifica integridad, 2) copia fechada comprimida en ~/backups/asistente-alma,
# 3) copia espejo fuera de WSL (E:\backups\asistente-alma), 4) retención 14 días.
set -euo pipefail

DB="$HOME/proyectos/asistente-alma-panel/database/database.sqlite"
DEST="$HOME/backups/asistente-alma"
ESPEJO="/mnt/e/backups/asistente-alma"
FECHA="$(date +%Y%m%d-%H%M)"

mkdir -p "$DEST"

# 1) Integridad (usa el PDO de PHP; no requiere el cliente sqlite3)
CHK=$(php -r '$db=new PDO("sqlite:".$argv[1]); echo $db->query("PRAGMA integrity_check")->fetchColumn();' "$DB")
if [ "$CHK" != "ok" ]; then
    echo "[ERROR] integrity_check devolvió: $CHK — NO se respalda una BD dañada." >&2
    exit 1
fi

# 2) Copia consistente (VACUUM INTO crea un snapshot limpio aunque la app esté abierta)
php -r '$db=new PDO("sqlite:".$argv[1]); $db->exec("VACUUM INTO ".$db->quote($argv[2]));' "$DB" "$DEST/registro-$FECHA.sqlite"
gzip -f "$DEST/registro-$FECHA.sqlite"

# 3) Espejo fuera de WSL (por si se reinstala la distro)
if [ -d /mnt/e ]; then
    mkdir -p "$ESPEJO"
    cp "$DEST/registro-$FECHA.sqlite.gz" "$ESPEJO/" || echo "[AVISO] no se pudo copiar el espejo a E:"
fi

# 4) Retención: 14 días
find "$DEST" -name 'registro-*.sqlite.gz' -mtime +14 -delete
[ -d "$ESPEJO" ] && find "$ESPEJO" -name 'registro-*.sqlite.gz' -mtime +14 -delete

echo "[OK] respaldo registro-$FECHA.sqlite.gz (integridad: $CHK)"
