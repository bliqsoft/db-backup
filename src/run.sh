#!/bin/bash

if [ -z "$BACKUP_TIME" ]; then
  echo "Running backup now..."
  sh backup.sh
  echo "Backup completed"
  exit 0
fi

echo "Current time: $(date +"%H:%M")"
echo "Backup time: $BACKUP_TIME"

echo "Waiting for scheduled backup time..."

while true; do
  CURRENT_TIME=$(date +"%H:%M")
  # echo "Hora actual: $CURRENT_TIME"

  if [ "$CURRENT_TIME" = "$BACKUP_TIME" ]; then
    sh backup.sh
  fi

  # echo "Waiting..."
  sleep 60
done
