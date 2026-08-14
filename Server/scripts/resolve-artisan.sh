#!/usr/bin/env bash
# Shared helper for SCC scripts: prefer Lerd PHP (lerd-mysql, extensions, PHP 8.4).
# Usage: source "$(dirname "${BASH_SOURCE[0]}")/resolve-artisan.sh"

# Under `sudo`, $HOME becomes /root and Lerd is missing from PATH — use the
# invoking user's home so `lerd artisan` still resolves DB hostnames.
IR4_USER_HOME="${HOME}"
if [ "$(id -u)" -eq 0 ] && [ -n "${SUDO_USER:-}" ]; then
  IR4_USER_HOME="$(getent passwd "$SUDO_USER" | cut -d: -f6)"
  if [ -z "$IR4_USER_HOME" ] || [ ! -d "$IR4_USER_HOME" ]; then
    IR4_USER_HOME="/home/${SUDO_USER}"
  fi
fi

export PATH="${IR4_USER_HOME}/.local/share/lerd/bin:${IR4_USER_HOME}/.local/bin:${HOME}/.local/share/lerd/bin:${HOME}/.local/bin:${PATH}"

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

# Host PHP cannot resolve Docker DNS names like lerd-mysql (CACHE_STORE=database).
ir4_require_lerd() {
  if command -v lerd >/dev/null 2>&1; then
    return 0
  fi

  echo "ERROR: Lerd not found on PATH (needed for DB hostnames like lerd-mysql)." >&2
  echo "Install Lerd via scripts/01-setup.sh, or run as the SCC user (not root/sudo)." >&2
  echo "Expected: ${IR4_USER_HOME}/.local/share/lerd/bin/lerd" >&2
  return 1
}
