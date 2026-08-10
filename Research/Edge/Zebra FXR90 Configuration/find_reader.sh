#!/usr/bin/env bash
# Find the FXR90 on the local network: scan for HTTPS hosts, then probe the
# Zebra login endpoint. The host that returns a JWT is the reader.
# Usage: ./find_reader.sh [user] [password] [subnet]
#   ./find_reader.sh admin 'YOURPASS'              # auto-detect subnet
#   ./find_reader.sh admin 'change' 192.168.8.0/24 # explicit subnet (factory creds)
set -u

USER="${1:-admin}"
PASS="${2:-change}"
SUBNET="${3:-}"

if [ -z "$SUBNET" ]; then
  # take the first non-loopback IPv4 on an UP interface, e.g. 192.168.8.26/24
  CIDR=$(ip -br -4 addr | awk '$2=="UP" {print $3; exit}')
  [ -z "$CIDR" ] && { echo "no UP interface with IPv4 found; pass subnet explicitly"; exit 1; }
  # normalize to network/24 (good enough for home/lab routers)
  BASE=$(echo "$CIDR" | cut -d/ -f1 | cut -d. -f1-3)
  SUBNET="$BASE.0/24"
  echo "auto-detected subnet: $SUBNET (from $CIDR)"
fi

command -v nmap >/dev/null || { echo "installing nmap..."; sudo apt install -y nmap; }

echo "scanning $SUBNET for HTTPS hosts..."
HOSTS=$(sudo nmap -p 443 --open -oG - "$SUBNET" 2>/dev/null | awk '/443\/open/ {print $2}')
[ -z "$HOSTS" ] && { echo "no HTTPS hosts found on $SUBNET"; exit 1; }
echo "candidates: $HOSTS"

FOUND=""
for ip in $HOSTS; do
  RESP=$(curl -sk -m 5 -u "$USER:$PASS" "https://$ip/cloud/localRestLogin")
  if echo "$RESP" | grep -q '"message":"eyJ'; then
    echo "FXR90 FOUND at $ip (login OK as $USER)"
    FOUND="$ip"
  elif echo "$RESP" | grep -qi 'unauthorized\|password'; then
    echo "$ip looks like a Zebra reader but credentials were rejected: $RESP"
  fi
done

if [ -n "$FOUND" ]; then
  echo
  echo "next: uv run read_tags.py $FOUND $USER 'YOURPASS'"
else
  echo "no host answered the Zebra login endpoint. Check power/cabling, wait 2 min after boot, or try factory creds admin/change."
  exit 1
fi
