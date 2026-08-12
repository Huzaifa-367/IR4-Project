#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=resolve-artisan.sh
source "$SCRIPT_DIR/resolve-artisan.sh"

ir4_artisan wayfinder:generate --with-form "$@"
