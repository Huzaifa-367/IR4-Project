"""Load YAML agent config merged with environment secrets."""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any, Dict, Mapping, MutableMapping, Optional

import yaml

_EDGE_ROOT = Path(__file__).resolve().parents[2]
_DEFAULT_CONFIG_DIR = _EDGE_ROOT / "configs"
_DEFAULT_VAR_DIR = _EDGE_ROOT / "var"


def edge_root() -> Path:
    return _EDGE_ROOT


def config_dir() -> Path:
    override = os.environ.get("IR4_EDGE_CONFIG_DIR")
    if override:
        return Path(override)
    return _DEFAULT_CONFIG_DIR


def var_dir() -> Path:
    override = os.environ.get("IR4_EDGE_VAR_DIR")
    if override:
        return Path(override)
    return _DEFAULT_VAR_DIR


def default_gas_config() -> Path:
    return config_dir() / "gas.yaml"


def default_rfid_config() -> Path:
    return config_dir() / "rfid.yaml"


def resolve_buffer_path(raw: Optional[str], default_name: str) -> Path:
    """Absolute path as-is; relative names go under EdgeCompute/var/."""
    if not raw:
        path = var_dir() / default_name
    else:
        path = Path(raw)
        if not path.is_absolute():
            path = var_dir() / path
    path.parent.mkdir(parents=True, exist_ok=True)
    return path


def _env(name: str, default: Optional[str] = None) -> Optional[str]:
    value = os.environ.get(name)
    if value is None or value == "":
        return default
    return value


def load_env_file(path: Path, *, override: bool = False) -> None:
    """Minimal KEY=VALUE loader (no python-dotenv dependency)."""
    if not path.is_file():
        return
    with path.open("r", encoding="utf-8") as handle:
        for line in handle:
            text = line.strip()
            if not text or text.startswith("#") or "=" not in text:
                continue
            key, value = text.split("=", 1)
            key = key.strip()
            value = value.strip().strip("'").strip('"')
            if not key:
                continue
            if not override and key in os.environ and os.environ.get(key) != "":
                continue
            os.environ[key] = value


def load_secrets(directory: Optional[Path] = None) -> None:
    """Load secrets.env then secrets.local.env (local wins)."""
    root = directory or config_dir()
    load_env_file(root / "secrets.env", override=False)
    load_env_file(root / "secrets.local.env", override=True)


def load_yaml(path: Path) -> Dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        data = yaml.safe_load(handle) or {}
    if not isinstance(data, dict):
        raise ValueError("Config root must be a mapping: {}".format(path))
    return data


def apply_env_overrides(
    config: MutableMapping[str, Any],
    *,
    token_env: str = "IR4_DEVICE_TOKEN",
    uuid_env: str = "IR4_DEVICE_UUID",
) -> Dict[str, Any]:
    """Overlay IR4_* environment variables onto a loaded YAML config."""
    ir4 = dict(config.get("ir4") or {})
    if _env("IR4_BASE_URL"):
        ir4["base_url"] = _env("IR4_BASE_URL")
    token = _env(token_env) or _env("IR4_DEVICE_TOKEN")
    uuid_value = _env(uuid_env) or _env("IR4_DEVICE_UUID")
    if token:
        ir4["device_token"] = token
    if uuid_value:
        ir4["device_uuid"] = uuid_value
    if _env("IR4_DRY_RUN") is not None:
        ir4["dry_run"] = _env("IR4_DRY_RUN", "0") in ("1", "true", "True", "yes")
    # Default LAN base if still unset.
    if not ir4.get("base_url"):
        ir4["base_url"] = "http://192.168.3.149:9100"
    config["ir4"] = ir4

    mqtt = dict(config.get("mqtt") or {})
    if _env("IR4_MQTT_USERNAME"):
        mqtt["username"] = _env("IR4_MQTT_USERNAME")
    if _env("IR4_MQTT_PASSWORD"):
        mqtt["password"] = _env("IR4_MQTT_PASSWORD")
    if mqtt:
        config["mqtt"] = mqtt
    return dict(config)


def require_ir4(config: Mapping[str, Any], *, dry_run_ok: bool = True) -> Dict[str, Any]:
    """Validate ir4 section; dry-run may omit token/uuid."""
    ir4 = dict(config.get("ir4") or {})
    dry_run = bool(ir4.get("dry_run", False))
    base_url = (ir4.get("base_url") or "").rstrip("/")
    if not dry_run and not base_url:
        raise ValueError("ir4.base_url (or IR4_BASE_URL) is required")
    if not dry_run:
        if not ir4.get("device_token"):
            raise ValueError(
                "Missing device token. Run: ./scripts/configure.sh  "
                "or set IR4_*_DEVICE_TOKEN in configs/secrets.env"
            )
        if not ir4.get("device_uuid"):
            raise ValueError(
                "Missing device UUID. Run: ./scripts/configure.sh  "
                "or set IR4_*_DEVICE_UUID in configs/secrets.env"
            )
    elif not dry_run_ok and dry_run:
        raise ValueError("dry-run is not allowed for this command")
    ir4["base_url"] = base_url
    ir4["dry_run"] = dry_run
    return ir4


def load_agent_config(
    path: Path,
    *,
    token_env: str = "IR4_DEVICE_TOKEN",
    uuid_env: str = "IR4_DEVICE_UUID",
) -> Dict[str, Any]:
    load_secrets(path.parent if path.parent.name else config_dir())
    return apply_env_overrides(
        load_yaml(path),
        token_env=token_env,
        uuid_env=uuid_env,
    )
