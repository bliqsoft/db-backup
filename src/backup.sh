#!/bin/sh

# Verifica que las variables de entorno necesarias estén establecidas
if [ -z "$MYSQL_HOST" ] || [ -z "$MYSQL_PORT" ] || [ -z "$MYSQL_USER" ] || [ -z "$MYSQL_PASSWORD" ]; then
  echo "Environment variables MYSQL_HOST, MYSQL_PORT, MYSQL_USER and MYSQL_PASSWORD are required"
  exit 1
fi

BASE_BACKUP_DIR="/backups"

mkdir -p $BASE_BACKUP_DIR

CURRENT_DATETIME=$(date +"%Y%m%d-%H%M%S")

BACKUP_DIR="${BASE_BACKUP_DIR}/${CURRENT_DATETIME}"
mkdir -p $BACKUP_DIR

if [ -n "$MYSQL_DATABASES" ]; then
  DATABASES=$MYSQL_DATABASES
else
  DATABASES=$(mysql --host=${MYSQL_HOST} --port=${MYSQL_PORT} --user=${MYSQL_USER} --password=${MYSQL_PASSWORD} -e "SHOW DATABASES;" | grep -Ev "(Database|information_schema|performance_schema|mysql|sys)")
fi

for DB in $DATABASES; do
  echo "Dumping database: $DB"
  mysqldump --host=${MYSQL_HOST} --port=${MYSQL_PORT} --user=${MYSQL_USER} --password=${MYSQL_PASSWORD} $DB | gzip > ${BACKUP_DIR}/${DB}.sql.gz
  echo "Done"
done

echo "Backup files created in $BACKUP_DIR"

if [ -z "$DROPBOX_ACCESS_TOKEN" ]; then
  echo "Environment variable DROPBOX_ACCESS_TOKEN is required"
  exit 1
fi

echo "Uploading backup files to Dropbox..."
php send.php $BACKUP_DIR $CURRENT_DATETIME
