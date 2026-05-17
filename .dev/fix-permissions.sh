#!/usr/bin/env bash
set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ -n "$SUDO_UID" ] && [ -n "$SUDO_GID" ]; then
  OWNER="$SUDO_UID:$SUDO_GID"
else
  OWNER="$(id -u):$(id -g)"
fi
chown -R "$OWNER" "$DIR"
echo "Permissions corrigées : $DIR appartient à $OWNER"
