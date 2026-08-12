#!/usr/bin/env bash
# Shared helper for SCC scripts: prefer Lerd PHP (lerd-mysql, extensions, PHP 8.4).
# Usage: source "$(dirname "${BASH_SOURCE[0]}")/resolve-artisan.sh"

export PATH="${HOME}/.local/share/lerd/bin:${HOME}/.local/bin:${PATH}"

if command -v lerd >/dev/null 2>&1; then
  export IR4_ARTISAN="lerd artisan"
  export WAYFINDER_COMMAND="lerd artisan wayfinder:generate"
else
  export IR4_ARTISAN="php artisan"
  export WAYFINDER_COMMAND="php artisan wayfinder:generate"
fi

ir4_artisan() {
  # shellcheck disable=SC2086
  $IR4_ARTISAN "$@"
}
